<?php
/**
 * @brief dcLegacyEditor, a plugin for Dotclear 2
 *
 * @package Dotclear
 * @subpackage Plugins
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\dcLegacyEditor;

use dcAdmin;
use dcCore;
use dcNsProcess;
use dcPage;
use Dotclear\Helper\Html\WikiToHtml;

class Backend extends dcNsProcess
{
    public static function init(): bool
    {
        return (static::$init = My::checkContext(My::BACKEND));
    }

    public static function process(): bool
    {
        if (!static::$init) {
            return false;
        }

        My::backendSidebarMenuIcon(scheme: '');

        if (dcCore::app()->blog->settings->dclegacyeditor->active) {
            dcCore::app()->wiki->initWikiPost();

            dcCore::app()->formater->add('dcLegacyEditor', 'xhtml', fn ($s) => $s);
            dcCore::app()->formater->setName('xhtml', __('HTML'));

            dcCore::app()->formater->add('dcLegacyEditor', 'wiki', [dcCore::app()->wiki, 'transform']);
            dcCore::app()->formater->setName('wiki', __('Dotclear wiki'));

            dcCore::app()->behavior->add([
                'adminPostEditor' => [BackendBehaviors::class, 'adminPostEditor'],
                'adminPopupMedia' => [BackendBehaviors::class, 'adminPopupMedia'],
                'adminPopupLink'  => [BackendBehaviors::class, 'adminPopupLink'],
                'adminPopupPosts' => [BackendBehaviors::class, 'adminPopupPosts'],
            ]);

            // Register REST methods
            dcCore::app()->rest->addFunction('wikiConvert', [Rest::class, 'convert']);
        }

        return true;
    }
}
