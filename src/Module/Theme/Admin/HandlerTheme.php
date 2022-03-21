<?php
/**
 * @class Dotclear\Module\Theme\Admin\HandlerTheme
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

use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Module\AbstractPage;

class HandlerTheme extends AbstractPage
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
        if (dotclear()->themes->disableModulesDependencies(dotclear()->adminurl()->get('admin.blog.theme'))) {
            exit;
        }

        # Module configuration
        if (dotclear()->themes->loadModuleConfiguration()) {

            dotclear()->themes->parseModuleConfiguration();

            # Page setup
            $this->setPageTitle(__('Blog appearance'));
            $this->setPageHelp('core_blog_theme_conf');

            # --BEHAVIOR-- themessToolsHeaders
            $head = dotclear()->behavior()->call('themessToolsHeaders', true);
            if ($head) {
                $this->setPageHead($head);
            }
            $this->setPageBreadcrumb([
                Html::escapeHTML(dotclear()->blog()->name)                            => '',
                __('Blog appearance')                                             => dotclear()->themes->getURL('', false),
                '<span class="page-title">' . __('Theme configuration') . '</span>' => ''
            ]);

            # Stop reading code here
            $this->from_configuration = true;

        # Modules list
        } else {

            # -- Execute actions --
            try {
                dotclear()->themes->doActions();
            } catch (\Exception $e) {
                dotclear()->themes->add($e->getMessage());
            }

            # -- Plugin install --
            $this->modules_install = null;
            if (!dotclear()->error()->flag()) {
                $this->modules_install = dotclear()->themes->installModules();
            }

            # Page setup
            $this
                ->setPageTitle(__('Themes management'))
                ->setPageHelp('core_blog_theme')
                ->setPageHead(
                    dotclear()->resource()->load('_blog_theme.js') .
                    dotclear()->resource()->pageTabs() .

                    # --BEHAVIOR-- pluginsToolsHeaders
                    (string) dotclear()->behavior()->call('themesToolsHeaders', false)
                )
                ->setPageBreadcrumb([
                        Html::escapeHTML(dotclear()->blog()->name)                       => '',
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
                $info = implode(' - ', dotclear()->themes->getSettingsUrls($k, true));
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
            echo dotclear()->themes->displayModuleConfiguration();

            return;
        }

        # -- Display modules lists --
        if (dotclear()->user()->isSuperAdmin()) {
            if (!dotclear()->error()->flag()) {
                if (!empty($_GET['nocache'])) {
                    dotclear()->notice()->success(__('Manual checking of themes update done successfully.'));
                }
            }

            # Updated modules from repo
            $modules = dotclear()->themes->store->get(true);
            if (!empty($modules)) {
                echo
                '<div class="multi-part" id="update" title="' . Html::escapeHTML(__('Update themes')) . '">' .
                '<h3>' . Html::escapeHTML(__('Update themes')) . '</h3>' .
                '<p>' . sprintf(
                    __('There is one theme to update available from repository.', 'There are %s themes to update available from repository.', count($modules)),
                    count($modules)
                ) . '</p>';

                dotclear()->themes
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
                '<form action="' . dotclear()->themes->getURL('', false) . '" method="get">' .
                '<p><input type="hidden" name="nocache" value="1" />' .
                '<input type="submit" value="' . __('Force checking update of themes') . '" /></p>' .
                Form::hidden(['handler'], dotclear()->adminurl()->called()) .
                    '</form>';
            }
        }

        # Activated modules
        $modules = dotclear()->themes->getModules();
        if (!empty($modules)) {
            echo
            '<div class="multi-part" id="themes" title="' . __('Installed themes') . '">' .
            '<h3>' . __('Installed themes') . '</h3>' .
            '<p class="more-info">' . __('You can configure and manage installed themes from this list.') . '</p>';

            dotclear()->themes
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
        if (dotclear()->user()->isSuperAdmin()) {
            $modules = dotclear()->themes->getDisabledModules();
            if (!empty($modules)) {
                echo
                '<div class="multi-part" id="deactivate" title="' . __('Deactivated themes') . '">' .
                '<h3>' . __('Deactivated themes') . '</h3>' .
                '<p class="more-info">' . __('Deactivated themes are installed but not usable. You can activate them from here.') . '</p>';

                dotclear()->themes
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

        if (dotclear()->user()->isSuperAdmin() && dotclear()->themes->isWritablePath()) {

            # New modules from repo
            $search  = dotclear()->themes->getSearch();
            $modules = $search ? dotclear()->themes->store->search($search) : dotclear()->themes->store->get();

            if (!empty($search) || !empty($modules)) {
                echo
                '<div class="multi-part" id="new" title="' . __('Add themes') . '">' .
                '<h3>' . __('Add themes from repository') . '</h3>';

                dotclear()->themes
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

            dotclear()->themes->displayManualForm();

            echo
                '</div>';
        }

        # --BEHAVIOR-- themessToolsTabs
        dotclear()->behavior()->call('themesToolsTabs');

        # -- Notice for super admin --
        if (dotclear()->user()->isSuperAdmin() && !dotclear()->themes->isWritablePath()) {
            echo
            '<p class="warning">' . __('Some functions are disabled, please give write access to your themes directory to enable them.') . '</p>';
        }
    }
}
