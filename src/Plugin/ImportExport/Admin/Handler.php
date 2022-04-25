<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\ImportExport\Admin;

// Dotclear\Plugin\ImportExport\Admin\Handler
use ArrayObject;
use Dotclear\Helper\Html\Html;
use Dotclear\Module\AbstractPage;
use Exception;

/**
 * Admin page for plugin ImportExport.
 *
 * @ingroup  Plugin ImportExport
 */
class Handler extends AbstractPage
{
    protected function getPermissions(): string|null|false
    {
        return 'admin';
    }

    protected function getPagePrepend(): ?bool
    {
        $modules = new ArrayObject(['import' => [], 'export' => []]);

        // --BEHAVIOR-- importExportModules
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

        if ($type && null !== $module && !empty($_REQUEST['do'])) {
            try {
                $module->process($_REQUEST['do']);
            } catch (Exception $e) {
                dotclear()->error()->add($e->getMessage());
            }
        }

        // Page setup
        $this
            ->setPageTitle(__('Import/Export'))
            ->setPageHelp('import')
            ->setPageHead(
                dotclear()->resource()->load('style.css', 'Plugin', 'ImportExport') .
                dotclear()->resource()->json('ie_msg', ['please_wait' => __('Please wait...')]) .
                dotclear()->resource()->load('script.js', 'Plugin', 'ImportExport')
            )
        ;

        if ($type && null !== $module) {
            $this->setPageBreadcrumb([
                __('Plugins')                   => '',
                __('Import/Export')             => dotclear()->adminurl()->get('admin.plugin.ImportExport'),
                html::escapeHTML($module->name) => '',
            ]);

            // Keep old module style that echo gui
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
                $this->listImportExportModules($modules['import']) .
                '<h3>' . __('Export') . '</h3>' .
                '<p class="info">' . sprintf(
                    __('Export functions are in the page %s.'),
                    '<a href="' . dotclear()->adminurl()->get('admin.plugin.Maintenance', ['tab' => 'backup']) . '#backup">' . __('Maintenance') . '</a>'
                ) . '</p>'
            );
        }

        return true;
    }

    // protected function getPageContent(): void { }

    private function listImportExportModules($modules)
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
