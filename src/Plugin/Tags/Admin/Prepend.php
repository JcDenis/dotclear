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

class Prepend extends AbstractPrepend
{
    use TraitPrependAdmin;

    public function loadModule(): void
    {
        # Menu and favs
        $this->addStandardMenu('Blog');
        $this->addStandardFavorites('usage,contentadmin');

        # Behaviors and url
        new TagsUrl();
        new TagsCore();
        new TagsBehavior();
        new TagsXmlrpc();

        # Widgets
        if (dotclear()->adminurl()->is('admin.plugin.Widgets')) {
            new TagsWidgets();
        }
    }
}
