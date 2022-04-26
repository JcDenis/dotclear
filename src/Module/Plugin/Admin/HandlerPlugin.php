<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module\Plugin\Admin;

// Dotclear\Module\Plugin\Admin\HandlerPlugin
use Dotclear\App;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Module\AbstractPage;
use Exception;

/**
 * Plugin modules admin page.
 *
 * @ingroup  Module Admin Plugin
 */
class HandlerPlugin extends AbstractPage
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
        if (App::core()->plugins()?->disableModulesDependencies(App::core()->adminurl()->get('admin.plugins'))) {
            exit;
        }

        // Module configuration
        if (App::core()->plugins()?->loadModuleConfiguration()) {
            App::core()->plugins()->parseModuleConfiguration();

            // Page setup
            $this->setPageTitle(__('Plugins management'));
            $this->setPageHelp('core_plugins_conf');

            // --BEHAVIOR-- pluginsToolsHeaders
            $head = App::core()->behavior()->call('pluginsToolsHeaders', true);
            if ($head) {
                $this->setPageHead($head);
            }
            $this->setPageBreadcrumb([
                Html::escapeHTML(App::core()->blog()->name)                           => '',
                __('Plugins management')                                              => App::core()->plugins()->getURL('', false),
                '<span class="page-title">' . __('Plugin configuration') . '</span>'  => '',
            ]);

            // Stop reading code here
            $this->from_configuration = true;

        // Modules list
        } else {
            // -- Execute actions --
            try {
                App::core()->plugins()->doActions();
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
            }

            // -- Plugin install --
            if (!App::core()->error()->flag()) {
                $this->modules_install = App::core()->plugins()->installModules();
            }

            // Page setup
            $this
                ->setPageTitle(__('Plugins management'))
                ->setPageHelp('core_plugins')
                ->setPageHead(
                    App::core()->resource()->load('_plugins.js') .
                    App::core()->resource()->pageTabs() .

                    // --BEHAVIOR-- pluginsToolsHeaders
                    (string) App::core()->behavior()->call('pluginsToolsHeaders', false)
                )
                ->setPageBreadcrumb([
                    __('System')             => '',
                    __('Plugins management') => '',
                ])
            ;
        }

        return true;
    }

    protected function getPageContent(): void
    {
        // -- Plugins install messages --
        if (!empty($this->modules_install['success'])) {
            echo '<div class="static-msg">' . __('Following plugins have been installed:') . '<ul>';

            foreach ($this->modules_install['success'] as $k => $v) {
                $info = implode(' - ', App::core()->plugins()->getSettingsUrls($k, true));
                echo '<li>' . $k . ('' !== $info ? ' → ' . $info : '') . '</li>';
            }

            echo '</ul></div>';
        }
        if (!empty($this->modules_install['failure'])) {
            echo '<div class="error">' . __('Following plugins have not been installed:') . '<ul>';

            foreach ($this->modules_install['failure'] as $k => $v) {
                echo '<li>' . $k . ' (' . $v . ')</li>';
            }

            echo '</ul></div>';
        }

        if ($this->from_configuration) {
            echo App::core()->plugins()->displayModuleConfiguration();

            return;
        }

        // -- Display modules lists --
        if (App::core()->user()->isSuperAdmin()) {
            if (!App::core()->error()->flag()) {
                if (!empty($_GET['nocache'])) {
                    App::core()->notice()->success(__('Manual checking of plugins update done successfully.'));
                }
            }

            // Updated modules from repo
            $modules = App::core()->plugins()->store->get(true);
            if (!empty($modules)) {
                echo '<div class="multi-part" id="update" title="' . Html::escapeHTML(__('Update plugins')) . '">' .
                '<h3>' . Html::escapeHTML(__('Update plugins')) . '</h3>' .
                '<p>' . sprintf(
                    __('There is one plugin to update available from repository.', 'There are %s plugins to update available from repository.', count($modules)),
                    count($modules)
                ) . '</p>';

                App::core()->plugins()
                    ->setList('plugin-update')
                    ->setTab('update')
                    ->setData($modules)
                    ->displayData(
                        ['checkbox', 'icon', 'name', 'version', 'repository', 'current_version', 'description'],
                        ['update']
                    )
                ;

                echo '<p class="info vertical-separator">' . sprintf(
                    __('Visit %s repository, the resources center for Dotclear.'),
                    '<a href="https://plugins.dotaddict.org/dc2/">Dotaddict</a>'
                ) .
                    '</p>' .

                    '</div>';
            } else {
                echo '<form action="' . App::core()->plugins()->getURL('', false) . '" method="get">' .
                '<p><input type="hidden" name="nocache" value="1" />' .
                '<input type="submit" value="' . __('Force checking update of plugins') . '" /></p>' .
                Form::hidden(['handler'], App::core()->adminurl()->called()) .
                    '</form>';
            }
        }

        echo '<div class="multi-part" id="plugins" title="' . __('Installed plugins') . '">';

        // Activated modules
        $modules = App::core()->plugins()->getModules();
        if (!empty($modules)) {
            echo '<h3>' . (App::core()->user()->isSuperAdmin() ? __('Activated plugins') : __('Installed plugins')) . '</h3>' .
            '<p class="more-info">' . __('You can configure and manage installed plugins from this list.') . '</p>';

            App::core()->plugins()
                ->setList('plugin-activate')
                ->setTab('plugins')
                ->setData($modules)
                ->displayData(
                    ['expander', 'icon', 'name', 'version', 'description', 'distrib', 'deps'],
                    ['deactivate', 'delete', 'behavior']
                )
            ;
        }

        // Deactivated modules
        if (App::core()->user()->isSuperAdmin()) {
            $modules = App::core()->plugins()->getDisabledModules();
            if (!empty($modules)) {
                echo '<h3>' . __('Deactivated plugins') . '</h3>' .
                '<p class="more-info">' . __('Deactivated plugins are installed but not usable. You can activate them from here.') . '</p>';

                App::core()->plugins()
                    ->setList('plugin-deactivate')
                    ->setTab('plugins')
                    ->setData($modules)
                    ->displayData(
                        ['expander', 'icon', 'name', 'version', 'description', 'distrib'],
                        ['activate', 'delete']
                    )
                ;
            }
        }

        echo '</div>';

        if (App::core()->user()->isSuperAdmin() && App::core()->plugins()->isWritablePath()) {
            // New modules from repo
            $search  = App::core()->plugins()->getSearch();
            $modules = $search ? App::core()->plugins()->store->search($search) : App::core()->plugins()->store->get();

            if (!empty($search) || !empty($modules)) {
                echo '<div class="multi-part" id="new" title="' . __('Add plugins') . '">' .
                '<h3>' . __('Add plugins from repository') . '</h3>';

                App::core()->plugins()
                    ->setList('plugin-new')
                    ->setTab('new')
                    ->setData($modules)
                    ->displaySearch()
                    ->displayIndex()
                    ->displayData(
                        ['expander', 'name', 'score', 'version', 'description', 'deps'],
                        ['install'],
                        true
                    )
                ;

                echo '<p class="info vertical-separator">' . sprintf(
                    __('Visit %s repository, the resources center for Dotclear.'),
                    '<a href="https://plugins.dotaddict.org/dc2/">Dotaddict</a>'
                ) .
                    '</p>' .

                    '</div>';
            }

            // Add a new plugin
            echo '<div class="multi-part" id="addplugin" title="' . __('Install or upgrade manually') . '">' .
            '<h3>' . __('Add plugins from a package') . '</h3>' .
            '<p class="more-info">' . __('You can install plugins by uploading or downloading zip files.') . '</p>';

            App::core()->plugins()->displayManualForm();

            echo '</div>';
        }

        // --BEHAVIOR-- pluginsToolsTabs
        App::core()->behavior()->call('pluginsToolsTabs');

        // -- Notice for super admin --
        if (App::core()->user()->isSuperAdmin() && !App::core()->plugins()->isWritablePath()) {
            echo '<p class="warning">' . __('Some functions are disabled, please give write access to your plugins directory to enable them.') . '</p>';
        }
    }
}
