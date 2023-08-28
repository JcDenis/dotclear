<?php
/**
 * @brief importExport, a plugin for Dotclear 2
 *
 * @package Dotclear
 * @subpackage Plugins
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\importExport;

use ArrayObject;
use Exception;
use dcCore;
use Dotclear\Core\Backend\Notices;
use Dotclear\Core\Backend\Page;
use Dotclear\Core\Core;
use Dotclear\Core\Process;
use Dotclear\Helper\Html\Html;

class Manage extends Process
{
    public static function init(): bool
    {
        self::status(My::checkContext(My::MANAGE));

        $modules = new ArrayObject(['import' => [], 'export' => []]);

        # --BEHAVIOR-- importExportModules -- ArrayObject
        Core::behavior()->callBehavior('importExportModulesV2', $modules);

        Core::backend()->type = null;
        if (!empty($_REQUEST['type']) && in_array($_REQUEST['type'], ['export', 'import'])) {
            Core::backend()->type = $_REQUEST['type'];
        }

        Core::backend()->modules = $modules;
        Core::backend()->module  = null;

        $module = $_REQUEST['module'] ?? false;
        if (Core::backend()->type && $module !== false && isset(Core::backend()->modules[Core::backend()->type]) && in_array($module, Core::backend()->modules[Core::backend()->type])) {
            Core::backend()->module = new $module(dcCore::app());
            Core::backend()->module->init();
        }

        return self::status();
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        if (Core::backend()->type && Core::backend()->module !== null && !empty($_REQUEST['do'])) {
            try {
                Core::backend()->module->process($_REQUEST['do']);
            } catch (Exception $e) {
                Core::error()->add($e->getMessage());
            }
        }

        return true;
    }

    /**
     * Renders the page.
     */
    public static function render(): void
    {
        if (!self::status()) {
            return;
        }

        Page::openModule(
            My::name(),
            My::cssLoad('style') .
            Page::jsJson('ie_msg', ['please_wait' => __('Please wait...')]) .
            My::jsLoad('script')
        );

        if (Core::backend()->type && Core::backend()->module !== null) {
            echo
            Page::breadcrumb(
                [
                    __('Plugins')                                        => '',
                    My::name()                                           => Core::backend()->getPageURL(),
                    Html::escapeHTML(Core::backend()->module->name) => '',
                ]
            ) .
            Notices::getNotices() .
            '<div id="ie-gui">';

            Core::backend()->module->gui();

            echo
            '</div>';
        } else {
            echo
            Page::breadcrumb(
                [
                    __('Plugins') => '',
                    My::name()    => '',
                ]
            ) .
            Notices::getNotices() .

            '<h3>' . __('Import') . '</h3>' .
            self::listImportExportModules(Core::backend()->modules['import']) .

            '<h3>' . __('Export') . '</h3>' .
            '<p class="info">' . sprintf(
                __('Export functions are in the page %s.'),
                '<a href="' . Core::backend()->url->get('admin.plugin.maintenance', ['tab' => 'backup']) . '#backup">' .
                __('Maintenance') . '</a>'
            ) . '</p>';
        }

        Page::helpBlock('import');

        Page::closeModule();
    }

    protected static function listImportExportModules($modules)
    {
        $res = '';
        foreach ($modules as $id) {
            if (is_subclass_of($id, Module::class)) {
                $o = new $id(dcCore::app());

                $res .= '<dt><a href="' . $o->getURL(true) . '">' . Html::escapeHTML($o->name) . '</a></dt>' .
                '<dd>' . Html::escapeHTML($o->description) . '</dd>';
            }
        }

        return '<dl class="modules">' . $res . '</dl>';
    }
}
