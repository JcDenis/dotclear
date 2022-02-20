<?php
/**
 * @class Dotclear\Plugin\ImportExport\Admin\Page
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginMaintenance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\ImportExport\Admin;

use ArrayObject;

use Dotclear\Html\Html;
use Dotclear\Module\AbstractPage;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Page extends AbstractPage
{

    protected function getPermissions(): string|null|false
    {
        return 'admin';
    }

    protected function getPagePrepend(): ?bool
    {

        $modules = new ArrayObject(['import' => [], 'export' => []]);

        # --BEHAVIOR-- importExportModules
        dotclear()->behavior()->call('importExportModules', $modules);

        $type = null;
        if (!empty($_REQUEST['type']) && in_array($_REQUEST['type'], ['export', 'import'])) {
            $type = $_REQUEST['type'];
        }

        $module = null;
        if ($type && !empty($_REQUEST['module'])) {
            if (isset($modules[$type]) && in_array($_REQUEST['module'], $modules[$type])) {
                $module = new $_REQUEST['module']();
                $module->init();
            }
        }

        if ($type && $module !== null && !empty($_REQUEST['do'])) {
            try {
                $module->process($_REQUEST['do']);
            } catch (Exception $e) {
                $core->error->add($e->getMessage());
            }
        }

        # Page setup
        $this
            ->setPageTitle(__('Import/Export'))
            ->setPageHelp('import')
            ->setPageHead(
                static::cssLoad('?mf=Plugin/ImportExport/files/style.css') .
                static::jsJson('ie_msg', ['please_wait' => __('Please wait...')]) .
                static::jsLoad('?mf=Plugin/ImportExport/files/js/script.js')
            )
        ;


        if ($type && $module !== null) {
            $this->setPageBreadcrumb([
                __('Plugins')                   => '',
                __('Import/Export')             => dotclear()->adminurl()->get('admin.plugin.ImportExport'),
                html::escapeHTML($module->name) => '',
            ]);

            # Keep old module style that echo gui
            ob_start();
            $module->gui();
            $content = ob_get_contents();
            ob_end_clean();

            $this->setPageContent(
                '<div id="ie-gui">' . $content . '</div>'
            );
        } else {
            $this->setPageBreadcrumb([
                __('Plugins')       => '',
                __('Import/Export') => '',
            ]);

            $this->setPageContent(
                '<h3>' . __('Import') . '</h3>' .
                self::listImportExportModules($modules['import']) .
                '<h3>' . __('Export') . '</h3>' .
                '<p class="info">' . sprintf(
                    __('Export functions are in the page %s.'),
                    '<a href="' . dotclear()->adminurl()->get('admin.plugin.Maintenance', ['tab' => 'backup']) . '#backup">' . __('Maintenance') . '</a>'
                ) . '</p>'
            );
        }

        return true;
    }

    //protected function getPageContent(): void { }

    private static function listImportExportModules($modules)
    {
        $res = '';
        foreach ($modules as $id) {
            $o = new $id();

            $res .= '<dt><a href="' . $o->getURL(true) . '">' . Html::escapeHTML($o->name) . '</a></dt>' .
            '<dd>' . Html::escapeHTML($o->description) . '</dd>';

            unset($o);
        }

        return '<dl class="modules">' . $res . '</dl>';
    }
}
