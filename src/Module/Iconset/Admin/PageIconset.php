<?php
/**
 * @class Dotclear\Module\Iconset\Admin\PageIconset
 * @brief Dotclear admin icon set page
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module\Iconset\Admin;

use Dotclear\Exception;

use Dotclear\Admin\Page;
use Dotclear\Admin\Notices;

use Dotclear\Module\Iconset\Admin\ModulesIconset as Modules;

use Dotclear\Html\Html;
use Dotclear\Html\Form;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class PageIconset extends Page
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
        # -- Execute actions --
        try {
            $this->core->iconsets->doActions();
        } catch (Exception $e) {
            $this->core->error->add($e->getMessage());
        }

        # -- Plugin install --
        $this->modules_install = null;
        if (!$this->core->error->flag()) {
            $this->modules_install = $this->core->iconsets->installModules();
        }

        # Page setup
        $this
            ->setPageTitle(__('Iconset management'))
            ->setPageHelp('core_iconset')
            ->setPageHead(
                static::jsLoad('js/_plugins.js') .
                static::jsPageTabs() .

                # --BEHAVIOR-- modulesToolsHeaders
                (string) $this->core->behaviors->call('modulesToolsHeaders', false)
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
        # Modules install messages
        if (!empty($this->modules_install['success'])) {
            echo '<div class="static-msg">' . __('Following modules have been installed:') . '<ul>';

            foreach ($this->modules_install['success'] as $k => $v) {
                $info = implode(' - ', $this->core->iconsets->getSettingsUrls($k, true));
                echo '<li>' . $k . ($info !== '' ? ' → ' . $info : '') . '</li>';
            }

            echo '</ul></div>';
        }
        if (!empty($this->modules_install['failure'])) {
            echo
            '<div class="error">' . __('Following modules have not been installed:') . '<ul>';

            foreach ($this->modules_install['failure'] as $k => $v) {
                echo
                    '<li>' . $k . ' (' . $v . ')</li>';
            }

            echo
                '</ul></div>';
        }

        if ($this->from_configuration) {
            echo $this->core->iconsets->displayModuleConfiguration();

            return;
        }

        # -- Display modules lists --
        if ($this->core->auth->isSuperAdmin()) {
            if (!$this->core->error->flag()) {
                if (!empty($_GET['nocache'])) {
                    $this->core->notices->success(__('Manual checking of modules update done successfully.'));
                }
            }

            # Updated modules from repo
            $modules = $this->core->iconsets->store->get(true);
            if (!empty($modules)) {
                echo
                '<div class="multi-part" id="update" title="' . Html::escapeHTML(__('Update modules')) . '">' .
                '<h3>' . Html::escapeHTML(__('Update modules')) . '</h3>' .
                '<p>' . sprintf(
                    __('There is one module to update available from repository.', 'There are %s modules to update available from repository.', count($modules)),
                    count($modules)
                ) . '</p>';

                $this->core->iconsets
                    ->setList('module-update')
                    ->setTab('update')
                    ->setData($modules)
                    ->displayData(
                        /*cols */['checkbox', 'icon', 'name', 'author', 'version', 'repository', 'current_version', 'desc'],
                        /* actions */['update']
                    );

                echo
                '<p class="info vertical-separator">' . sprintf(
                    __('Visit %s repository, the resources center for Dotclear.'),
                    '<a href="https://iconset.dotaddict.org/dc2/">Dotaddict</a>'
                ) .
                    '</p>' .

                    '</div>';
            } else {
                echo
                '<form action="' . $this->core->iconsets->getURL('', false) . '" method="get">' .
                '<p><input type="hidden" name="nocache" value="1" />' .
                '<input type="submit" value="' . __('Force checking update of modules') . '" />' .
                Form::hidden('handler', $this->core->adminurl->called()) .
                    '</form>';
            }
        }

        echo
        '<div class="multi-part" id="modules" title="' . __('Installed modules') . '">';

        # Activated modules
        $modules = $this->core->iconsets->getModules();
        if (!empty($modules)) {
            echo
            '<h3>' . ($this->core->auth->isSuperAdmin() ? __('Activated modules') : __('Installed modules')) . '</h3>' .
            '<p class="more-info">' . __('You can configure and manage installed modules from this list.') . '</p>';

            $this->core->iconsets
                ->setList('module-activate')
                ->setTab('modules')
                ->setData($modules)
                ->displayData(
                    ['icon', 'name', 'author', 'version', 'description', 'distrib', 'deps'],
                    ['deactivate', 'delete', 'behavior']
                );
        }

        # Deactivated modules
        if ($this->core->auth->isSuperAdmin()) {
            $modules = $this->core->iconsets->getDisabledModules();
            if (!empty($modules)) {
                echo
                '<h3>' . __('Deactivated modules') . '</h3>' .
                '<p class="more-info">' . __('Deactivated modules are installed but not usable. You can activate them from here.') . '</p>';

                $this->core->iconsets
                    ->setList('module-deactivate')
                    ->setTab('modules')
                    ->setData($modules)
                    ->displayData(
                        ['icon', 'name', 'version', 'description', 'distrib'],
                        ['activate', 'delete']
                    );
            }
        }

        echo
            '</div>';

        if ($this->core->auth->isSuperAdmin() && $this->core->iconsets->isWritablePath()) {

            # New modules from repo
            $search  = $this->core->iconsets->getSearch();
            $modules = $search ? $this->core->iconsets->store->search($search) : $this->core->iconsets->store->get();

            if (!empty($search) || !empty($modules)) {
                echo
                '<div class="multi-part" id="new" title="' . __('Add modules') . '">' .
                '<h3>' . __('Add modules from repository') . '</h3>';

                $this->core->iconsets
                    ->setList('module-new')
                    ->setTab('new')
                    ->setData($modules)
                    ->displaySearch()
                    ->displayIndex()
                    ->displayData(
                        ['name', 'score', 'version', 'desc', 'deps'],
                        ['install'],
                        true
                    );

                echo
                '<p class="info vertical-separator">' . sprintf(
                    __('Visit %s repository, the resources center for Dotclear.'),
                    '<a href="https://iconset.dotaddict.org/dc2/">Dotaddict</a>'
                ) .
                    '</p>' .

                    '</div>';
            }

            # Add a new module
            echo
            '<div class="multi-part" id="addmodule" title="' . __('Install or upgrade manually') . '">' .
            '<h3>' . __('Add modules from a package') . '</h3>' .
            '<p class="more-info">' . __('You can install modules by uploading or downloading zip files.') . '</p>';

            $this->core->iconsets->displayManualForm();

            echo
                '</div>';
        }

        # --BEHAVIOR-- modulesToolsTabs
        $this->core->behaviors->call('modulesToolsTabs');

        # -- Notice for super admin --
        if ($this->core->auth->isSuperAdmin() && !$this->core->iconsets->isWritablePath()) {
            echo
            '<p class="warning">' . __('Some functions are disabled, please give write access to your modules directory to enable them.') . '</p>';
        }
    }
}
