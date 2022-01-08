<?php
/**
 * @class Dotclear\Admin\Page\Plugins
 * @brief Dotclear admin plugins page
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin\Page;

use Dotclear\Exception;

use Dotclear\Admin\Page;
use Dotclear\Admin\Notices;
use Dotclear\Admin\Modules;

use Dotclear\Html\Html;
use Dotclear\Html\Form;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Plugins extends Page
{
    /** @var    Modules     Modules list instance */
    private $list;

    /** @var    array       freashly installed modules */
    private $plugins_install = [];

    private $from_configuration = false;

    protected function getPermissions(): string|null|false
    {
        # Super admin
        return null;
    }

    protected function getPagePrepend(): ?bool
    {
        # -- Page helper --
        $this->list = new Modules(
            $this->core->plugins,
            DOTCLEAR_PLUGIN_DIR,
            (string) $this->core->blog->settings->system->store_plugin_url,
            !empty($_GET['nocache'])
        );

        Modules::$allow_multi_install = (bool) DOTCLEAR_ALLOW_MULTI_MODULES;
        Modules::$distributed_modules = explode(',', DOTCLEAR_DISTRIBUTED_PLUGINS);

        if ($this->core->plugins->disableDepModules($this->core->adminurl->get('admin.plugins'))) {
            exit;
        }

        # Module configuration
        if ($this->list->loadModuleConfiguration()) {

            $this->list->parseModuleConfiguration();

            # Page setup
            $this->setPageTitle(__('Plugins management'));
            $this->setPageHelp('core_plugins_conf');

            # --BEHAVIOR-- pluginsToolsHeaders
            $head = $this->core->behaviors->call('pluginsToolsHeaders', true);
            if ($head) {
                $this->setPageHead($head);
            }
            $this->setPageBreadcrumb([
                Html::escapeHTML($this->core->blog->name)                            => '',
                __('Plugins management')                                             => $this->list->getURL('', false),
                '<span class="page-title">' . __('Plugin configuration') . '</span>' => ''
            ]);

            # Stop reading code here
            $this->from_configuration = true;

        # Modules list
        } else {

    /*
            # -- Execute actions --
            try {
                $this->list->doActions();
            } catch (Exception $e) {
                $this->core->error->add($e->getMessage());
            }

            # -- Plugin install --
            $this->plugins_install = null;
            if (!$this->core->error->flag()) {
                $this->plugins_install = $this->core->plugins->installModules();
            }
    */
            # Page setup
            $this
                ->setPageTitle(__('Plugins management'))
                ->setPageHelp('core_plugins')
                ->setPageHead(
                    static::jsLoad('js/_plugins.js') .
                    static::jsPageTabs() .

                    # --BEHAVIOR-- pluginsToolsHeaders
                    (string) $this->core->behaviors->call('pluginsToolsHeaders', false)
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
        if (!empty($this->plugins_install['success'])) {
            echo
            '<div class="static-msg">' . __('Following plugins have been installed:') . '<ul>';

            foreach ($this->plugins_install['success'] as $k => $v) {
                $info = implode(' - ', $this->list->getSettingsUrls($this->core, $k, true));
                echo
                    '<li>' . $k . ($info !== '' ? ' → ' . $info : '') . '</li>';
            }

            echo
                '</ul></div>';
        }
        if (!empty($this->plugins_install['failure'])) {
            echo
            '<div class="error">' . __('Following plugins have not been installed:') . '<ul>';

            foreach ($this->plugins_install['failure'] as $k => $v) {
                echo
                    '<li>' . $k . ' (' . $v . ')</li>';
            }

            echo
                '</ul></div>';
        }

        if ($this->from_configuration) {
            echo Notices::getNotices() . $this->list->displayModuleConfiguration();

            return;
        }

        # -- Display modules lists --
        if ($this->core->auth->isSuperAdmin()) {
            if (!$this->core->error->flag()) {
                if (!empty($_GET['nocache'])) {
                    dcPage::success(__('Manual checking of plugins update done successfully.'));
                }
            }

            # Updated modules from repo
            $modules = $this->list->store->get(true);
            if (!empty($modules)) {
                echo
                '<div class="multi-part" id="update" title="' . Html::escapeHTML(__('Update plugins')) . '">' .
                '<h3>' . Html::escapeHTML(__('Update plugins')) . '</h3>' .
                '<p>' . sprintf(
                    __('There is one plugin to update available from repository.', 'There are %s plugins to update available from repository.', count($modules)),
                    count($modules)
                ) . '</p>';

                $this->list
                    ->setList('plugin-update')
                    ->setTab('update')
                    ->setModules($modules)
                    ->displayModules(
                        /*cols */['checkbox', 'icon', 'name', 'version', 'repository', 'current_version', 'desc'],
                        /* actions */['update']
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
                '<form action="' . $this->list->getURL('', false) . '" method="get">' .
                '<p><input type="hidden" name="nocache" value="1" />' .
                '<input type="submit" value="' . __('Force checking update of plugins') . '" /></p>' .
                    '</form>';
            }
        }

        echo
        '<div class="multi-part" id="plugins" title="' . __('Installed plugins') . '">';

        # Activated modules
        $modules = $this->list->modules->getModules();
        if (!empty($modules)) {
            echo
            '<h3>' . ($this->core->auth->isSuperAdmin() ? __('Activated plugins') : __('Installed plugins')) . '</h3>' .
            '<p class="more-info">' . __('You can configure and manage installed plugins from this list.') . '</p>';

            $this->list
                ->setList('plugin-activate')
                ->setTab('plugins')
                ->setModules($modules)
                ->displayModules(
                    /* cols */['expander', 'icon', 'name', 'version', 'desc', 'distrib', 'deps'],
                    /* actions */['deactivate', 'delete', 'behavior']
                );
        }

        # Deactivated modules
        if ($this->core->auth->isSuperAdmin()) {
            $modules = $this->list->modules->getDisabledModules();
            if (!empty($modules)) {
                echo
                '<h3>' . __('Deactivated plugins') . '</h3>' .
                '<p class="more-info">' . __('Deactivated plugins are installed but not usable. You can activate them from here.') . '</p>';

                $this->list
                    ->setList('plugin-deactivate')
                    ->setTab('plugins')
                    ->setModules($modules)
                    ->displayModules(
                        /* cols */['expander', 'icon', 'name', 'version', 'desc', 'distrib'],
                        /* actions */['activate', 'delete']
                    );
            }
        }

        echo
            '</div>';

        if ($this->core->auth->isSuperAdmin() && $this->list->isWritablePath()) {

            # New modules from repo
            $search  = $this->list->getSearch();
            $modules = $search ? $this->list->store->search($search) : $this->list->store->get();

            if (!empty($search) || !empty($modules)) {
                echo
                '<div class="multi-part" id="new" title="' . __('Add plugins') . '">' .
                '<h3>' . __('Add plugins from repository') . '</h3>';

                $this->list
                    ->setList('plugin-new')
                    ->setTab('new')
                    ->setModules($modules)
                    ->displaySearch()
                    ->displayIndex()
                    ->displayModules(
                        /* cols */['expander', 'name', 'score', 'version', 'desc', 'deps'],
                        /* actions */['install'],
                        /* nav limit */true
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

            $this->list->displayManualForm();

            echo
                '</div>';
        }

        # --BEHAVIOR-- pluginsToolsTabs
        $this->core->behaviors->call('pluginsToolsTabs');

        # -- Notice for super admin --
        if ($this->core->auth->isSuperAdmin() && !$this->list->isWritablePath()) {
            echo
            '<p class="warning">' . __('Some functions are disabled, please give write access to your plugins directory to enable them.') . '</p>';
        }
    }
}
