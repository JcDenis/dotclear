<?php
/**
 * @brief dcProxyV2, a plugin for Dotclear 2
 *
 * Public behaviours
 *
 * @package Dotclear
 * @subpackage Plugins
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
class dcProxyV2PublicBehaviors
{
    // Count : 14

    public static function publicAfterContentFilter($tag, $args)
    {
        return dcCore::app()->behavior->call('publicAfterContentFilter', dcCore::app(), $tag, $args);
    }
    public static function publicAfterDocument()
    {
        return dcCore::app()->behavior->call('publicAfterDocument', dcCore::app());
    }
    public static function publicBeforeContentFilter($tag, $args)
    {
        return dcCore::app()->behavior->call('publicBeforeContentFilter', dcCore::app(), $tag, $args);
    }
    public static function publicBeforeDocument()
    {
        return dcCore::app()->behavior->call('publicBeforeDocument', dcCore::app());
    }
    public static function publicBeforeReceiveTrackback($args)
    {
        return dcCore::app()->behavior->call('publicBeforeReceiveTrackback', dcCore::app(), $args);
    }
    public static function publicContentFilter($tag, $args, $filter)
    {
        return dcCore::app()->behavior->call('publicContentFilter', dcCore::app(), $tag, $args, $filter);
    }
    public static function publicPrepend()
    {
        return dcCore::app()->behavior->call('publicPrepend', dcCore::app());
    }

    public static function templateAfterBlock($current_tag, $attr)
    {
        return dcCore::app()->behavior->call('templateAfterBlock', dcCore::app(), $current_tag, $attr);
    }
    public static function templateAfterValue($current_tag, $attr)
    {
        return dcCore::app()->behavior->call('templateAfterValue', dcCore::app(), $current_tag, $attr);
    }
    public static function templateBeforeBlock($current_tag, $attr)
    {
        return dcCore::app()->behavior->call('templateBeforeBlock', dcCore::app(), $current_tag, $attr);
    }
    public static function templateBeforeValue($current_tag, $attr)
    {
        return dcCore::app()->behavior->call('templateBeforeValue', dcCore::app(), $current_tag, $attr);
    }
    public static function templateInsideBlock($current_tag, $attr, $array_content)
    {
        return dcCore::app()->behavior->call('templateInsideBlock', dcCore::app(), $current_tag, $attr, $array_content);
    }
    public static function tplAfterData($_r)
    {
        return dcCore::app()->behavior->call('tplAfterData', dcCore::app(), $_r);
    }
    public static function tplBeforeData()
    {
        return dcCore::app()->behavior->call('tplBeforeData', dcCore::app());
    }
}
