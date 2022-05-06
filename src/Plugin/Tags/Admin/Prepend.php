<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Tags\Admin;

// Dotclear\Plugin\Tags\Admin\Prepend
use Dotclear\App;
use Dotclear\Modules\ModulePrepend;
use Dotclear\Plugin\Tags\Common\TagsCore;
use Dotclear\Plugin\Tags\Common\TagsUrl;
use Dotclear\Plugin\Tags\Common\TagsXmlrpc;
use Dotclear\Plugin\Tags\Common\TagsWidgets;

/**
 * Admin prepend for plugin Tags.
 *
 * @ingroup  Plugin Tags
 */
class Prepend extends ModulePrepend
{
    public function loadModule(): void
    {
        // Menu and favs
        $this->addStandardMenu('Blog');
        $this->addStandardFavorites('usage,contentadmin');

        // Behaviors and url
        new TagsUrl();
        new TagsCore();
        new TagsBehavior();
        new TagsXmlrpc();

        // Widgets
        if (App::core()->adminurl()->is('admin.plugin.Widgets')) {
            new TagsWidgets();
        }
    }
}
