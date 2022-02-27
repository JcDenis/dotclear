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
use Dotclear\Plugin\Tags\Admin\TagsBehavior;
use Dotclear\Plugin\Tags\Common\TagsCore;
use Dotclear\Plugin\Tags\Common\TagsUrl;
use Dotclear\Plugin\Tags\Common\TagsXmlrpc;
use Dotclear\Plugin\Tags\Common\TagsWidgets;

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
        TagsBehavior::initTags();
        TagsXmlrpc::initTags();

        # Widgets
        if (dotclear()->adminurl()->called() == 'admin.plugin.Widgets') {
            TagsWidgets::initTags();
        }
    }
}
