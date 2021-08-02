# Модуль для Битрикса, оповещающий о фатальных ошибках на проекте через Symfony Notifier 

Пример практического применения нотификатора Symfony в проектах на Битриксе.

Отправляет сообщения о фатальных ошибках на проекте в каналы согласно конфигурации корневого [модуля](https://github.com/ProklUng/bitrix.notifier.module): sms, почта,
чаты (включая Телеграм).

## ВАЖНО

Этот модуль зависит от установки, активации и настройки модуля [proklung.notifier](https://github.com/ProklUng/bitrix.notifier.module). Без него
работать не будет (но и ругаться тоже).

## Установка

composer.json основного проекта:

```json
  "extra": {
    "installer-paths": {
      "./bitrix/modules/{$name}/": ["type:bitrix-d7-module", "type:bitrix-module"],
      "./bitrix/components/{$name}/": ["type:bitrix-d7-component", "type:bitrix-component"],
      "./bitrix/templates/{$name}/": ["type:bitrix-d7-template", "type:bitrix-theme"]
    }
  }
```

И:

```json
    "repositories": [
        {
          "type": "git",
          "url": "https://github.com/proklung/bitrix.error.notifier.module"
        }
    ]
```

```bash
$ composer require proklung/bitrix-error-notifier-module
```

Установите модуль `bitrix.error.notifier` в административном интерфейсе сайта `bitrix/admin/partner_modules.php`

`init.php`:

```php
use Bitrix\Main\Loader;

Loader::includeModule('proklung.notifier');

```
### Использование

`/bitrix/.settings.php`:

```php
use Symfony\Component\Notifier\Notification\Notification;

return [
    'exception_handling' =>
        array(
            'value' =>
                array(
                    'debug' => env('DEBUG', false),
                    'handled_errors_types' => 4437,
                    'exception_errors_types' => 4437,
                    'ignore_silence' => false,
                    'assertion_throws_exception' => true,
                    'assertion_error_type' => 256,
                    'log' => array (
                        'class_name' => \Proklung\Error\Notifier\ErrorHandler::class,
                        'required_file' => 'bitrix/modules/proklung.error.notifier/lib/ErrorHandler.php',
                        'settings' => array (
                            'types' => [
                                \Bitrix\Main\Diag\ExceptionHandlerLog::UNCAUGHT_EXCEPTION,
                                \Bitrix\Main\Diag\ExceptionHandlerLog::IGNORED_ERROR,
                                \Bitrix\Main\Diag\ExceptionHandlerLog::FATAL,
                            ],
                            // Получатель почты; перебивает параметры родительского модуля
                            'recipient' => 'email@gmail.com',
                            // Или какой-нибудь иной способ различения dev/prod среды
                            // По умолчанию - dev
                            'env' => env('DEBUG', false) ? 'dev' : 'prod',
                            // В каком окружении работать. По умолчанию - prod.
                            'allowed_env' => ['dev', 'prod'],
                            // Уровень важности согласно channel_policy (см. документацию к модулю proklung.notifier)
                            // По умолчанию - urgent
                            'importancy' => Notification::IMPORTANCE_URGENT,
                        ),
                    ),
                ),
            'readonly' => false,
        ),
];
```
### Нюансы

1) Сообщение об ошибке рассылается всего один раз (иначе чревато флудом). Каждые сутки таблица с информацией 
об отправленных уведомлениях очищается посредством агента. Процесс начинается по новой.