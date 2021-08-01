<?php

namespace Proklung\Error\Notifier;

use Bitrix\Main\Application;

/**
 * Class ClearTableAgent
 * @package Proklung\Error\Notifier
 *
 * @since 01.08.2021
 */
class ClearTableAgent
{
    /**
     * @return string
     */
    public static function clear() : string
    {
        $connection = Application::getConnection();
        $connection->truncateTable('b_fatal_error_log');

        return '\Proklung\Error\Notifier\ClearTableAgent::clear();';
    }
}
