<?php

use Bitrix\Main\Loader;

Loader::registerAutoLoadClasses(
    'proklung.error.notifier',
    [
        'Proklung\Error\Notifier\ErrorLogTable' => 'lib/ErrorLogTable.php',
    ]
);
