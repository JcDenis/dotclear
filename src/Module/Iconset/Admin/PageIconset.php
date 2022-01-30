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
            dcCore()->iconsets->doActions();
        } catch (Exception $e) {
            dcCore()->error->add($e->getMessage());
        }

        # -- Plugin install --
        $this->modules_install = null;
        if (!dcCore()->error->flag()) {
            $this->modules_install = dcCore()->iconsets->installModules();
        }

        # Page setup
        $this
            ->setPageTitle(__('Iconset management'))
            ->setPageHelp('core_iconset')
            ->setPageHead(
                static::jsLoad('js/_plugins.js') .
                static::jsPageTabs() .

                # --BEHAVIOR-- modulesToolsHeaders
                (string) dcCore()->behaviors->call('modulesToolsHeaders', false)
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
                $info = implode(' - ', dcCore()->iconsets->getSettingsUrls($k, true));
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
            echo dcCore()->iconsets->displayModuleConfiguration();

            return;
        }

        # -- Display modules lists --
        if (dcCore()->auth->isSuperAdmin()) {
            if (!dcCore()->error->flag()) {
                if (!empty($_GET['nocache'])) {
                    dcCore()->notices->success(__('Manual checking of modules update done successfully.'));
                }
            }

            # Updated modules from repo
            $modules = dcCore()->iconsets->store->get(true);
            if (!empty($modules)) {
                echo
                '<div class="multi-part" id="update" title="' . Html::escapeHTML(__('Update modules')) . '">' .
                '<h3>' . Html::escapeHTML(__('Update modules')) . '</h3>' .
                '<p>' . sprintf(
                    __('There is one module to update available from repository.', 'There are %s modules to update available from repository.', count($modules)),
                    count($modules)
                ) . '</p>';

                dcCore()->iconsets
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
                '<form action="' . dcCore()->iconsets->getURL('', false) . '" method="get">' .
                '<p><input type="hidden" name="nocache" value="1" />' .
                '<input type="submit" value="' . __('Force checking update of modules') . '" />' .
                Form::hidden('handler', dcCore()->adminurl->called()) .
                    '</form>';
            }
        }

        echo
        '<div class="multi-part" id="modules" title="' . __('Installed modules') . '">';

        # Activated modules
        $modules = dcCore()->iconsets->getModules();
        if (!empty($modules)) {
            echo
            '<h3>' . (dcCore()->auth->isSuperAdmin() ? __('Activated modules') : __('Installed modules')) . '</h3>' .
            '<p class="more-info">' . __('You can configure and manage installed modules from this list.') . '</p>';

            dcCore()->iconsets
                ->setList('module-activate')
                ->setTab('modules')
                ->setData($modules)
                ->displayData(
                    ['icon', 'name', 'author', 'version', 'description', 'distrib', 'deps'],
                    ['deactivate', 'delete', 'behavior']
                );
        }

        # Deactivated modules
        if (dcCore()->auth->isSuperAdmin()) {
            $modules = dcCore()->iconsets->getDisabledModules();
            if (!empty($modules)) {
                echo
                '<h3>' . __('Deactivated modules') . '</h3>' .
                '<p class="more-info">' . __('Deactivated modules are installed but not usable. You can activate them from here.') . '</p>';

                dcCore()->iconsets
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

        if (dcCore()->auth->isSuperAdmin() && dcCore()->iconsets->isWritablePath()) {

            # New modules from repo
            $search  = dcCore()->iconsets->getSearch();
            $modules = $search ? dcCore()->iconsets->store->search($search) : dcCore()->iconsets->store->get();

            if (!empty($search) || !empty($modules)) {
                echo
                '<div class="multi-part" id="new" title="' . __('Add modules') . '">' .
                '<h3>' . __('Add modules from repository') . '</h3>';

                dcCore()->iconsets
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

            dcCore()->iconsets->displayManualForm();

            echo
                '</div>';
        }

        # --BEHAVIOR-- modulesToolsTabs
        dcCore()->behaviors->call('modulesToolsTabs');

        # -- Notice for super admin --
        if (dcCore()->auth->isSuperAdmin() && !dcCore()->iconsets->isWritablePath()) {
            echo
            '<p class="warning">' . __('Some functions are disabled, please give write access to your modules directory to enable them.') . '</p>';
        }
    }
}
