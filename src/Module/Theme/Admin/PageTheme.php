<?php
/**
 * @class Dotclear\Module\Theme\Admin\PageTheme
 * @brief Dotclear admin themes page
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module\Theme\Admin;

use Dotclear\Exception;

use Dotclear\Admin\Page;
use Dotclear\Admin\Notices;

use Dotclear\Module\Theme\Admin\ModulesTheme as Modules;

use Dotclear\Html\Html;
use Dotclear\Html\Form;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class PageTheme extends Page
{
    /** @var    array       freashly installed modules */
    private $modules_install = [];

    private $from_configuration = false;

    protected function getPermissions(): string|null|false
    {
        return 'admin';
    }

    protected function getPagePrepend(): ?bool
    {
        if ($this->core->themes->disableModulesDependencies($this->core->adminurl->get('admin.blog.theme'))) {
            exit;
        }

        # Module configuration
        if ($this->core->themes->loadModuleConfiguration()) {

            $this->core->themes->parseModuleConfiguration();

            # Page setup
            $this->setPageTitle(__('Blog appearance'));
            $this->setPageHelp('core_blog_theme_conf');

            # --BEHAVIOR-- themessToolsHeaders
            $head = $this->core->behaviors->call('themessToolsHeaders', true);
            if ($head) {
                $this->setPageHead($head);
            }
            $this->setPageBreadcrumb([
                Html::escapeHTML($this->core->blog->name)                            => '',
                __('Blog appearance')                                             => $this->core->themes->getURL('', false),
                '<span class="page-title">' . __('Theme configuration') . '</span>' => ''
            ]);

            # Stop reading code here
            $this->from_configuration = true;

        # Modules list
        } else {

            # -- Execute actions --
            try {
                $this->core->themes->doActions();
            } catch (Exception $e) {
                $this->core->themes->add($e->getMessage());
            }

            # -- Plugin install --
            $this->modules_install = null;
            if (!$this->core->error->flag()) {
                $this->modules_install = $this->core->themes->installModules();
            }

            # Page setup
            $this
                ->setPageTitle(__('Themes management'))
                ->setPageHelp('core_blog_theme')
                ->setPageHead(
                    static::jsLoad('js/_blog_theme.js') .
                    static::jsPageTabs() .

                    # --BEHAVIOR-- pluginsToolsHeaders
                    (string) $this->core->behaviors->call('themessToolsHeaders', false)
                )
                ->setPageBreadcrumb([
                        html::escapeHTML($this->core->blog->name)                       => '',
                        '<span class="page-title">' . __('Blog appearance') . '</span>' => '',
                ])
            ;
        }

        return true;
    }

    protected function getPageContent(): void
    {
        # -- Modules install messages --
        if (!empty($this->modules_install['success'])) {
            echo
            '<div class="static-msg">' . __('Following themes have been installed:') . '<ul>';

            foreach ($this->modules_install['success'] as $k => $v) {
                $info = implode(' - ', $this->core->themes->getSettingsUrls($k, true));
                echo
                    '<li>' . $k . ($info !== '' ? ' → ' . $info : '') . '</li>';
            }

            echo
                '</ul></div>';
        }
        if (!empty($this->modules_install['failure'])) {
            echo
            '<div class="error">' . __('Following themes have not been installed:') . '<ul>';

            foreach ($this->modules_install['failure'] as $k => $v) {
                echo
                    '<li>' . $k . ' (' . $v . ')</li>';
            }

            echo
                '</ul></div>';
        }

        if ($this->from_configuration) {
            echo $this->core->themes->displayModuleConfiguration();

            return;
        }

        # -- Display modules lists --
        if ($this->core->auth->isSuperAdmin()) {
            if (!$this->core->error->flag()) {
                if (!empty($_GET['nocache'])) {
                    $this->core->notices->success(__('Manual checking of themes update done successfully.'));
                }
            }

            # Updated modules from repo
            $modules = $this->core->themes->store->get(true);
            if (!empty($modules)) {
                echo
                '<div class="multi-part" id="update" title="' . Html::escapeHTML(__('Update themes')) . '">' .
                '<h3>' . Html::escapeHTML(__('Update themes')) . '</h3>' .
                '<p>' . sprintf(
                    __('There is one theme to update available from repository.', 'There are %s themes to update available from repository.', count($modules)),
                    count($modules)
                ) . '</p>';

                $this->core->themes
                    ->setList('theme-update')
                    ->setTab('update')
                    ->setData($modules)
                    ->displayData(
                        ['checkbox', 'name', 'screenshot', 'description', 'author', 'version', 'current_version', 'repository', 'parent'],
                        ['update', 'delete']
                    );

                echo
                '<p class="info vertical-separator">' . sprintf(
                    __('Visit %s repository, the resources center for Dotclear.'),
                    '<a href="https://themes.dotaddict.org/dc2/">Dotaddict</a>'
                ) .
                    '</p>' .

                    '</div>';
            } else {
                echo
                '<form action="' . $this->core->themes->getURL('', false) . '" method="get">' .
                '<p><input type="hidden" name="nocache" value="1" />' .
                '<input type="submit" value="' . __('Force checking update of themes') . '" /></p>' .
                Form::hidden('handler', $this->core->adminurl->called()) .
                    '</form>';
            }
        }

        # Activated modules
        $modules = $this->core->themes->getModules();
        if (!empty($modules)) {
            echo
            '<div class="multi-part" id="themes" title="' . __('Installed themes') . '">' .
            '<h3>' . __('Installed themes') . '</h3>' .
            '<p class="more-info">' . __('You can configure and manage installed themes from this list.') . '</p>';

            $this->core->themes
                ->setList('theme-activate')
                ->setTab('themes')
                ->setData($modules)
                ->displayData(
                    ['screenshot', 'distrib', 'name', 'config', 'description', 'author', 'version', 'parent'],
                    ['select', 'behavior', 'deactivate', 'clone', 'delete']
                );

            echo
                '</div>';
        }

        # Deactivated modules
        if ($this->core->auth->isSuperAdmin()) {
            $modules = $this->core->themes->getDisabledModules();
            if (!empty($modules)) {
                echo
                '<div class="multi-part" id="deactivate" title="' . __('Deactivated themes') . '">' .
                '<h3>' . __('Deactivated themes') . '</h3>' .
                '<p class="more-info">' . __('Deactivated themes are installed but not usable. You can activate them from here.') . '</p>';

                $this->core->themes
                    ->setList('theme-deactivate')
                    ->setTab('themes')
                    ->setData($modules)
                    ->displayData(
                        ['screenshot', 'name', 'distrib', 'description', 'author', 'version'],
                        ['activate', 'delete']
                    );

                echo
                    '</div>';
            }
        }

        if ($this->core->auth->isSuperAdmin() && $this->core->themes->isWritablePath()) {

            # New modules from repo
            $search  = $this->core->themes->getSearch();
            $modules = $search ? $this->core->themes->store->search($search) : $this->core->themes->store->get();

            if (!empty($search) || !empty($modules)) {
                echo
                '<div class="multi-part" id="new" title="' . __('Add themes') . '">' .
                '<h3>' . __('Add themes from repository') . '</h3>';

                $this->core->themes
                    ->setList('theme-new')
                    ->setTab('new')
                    ->setData($modules)
                    ->displaySearch()
                    ->displayIndex()
                    ->displayData(
                        ['expander', 'screenshot', 'name', 'score', 'config', 'description', 'author', 'version', 'parent', 'details', 'support'],
                        ['install'],
                        true
                    );

                echo
                '<p class="info vertical-separator">' . sprintf(
                    __('Visit %s repository, the resources center for Dotclear.'),
                    '<a href="https://themes.dotaddict.org/dc2/">Dotaddict</a>'
                ) .
                    '</p>' .

                    '</div>';
            }

            # Add a new module
            echo
            '<div class="multi-part" id="addtheme" title="' . __('Install or upgrade manually') . '">' .
            '<h3>' . __('Add themes from a package') . '</h3>' .
            '<p class="more-info">' . __('You can install themes by uploading or downloading zip files.') . '</p>';

            $this->core->themes->displayManualForm();

            echo
                '</div>';
        }

        # --BEHAVIOR-- themessToolsTabs
        $this->core->behaviors->call('themesToolsTabs');

        # -- Notice for super admin --
        if ($this->core->auth->isSuperAdmin() && !$this->core->themes->isWritablePath()) {
            echo
            '<p class="warning">' . __('Some functions are disabled, please give write access to your themes directory to enable them.') . '</p>';
        }
    }
}
