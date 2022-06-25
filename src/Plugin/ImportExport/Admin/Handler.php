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
use Dotclear\App;
use Dotclear\Helper\GPC\GPC;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Mapper\Strings;
use Dotclear\Process\Admin\Page\AbstractPage;
use Exception;

/**
 * Admin page for plugin ImportExport.
 *
 * @ingroup  Plugin ImportExport
 */
class Handler extends AbstractPage
{
    protected function getPermissions(): string|bool
    {
        return 'admin';
    }

    protected function getPagePrepend(): ?bool
    {
        $import = new Strings();
        $export = new Strings();

        // --BEHAVIOR-- importExportModules
        App::core()->behavior('adminBeforeAddImportExportModules')->call(import: $import, export: $export);

        $modules = [
            'import' => $import->dump(),
            'export' => $export->dump(),
        ];

        $type = null;
        if (in_array(GPC::request()->string('type'), ['export', 'import'])) {
            $type = GPC::request()->string('type');
        }

        $module = null;
        if ($type && !GPC::request()->empty('module')) {
            if (isset($modules[$type]) && in_array(GPC::request()->string('module'), $modules[$type])) {
                $m      = GPC::request()->string('module');
                $module = new $m();
                $module->init();
            }
        }

        if ($type && null !== $module && !GPC::request()->empty('do')) {
            try {
                $module->process(GPC::request()->string('do'));
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
            }
        }

        // Page setup
        $this
            ->setPageTitle(__('Import/Export'))
            ->setPageHelp('import')
            ->setPageHead(
                App::core()->resource()->load('style.css', 'Plugin', 'ImportExport') .
                App::core()->resource()->json('ie_msg', ['please_wait' => __('Please wait...')]) .
                App::core()->resource()->load('script.js', 'Plugin', 'ImportExport')
            )
        ;

        if ($type && null !== $module) {
            $this->setPageBreadcrumb([
                __('Plugins')                   => '',
                __('Import/Export')             => App::core()->adminurl()->get('admin.plugin.ImportExport'),
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
                    '<a href="' . App::core()->adminurl()->get('admin.plugin.Maintenance', ['tab' => 'backup']) . '#backup">' . __('Maintenance') . '</a>'
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
