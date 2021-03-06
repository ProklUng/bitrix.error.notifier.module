<?php

namespace Proklung\Error\Notifier;

use Bitrix\Main\Application;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\Diag\ExceptionHandlerLog;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use Bitrix\Main\Type\DateTime;
use Exception;
use Bitrix\Main\Config\Option;
use Proklung\Notifier\Bitrix\Notifier\BitrixNotification;
use RuntimeException;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;

/**
 * Class ErrorHandler
 * @package Proklung\Error\Notifier
 *
 * @since 01.08.2021
 */
class ErrorHandler extends ExceptionHandlerLog
{
    private const OPTION_TYPES = 'types';

    /**
     * @var boolean[] $logTypeFlags
     */
    private $logTypeFlags;

    /**
     * @var array $options
     */
    private $options = [];

    /**
     * ErrorHandler constructor.
     */
    public function __construct()
    {
        /**
         * По-умолчанию логируется всё, кроме LOW_PRIORITY_ERROR.
         * Этот тип ошибки засоряет логи и появляется не только часто,
         * но и происходит от ошибок в коде ядра Битрикс.
         */
        $this->logTypeFlags = [
            self::UNCAUGHT_EXCEPTION => true,
            self::CAUGHT_EXCEPTION   => true,
            self::ASSERTION          => true,
            self::FATAL              => true,
        ];
    }

    /**
     * @inheritdoc
     */
    public function initialize(array $options)
    {
        $this->initTypes($options);

        $this->options = $options;
        $this->initOptions();
    }

    /**
     * @inheritdoc
     * @throws ArgumentException | SystemException
     * @throws Exception
     */
    public function write($exception, $logType)
    {
        if ((!array_key_exists($logType, $this->logTypeFlags)
                || true !== $this->logTypeFlags[$logType])
            ||
            // Только если этот proklung.notifier установлен
            !ModuleManager::isModuleInstalled('proklung.notifier')
            ||
            !in_array($this->options['env'], $this->options['allowed_env'], true)
        ) {
            return;
        }

        if ($exception instanceof Exception && !$this->has($exception)) {
            $this->send($exception);

            ErrorLogTable::add(
                [
                    'DATE_CREATE' => new DateTime(),
                    'MD5' => md5(serialize($exception)),
                    'EXCEPTION' => serialize($exception),
                ]
            );
        }
    }

    /**
     * Экземпляр notifier.
     *
     * @return NotifierInterface
     */
    private function getNotifier() : NotifierInterface
    {
        if (!class_exists(\Proklung\Notifier\DI\Services::class)
            ||
            !\Proklung\Notifier\DI\Services::has('notifier')
        ) {
            throw new RuntimeException('Не установлен родительский модуль.');
        }

        return \Proklung\Notifier\DI\Services::get('notifier');
    }

    /**
     * Отправка уведомлений.
     *
     * @param Exception $exception
     *
     * @return void
     * @throws Exception
     */
    private function send(Exception $exception) : void
    {
        $notifier = $this->getNotifier();
        $importancy = $this->options['importancy'];

        $envelope = \Proklung\Notifier\DI\Services::getParameter('envelope');
        $emails = $envelope['recipients'] ?? [];
        if (!$emails) {
            throw new RuntimeException('Email of recipitients not setting.');
        }

        $email = $emails[0];

        if ($this->options['recipient']) {
            $email = (string)$this->options['recipient'];
        }

        $request = Application::getInstance()->getContext()->getRequest();
        $uriString = $request->getRequestUri();

        $title = $request->getServer()->getServerName();
        if (!$title) {
            $title = Option::get('main', 'server_name', '');
        }

        $body = 'Url: ' . $uriString . ' ';
        $body = $body . get_class($exception) .
            ': ' . $exception->getMessage() .
            ' in ' . $exception->getFile() .
            ' at line ' . $exception->getLine();

        $notification = (new BitrixNotification($title))
            ->content($body)
            ->importance($importancy);

        $notifier->send($notification, new Recipient($email));
    }

    /**
     * Есть запись о такой ошибке в таблице?
     *
     * @param Exception $e Ошибка.
     *
     * @return boolean
     *
     * @throws ArgumentException | SystemException | ObjectPropertyException ORM ошибки.
     */
    private function has(Exception $e) : bool
    {
        $hash = md5(serialize($e));

        $query = ErrorLogTable::getList([
            'filter' => [
                '=MD5' => $hash
            ]
        ]);

        return count($query->fetchAll()) > 0;
    }

    /**
     * Инициализация типов обрабатываемых ошибок.
     *
     * @param array $options Опции.
     *
     * @return void
     */
    private function initTypes(array $options) : void
    {
        if (!array_key_exists(self::OPTION_TYPES, $options) || !is_array($options[self::OPTION_TYPES])) {
            return;
        }

        $this->logTypeFlags = [];
        foreach ($options[self::OPTION_TYPES] as $logType) {
            if (is_int($logType)) {
                $this->logTypeFlags[$logType] = true;
            }
        }
    }

    /**
     * Обработка параметров.
     *
     * @return void
     */
    private function initOptions(): void
    {
        $this->options['env'] = $this->options['env'] ?? 'prod';

        $this->options['allowed_env'] = $this->options['allowed_env'] ?? ['prod'];
        if (count($this->options['allowed_env']) === 0) {
            $this->options['allowed_env'] = ['prod'];
        }

        $this->options['importancy'] = $this->options['importancy'] ?? Notification::IMPORTANCE_URGENT;
    }
}
