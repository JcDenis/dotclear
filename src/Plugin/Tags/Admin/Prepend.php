<?php
/**
 * @class Dotclear\Plugin\Tags\Admin\Prepend
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginTags
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Tags\Admin;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependAdmin;
use Dotclear\Plugin\Tags\Lib\TagsAdmin;
use Dotclear\Plugin\Tags\Lib\TagsCore;
use Dotclear\Plugin\Tags\Lib\TagsUrl;
use Dotclear\Plugin\Tags\Lib\TagsXmlrpc;
use Dotclear\Plugin\Tags\Lib\TagsWidgets;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Prepend extends AbstractPrepend
{
    use TraitPrependAdmin;

    public static function loadModule(): void
    {
        # Menu and favs
        static::addStandardMenu('Blog');
        static::addStandardFavorites('usage,contentadmin');

        # Behaviors and url
        TagsUrl::initTags();
        TagsCore::initTags();
        TagsAdmin::initTags();
        TagsXmlrpc::initTags();

        # Widgets
        if (dotclear()->adminurl()->called() == 'admin.plugin.Widgets') {
            TagsWidgets::initTags();
        }
    }
}
