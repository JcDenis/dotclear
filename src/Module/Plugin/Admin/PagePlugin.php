<?php
/**
 * @class Dotclear\Module\Plugin\Admin\PagePlugin
 * @brief Dotclear admin plugins page
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module\Plugin\Admin;

use Dotclear\Exception;

use Dotclear\Admin\Page;

use Dotclear\Module\Plugin\Admin\ModulesPlugin as Modules;

use Dotclear\Html\Html;
use Dotclear\Html\Form;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class PagePlugin extends Page
{
    /** @var    array       freashly installed modules */
    private $modules_install = [];

    private $from_configuration = false;

    protected function getPermissions(): string|null|false
    {
        # Super admin
        return null;
    }

    protected function getPagePrepend(): ?bool
    {
        if (dcCore()->plugins->disableModulesDependencies(dcCore()->adminurl->get('admin.plugins'))) {
            exit;
        }

        # Module configuration
        if (dcCore()->plugins->loadModuleConfiguration()) {

            dcCore()->plugins->parseModuleConfiguration();

            # Page setup
            $this->setPageTitle(__('Plugins management'));
            $this->setPageHelp('core_plugins_conf');

            # --BEHAVIOR-- pluginsToolsHeaders
            $head = dcCore()->behaviors->call('pluginsToolsHeaders', true);
            if ($head) {
                $this->setPageHead($head);
            }
            $this->setPageBreadcrumb([
                Html::escapeHTML(dcCore()->blog->name)                            => '',
                __('Plugins management')                                             => dcCore()->plugins->getURL('', false),
                '<span class="page-title">' . __('Plugin configuration') . '</span>' => ''
            ]);

            # Stop reading code here
            $this->from_configuration = true;

        # Modules list
        } else {

            # -- Execute actions --
            try {
                dcCore()->plugins->doActions();
            } catch (Exception $e) {
                dcCore()->error($e->getMessage());
            }

            # -- Plugin install --
            $this->modules_install = null;
            if (!dcCore()->error()->flag()) {
                $this->modules_install = dcCore()->plugins->installModules();
            }

            # Page setup
            $this
                ->setPageTitle(__('Plugins management'))
                ->setPageHelp('core_plugins')
                ->setPageHead(
                    static::jsLoad('js/_plugins.js') .
                    static::jsPageTabs() .

                    # --BEHAVIOR-- pluginsToolsHeaders
                    (string) dcCore()->behaviors->call('pluginsToolsHeaders', false)
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
        # -- Plugins install messages --
        if (!empty($this->modules_install['success'])) {
            echo
            '<div class="static-msg">' . __('Following plugins have been installed:') . '<ul>';

            foreach ($this->modules_install['success'] as $k => $v) {
                $info = implode(' - ', dcCore()->plugins->getSettingsUrls($k, true));
                echo
                    '<li>' . $k . ($info !== '' ? ' → ' . $info : '') . '</li>';
            }

            echo
                '</ul></div>';
        }
        if (!empty($this->modules_install['failure'])) {
            echo
            '<div class="error">' . __('Following plugins have not been installed:') . '<ul>';

            foreach ($this->modules_install['failure'] as $k => $v) {
                echo
                    '<li>' . $k . ' (' . $v . ')</li>';
            }

            echo
                '</ul></div>';
        }

        if ($this->from_configuration) {
            echo dcCore()->plugins->displayModuleConfiguration();

            return;
        }

        # -- Display modules lists --
        if (dcCore()->auth->isSuperAdmin()) {
            if (!dcCore()->error()->flag()) {
                if (!empty($_GET['nocache'])) {
                    dcCore()->notices->success(__('Manual checking of plugins update done successfully.'));
                }
            }

            # Updated modules from repo
            $modules = dcCore()->plugins->store->get(true);
            if (!empty($modules)) {
                echo
                '<div class="multi-part" id="update" title="' . Html::escapeHTML(__('Update plugins')) . '">' .
                '<h3>' . Html::escapeHTML(__('Update plugins')) . '</h3>' .
                '<p>' . sprintf(
                    __('There is one plugin to update available from repository.', 'There are %s plugins to update available from repository.', count($modules)),
                    count($modules)
                ) . '</p>';

                dcCore()->plugins
                    ->setList('plugin-update')
                    ->setTab('update')
                    ->setData($modules)
                    ->displayData(
                        ['checkbox', 'icon', 'name', 'version', 'repository', 'current_version', 'description'],
                        ['update']
                    );

                echo
                '<p class="info vertical-separator">' . sprintf(
                    __('Visit %s repository, the resources center for Dotclear.'),
                    '<a href="https://plugins.dotaddict.org/dc2/">Dotaddict</a>'
                ) .
                    '</p>' .

                    '</div>';
            } else {
                echo
                '<form action="' . dcCore()->plugins->getURL('', false) . '" method="get">' .
                '<p><input type="hidden" name="nocache" value="1" />' .
                '<input type="submit" value="' . __('Force checking update of plugins') . '" /></p>' .
                Form::hidden('handler', dcCore()->adminurl->called()) .
                    '</form>';
            }
        }

        echo
        '<div class="multi-part" id="plugins" title="' . __('Installed plugins') . '">';

        # Activated modules
        $modules = dcCore()->plugins->getModules();
        if (!empty($modules)) {
            echo
            '<h3>' . (dcCore()->auth->isSuperAdmin() ? __('Activated plugins') : __('Installed plugins')) . '</h3>' .
            '<p class="more-info">' . __('You can configure and manage installed plugins from this list.') . '</p>';

            dcCore()->plugins
                ->setList('plugin-activate')
                ->setTab('plugins')
                ->setData($modules)
                ->displayData(
                    ['expander', 'icon', 'name', 'version', 'description', 'distrib', 'deps'],
                    ['deactivate', 'delete', 'behavior']
                );
        }

        # Deactivated modules
        if (dcCore()->auth->isSuperAdmin()) {
            $modules = dcCore()->plugins->getDisabledModules();
            if (!empty($modules)) {
                echo
                '<h3>' . __('Deactivated plugins') . '</h3>' .
                '<p class="more-info">' . __('Deactivated plugins are installed but not usable. You can activate them from here.') . '</p>';

                dcCore()->plugins
                    ->setList('plugin-deactivate')
                    ->setTab('plugins')
                    ->setData($modules)
                    ->displayData(
                        ['expander', 'icon', 'name', 'version', 'description', 'distrib'],
                        ['activate', 'delete']
                    );
            }
        }

        echo
            '</div>';

        if (dcCore()->auth->isSuperAdmin() && dcCore()->plugins->isWritablePath()) {

            # New modules from repo
            $search  = dcCore()->plugins->getSearch();
            $modules = $search ? dcCore()->plugins->store->search($search) : dcCore()->plugins->store->get();

            if (!empty($search) || !empty($modules)) {
                echo
                '<div class="multi-part" id="new" title="' . __('Add plugins') . '">' .
                '<h3>' . __('Add plugins from repository') . '</h3>';

                dcCore()->plugins
                    ->setList('plugin-new')
                    ->setTab('new')
                    ->setData($modules)
                    ->displaySearch()
                    ->displayIndex()
                    ->displayData(
                        ['expander', 'name', 'score', 'version', 'description', 'deps'],
                        ['install'],
                        true
                    );

                echo
                '<p class="info vertical-separator">' . sprintf(
                    __('Visit %s repository, the resources center for Dotclear.'),
                    '<a href="https://plugins.dotaddict.org/dc2/">Dotaddict</a>'
                ) .
                    '</p>' .

                    '</div>';
            }

            # Add a new plugin
            echo
            '<div class="multi-part" id="addplugin" title="' . __('Install or upgrade manually') . '">' .
            '<h3>' . __('Add plugins from a package') . '</h3>' .
            '<p class="more-info">' . __('You can install plugins by uploading or downloading zip files.') . '</p>';

            dcCore()->plugins->displayManualForm();

            echo
                '</div>';
        }

        # --BEHAVIOR-- pluginsToolsTabs
        dcCore()->behaviors->call('pluginsToolsTabs');

        # -- Notice for super admin --
        if (dcCore()->auth->isSuperAdmin() && !dcCore()->plugins->isWritablePath()) {
            echo
            '<p class="warning">' . __('Some functions are disabled, please give write access to your plugins directory to enable them.') . '</p>';
        }
    }
}