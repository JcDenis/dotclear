<?php
/**
 * @note Dotclear\Module\Plugin\Admin\HandlerPlugin
 * @brief Dotclear admin plugins page
 *
 * @ingroup  Module
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module\Plugin\Admin;

use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Module\AbstractPage;
use Exception;

class HandlerPlugin extends AbstractPage
{
    /** @var array freashly installed modules */
    private $modules_install = [];

    private $from_configuration = false;

    protected function getPermissions(): string|null|false
    {
        // Super admin
        return null;
    }

    protected function getPagePrepend(): ?bool
    {
        if (dotclear()->plugins()?->disableModulesDependencies(dotclear()->adminurl()->get('admin.plugins'))) {
            exit;
        }

        // Module configuration
        if (dotclear()->plugins()?->loadModuleConfiguration()) {
            dotclear()->plugins()->parseModuleConfiguration();

            // Page setup
            $this->setPageTitle(__('Plugins management'));
            $this->setPageHelp('core_plugins_conf');

            // --BEHAVIOR-- pluginsToolsHeaders
            $head = dotclear()->behavior()->call('pluginsToolsHeaders', true);
            if ($head) {
                $this->setPageHead($head);
            }
            $this->setPageBreadcrumb([
                Html::escapeHTML(dotclear()->blog()->name)                           => '',
                __('Plugins management')                                             => dotclear()->plugins()->getURL('', false),
                '<span class="page-title">' . __('Plugin configuration') . '</span>' => '',
            ]);

            // Stop reading code here
            $this->from_configuration = true;

        // Modules list
        } else {
            // -- Execute actions --
            try {
                dotclear()->plugins()->doActions();
            } catch (Exception $e) {
                dotclear()->error()->add($e->getMessage());
            }

            // -- Plugin install --
            if (!dotclear()->error()->flag()) {
                $this->modules_install = dotclear()->plugins()->installModules();
            }

            // Page setup
            $this
                ->setPageTitle(__('Plugins management'))
                ->setPageHelp('core_plugins')
                ->setPageHead(
                    dotclear()->resource()->load('_plugins.js') .
                    dotclear()->resource()->pageTabs() .

                    // --BEHAVIOR-- pluginsToolsHeaders
                    (string) dotclear()->behavior()->call('pluginsToolsHeaders', false)
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
                $info = implode(' - ', dotclear()->plugins()->getSettingsUrls($k, true));
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
            echo dotclear()->plugins()->displayModuleConfiguration();

            return;
        }

        // -- Display modules lists --
        if (dotclear()->user()->isSuperAdmin()) {
            if (!dotclear()->error()->flag()) {
                if (!empty($_GET['nocache'])) {
                    dotclear()->notice()->success(__('Manual checking of plugins update done successfully.'));
                }
            }

            // Updated modules from repo
            $modules = dotclear()->plugins()->store->get(true);
            if (!empty($modules)) {
                echo '<div class="multi-part" id="update" title="' . Html::escapeHTML(__('Update plugins')) . '">' .
                '<h3>' . Html::escapeHTML(__('Update plugins')) . '</h3>' .
                '<p>' . sprintf(
                    __('There is one plugin to update available from repository.', 'There are %s plugins to update available from repository.', count($modules)),
                    count($modules)
                ) . '</p>';

                dotclear()->plugins()
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
                echo '<form action="' . dotclear()->plugins()->getURL('', false) . '" method="get">' .
                '<p><input type="hidden" name="nocache" value="1" />' .
                '<input type="submit" value="' . __('Force checking update of plugins') . '" /></p>' .
                Form::hidden(['handler'], dotclear()->adminurl()->called()) .
                    '</form>';
            }
        }

        echo '<div class="multi-part" id="plugins" title="' . __('Installed plugins') . '">';

        // Activated modules
        $modules = dotclear()->plugins()->getModules();
        if (!empty($modules)) {
            echo '<h3>' . (dotclear()->user()->isSuperAdmin() ? __('Activated plugins') : __('Installed plugins')) . '</h3>' .
            '<p class="more-info">' . __('You can configure and manage installed plugins from this list.') . '</p>';

            dotclear()->plugins()
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
        if (dotclear()->user()->isSuperAdmin()) {
            $modules = dotclear()->plugins()->getDisabledModules();
            if (!empty($modules)) {
                echo '<h3>' . __('Deactivated plugins') . '</h3>' .
                '<p class="more-info">' . __('Deactivated plugins are installed but not usable. You can activate them from here.') . '</p>';

                dotclear()->plugins()
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

        if (dotclear()->user()->isSuperAdmin() && dotclear()->plugins()->isWritablePath()) {
            // New modules from repo
            $search  = dotclear()->plugins()->getSearch();
            $modules = $search ? dotclear()->plugins()->store->search($search) : dotclear()->plugins()->store->get();

            if (!empty($search) || !empty($modules)) {
                echo '<div class="multi-part" id="new" title="' . __('Add plugins') . '">' .
                '<h3>' . __('Add plugins from repository') . '</h3>';

                dotclear()->plugins()
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

            dotclear()->plugins()->displayManualForm();

            echo '</div>';
        }

        // --BEHAVIOR-- pluginsToolsTabs
        dotclear()->behavior()->call('pluginsToolsTabs');

        // -- Notice for super admin --
        if (dotclear()->user()->isSuperAdmin() && !dotclear()->plugins()->isWritablePath()) {
            echo '<p class="warning">' . __('Some functions are disabled, please give write access to your plugins directory to enable them.') . '</p>';
        }
    }
}
