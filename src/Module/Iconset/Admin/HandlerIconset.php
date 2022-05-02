<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module\Iconset\Admin;

// Dotclear\Module\Iconset\Admin\HandlerIconset
use Dotclear\App;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Process\Admin\Page\AbstractPage;
use Exception;

/**
 * Iconset modules admin page.
 *
 * @ingroup  Module Admin Iconset
 */
class HandlerIconset extends AbstractPage
{
    /**
     * @var array<string,array> $modules_install
     *                          Freashly installed modules
     */
    private $modules_install = [];

    /**
     * @var bool $from_configuration
     *           Use a configuration method
     */
    private $from_configuration = false;

    protected function getPermissions(): string|null|false
    {
        // Super admin
        return null;
    }

    protected function getPagePrepend(): ?bool
    {
        if (App::core()->iconsets()?->disableModulesDependencies(App::core()->adminurl()->get('admin.iconset'))) {
            exit;
        }
        // -- Execute actions --
        try {
            App::core()->iconsets()->doActions();
        } catch (Exception $e) {
            App::core()->error()->add($e->getMessage());
        }

        // -- Plugin install --
        if (!App::core()->error()->flag()) {
            $this->modules_install = App::core()->iconsets()->installModules();
        }

        // Page setup
        $this
            ->setPageTitle(__('Iconset management'))
            ->setPageHelp('core_iconset')
            ->setPageHead(
                App::core()->resource()->load('_plugins.js') .
                App::core()->resource()->pageTabs() .

                // --BEHAVIOR-- modulesToolsHeaders
                (string) App::core()->behavior()->call('modulesToolsHeaders', false)
            )
            ->setPageBreadcrumb([
                __('System')             => '',
                __('Iconset management') => '',
            ])
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        // Modules install messages
        if (!empty($this->modules_install['success'])) {
            echo '<div class="static-msg">' . __('Following modules have been installed:') . '<ul>';

            foreach ($this->modules_install['success'] as $k => $v) {
                $info = implode(' - ', App::core()->iconsets()->getSettingsUrls($k, true));
                echo '<li>' . $k . ('' !== $info ? ' → ' . $info : '') . '</li>';
            }

            echo '</ul></div>';
        }
        if (!empty($this->modules_install['failure'])) {
            echo '<div class="error">' . __('Following modules have not been installed:') . '<ul>';

            foreach ($this->modules_install['failure'] as $k => $v) {
                echo '<li>' . $k . ' (' . $v . ')</li>';
            }

            echo '</ul></div>';
        }

        if ($this->from_configuration) {
            echo App::core()->iconsets()->displayModuleConfiguration();

            return;
        }

        // -- Display modules lists --
        if (App::core()->user()->isSuperAdmin()) {
            if (!App::core()->error()->flag()) {
                if (!empty($_GET['nocache'])) {
                    App::core()->notice()->success(__('Manual checking of modules update done successfully.'));
                }
            }

            // Updated modules from repo
            $modules = App::core()->iconsets()->store->get(true);
            if (!empty($modules)) {
                echo '<div class="multi-part" id="update" title="' . Html::escapeHTML(__('Update modules')) . '">' .
                '<h3>' . Html::escapeHTML(__('Update modules')) . '</h3>' .
                '<p>' . sprintf(
                    __('There is one module to update available from repository.', 'There are %s modules to update available from repository.', count($modules)),
                    count($modules)
                ) . '</p>';

                App::core()->iconsets()
                    ->setList('module-update')
                    ->setTab('update')
                    ->setData($modules)
                    ->displayData(
                        // cols
                        ['checkbox', 'icon', 'name', 'author', 'version', 'repository', 'current_version', 'desc'],
                        // actions
                        ['update']
                    )
                ;

                echo '<p class="info vertical-separator">' . sprintf(
                    __('Visit %s repository, the resources center for Dotclear.'),
                    '<a href="https://iconset.dotaddict.org/dc2/">Dotaddict</a>'
                ) .
                    '</p>' .

                    '</div>';
            } else {
                echo '<form action="' . App::core()->iconsets()->getURL('', false) . '" method="get">' .
                '<p><input type="hidden" name="nocache" value="1" />' .
                '<input type="submit" value="' . __('Force checking update of modules') . '" />' .
                Form::hidden(['handler'], App::core()->adminurl()->called()) .
                    '</form>';
            }
        }

        echo '<div class="multi-part" id="modules" title="' . __('Installed modules') . '">';

        // Activated modules
        $modules = App::core()->iconsets()->getModules();
        if (!empty($modules)) {
            echo '<h3>' . (App::core()->user()->isSuperAdmin() ? __('Activated modules') : __('Installed modules')) . '</h3>' .
            '<p class="more-info">' . __('You can configure and manage installed modules from this list.') . '</p>';

            App::core()->iconsets()
                ->setList('module-activate')
                ->setTab('modules')
                ->setData($modules)
                ->displayData(
                    ['icon', 'name', 'author', 'version', 'description', 'distrib', 'deps'],
                    ['deactivate', 'delete', 'behavior']
                )
            ;
        }

        // Deactivated modules
        if (App::core()->user()->isSuperAdmin()) {
            $modules = App::core()->iconsets()->getDisabledModules();
            if (!empty($modules)) {
                echo '<h3>' . __('Deactivated modules') . '</h3>' .
                '<p class="more-info">' . __('Deactivated modules are installed but not usable. You can activate them from here.') . '</p>';

                App::core()->iconsets()
                    ->setList('module-deactivate')
                    ->setTab('modules')
                    ->setData($modules)
                    ->displayData(
                        ['icon', 'name', 'version', 'description', 'distrib'],
                        ['activate', 'delete']
                    )
                ;
            }
        }

        echo '</div>';

        if (App::core()->user()->isSuperAdmin() && App::core()->iconsets()->isWritablePath()) {
            // New modules from repo
            $search  = App::core()->iconsets()->getSearch();
            $modules = $search ? App::core()->iconsets()->store->search($search) : App::core()->iconsets()->store->get();

            if (!empty($search) || !empty($modules)) {
                echo '<div class="multi-part" id="new" title="' . __('Add modules') . '">' .
                '<h3>' . __('Add modules from repository') . '</h3>';

                App::core()->iconsets()
                    ->setList('module-new')
                    ->setTab('new')
                    ->setData($modules)
                    ->displaySearch()
                    ->displayIndex()
                    ->displayData(
                        ['name', 'score', 'version', 'desc', 'deps'],
                        ['install'],
                        true
                    )
                ;

                echo '<p class="info vertical-separator">' . sprintf(
                    __('Visit %s repository, the resources center for Dotclear.'),
                    '<a href="https://iconset.dotaddict.org/dc2/">Dotaddict</a>'
                ) .
                    '</p>' .

                    '</div>';
            }

            // Add a new module
            echo '<div class="multi-part" id="addmodule" title="' . __('Install or upgrade manually') . '">' .
            '<h3>' . __('Add modules from a package') . '</h3>' .
            '<p class="more-info">' . __('You can install modules by uploading or downloading zip files.') . '</p>';

            App::core()->iconsets()->displayManualForm();

            echo '</div>';
        }

        // --BEHAVIOR-- modulesToolsTabs
        App::core()->behavior()->call('modulesToolsTabs');

        // -- Notice for super admin --
        if (App::core()->user()->isSuperAdmin() && !App::core()->iconsets()->isWritablePath()) {
            echo '<p class="warning">' . __('Some functions are disabled, please give write access to your modules directory to enable them.') . '</p>';
        }
    }
}
