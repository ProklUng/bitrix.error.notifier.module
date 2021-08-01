<?php

use Bitrix\Main\Application;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\Entity\Base;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\SystemException;
use Proklung\Error\Notifier\ErrorLogTable;
use ProklUng\Module\Boilerplate\Module;
use ProklUng\Module\Boilerplate\ModuleUtilsTrait;

Loc::loadMessages(__FILE__);

class proklung_error_notifier extends CModule
{
    use ModuleUtilsTrait;

    /**
     * proklung_error_notifier constructor.
     */
    public function __construct()
    {
        $arModuleVersion = [];

        include __DIR__.'/version.php';

        if (is_array($arModuleVersion)
            &&
            array_key_exists('VERSION', $arModuleVersion)) {
            $this->MODULE_VERSION = $arModuleVersion['VERSION'];
            $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        }

        $this->MODULE_FULL_NAME = 'error.notifier';
        $this->MODULE_VENDOR = 'proklung';
        $prefixLangCode = 'ERROR_NOTIFIER';

        $this->MODULE_NAME = Loc::getMessage($prefixLangCode.'_MODULE_NAME');
        $this->MODULE_ID = $this->MODULE_VENDOR.'.'.$this->MODULE_FULL_NAME;
        $this->MODULE_TABLE_ENTITY = ErrorLogTable::class;

        $this->MODULE_DESCRIPTION = Loc::getMessage($prefixLangCode.'_MODULE_DESCRIPTION');
        $this->MODULE_GROUP_RIGHTS = 'N';
        $this->PARTNER_NAME = Loc::getMessage($prefixLangCode.'_MODULE_PARTNER_NAME');
        $this->PARTNER_URI = Loc::getMessage($prefixLangCode.'MODULE_PARTNER_URI');

        $this->moduleManager = new Module(
            [
                'MODULE_ID' => $this->MODULE_ID,
                'VENDOR_ID' => $this->MODULE_VENDOR,
                'MODULE_VERSION' => $this->MODULE_VERSION,
                'MODULE_VERSION_DATE' => $this->MODULE_VERSION_DATE,
                'ADMIN_FORM_ID' => $this->MODULE_VENDOR.'_settings_form',
            ]
        );

        $this->moduleManager->addModuleInstance($this);
        $this->options();
    }

    /**
     * @inheritDoc
     */
    public function installDB()
    {
        if (Loader::includeModule($this->MODULE_ID)) {
            if (!Application::getConnection()->isTableExists(
                Base::getInstance(ErrorLogTable::class)->getDBTableName()
            )) {
                Base::getInstance(ErrorLogTable::class)->createDBTable();
            }
        }

        if (!Application::getConnection()->isTableExists(
            Base::getInstance(ErrorLogTable::class)->getDBTableName()
        )) {
            throw new RuntimeException(
                'Table for module ' . $this->MODULE_ID . ' not created'
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function uninstallDB()
    {
        $connection = Application::getInstance()->getConnection();
        try {
            $connection->dropTable(
                Base::getInstance(ErrorLogTable::class)->getDBTableName()
            );
        } catch (Exception $e) {
            // Ошибки типа таблица не найдена - глушатся.
        }
    }

    /**
     * @inheritDoc
     */
    public function InstallEvents()
    {
        \CAgent::AddAgent(
            '\\Proklung\\Error\\Notifier\\ClearTableAgent::clear();',
            'proklung.error.notifier',
            'N',
            10 * 24 * 3600,
            '',
            'Y',
            ''
        );
    }

    /**
     * @inheritDoc
     */
    public function UnInstallEvents()
    {
        \CAgent::RemoveModuleAgents('proklung.error.notifier');
    }
}
