<?php
/**
 * @brief dcProxyV2, a plugin for Dotclear 2
 *
 * Core behaviours
 *
 * @package Dotclear
 * @subpackage Plugins
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
class dcProxyV2CoreBehaviors
{
    // Count: 3

    public static function coreBeforeLoadingNsFiles($that, $lang)
    {
        return dcCore::app()->behavior->call('coreBeforeLoadingNsFiles', dcCore::app(), $that, $lang);
    }
    public static function coreCommentSearch($table)
    {
        return dcCore::app()->behavior->call('coreCommentSearch', dcCore::app(), $table);
    }
    public static function corePostSearch($table)
    {
        return dcCore::app()->behavior->call('corePostSearch', dcCore::app(), $table);
    }
}
