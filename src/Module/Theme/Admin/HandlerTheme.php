<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module\Theme\Admin;

// Dotclear\Module\Theme\Admin\HandlerTheme
use Dotclear\App;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Module\AbstractPage;
use Exception;

/**
 * Theme modules admin page.
 *
 * @ingroup  Module Admin Theme
 */
class HandlerTheme extends AbstractPage
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
        return 'admin';
    }

    protected function getPagePrepend(): ?bool
    {
        if (App::core()->themes()?->disableModulesDependencies(App::core()->adminurl()->get('admin.blog.theme'))) {
            exit;
        }

        // Module configuration
        if (App::core()->themes()?->loadModuleConfiguration()) {
            App::core()->themes()->parseModuleConfiguration();

            // Page setup
            $this->setPageTitle(__('Blog appearance'));
            $this->setPageHelp('core_blog_theme_conf');

            // --BEHAVIOR-- themessToolsHeaders
            $head = App::core()->behavior()->call('themessToolsHeaders', true);
            if ($head) {
                $this->setPageHead($head);
            }
            $this->setPageBreadcrumb([
                Html::escapeHTML(App::core()->blog()->name)                          => '',
                __('Blog appearance')                                                => App::core()->themes()->getURL('', false),
                '<span class="page-title">' . __('Theme configuration') . '</span>'  => '',
            ]);

            // Stop reading code here
            $this->from_configuration = true;

        // Modules list
        } else {
            // -- Execute actions --
            try {
                App::core()->themes()->doActions();
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
            }

            // -- Plugin install --
            if (!App::core()->error()->flag()) {
                $this->modules_install = App::core()->themes()->installModules();
            }

            // Page setup
            $this
                ->setPageTitle(__('Themes management'))
                ->setPageHelp('core_blog_theme')
                ->setPageHead(
                    App::core()->resource()->load('_blog_theme.js') .
                    App::core()->resource()->pageTabs() .

                    // --BEHAVIOR-- pluginsToolsHeaders
                    (string) App::core()->behavior()->call('themesToolsHeaders', false)
                )
                ->setPageBreadcrumb([
                    Html::escapeHTML(App::core()->blog()->name)                      => '',
                    '<span class="page-title">' . __('Blog appearance') . '</span>'  => '',
                ])
            ;
        }

        return true;
    }

    protected function getPageContent(): void
    {
        // -- Modules install messages --
        if (!empty($this->modules_install['success'])) {
            echo '<div class="static-msg">' . __('Following themes have been installed:') . '<ul>';

            foreach ($this->modules_install['success'] as $k => $v) {
                $info = implode(' - ', App::core()->themes()->getSettingsUrls($k, true));
                echo '<li>' . $k . ('' !== $info ? ' → ' . $info : '') . '</li>';
            }

            echo '</ul></div>';
        }
        if (!empty($this->modules_install['failure'])) {
            echo '<div class="error">' . __('Following themes have not been installed:') . '<ul>';

            foreach ($this->modules_install['failure'] as $k => $v) {
                echo '<li>' . $k . ' (' . $v . ')</li>';
            }

            echo '</ul></div>';
        }

        if ($this->from_configuration) {
            echo App::core()->themes()->displayModuleConfiguration();

            return;
        }

        // -- Display modules lists --
        if (App::core()->user()->isSuperAdmin()) {
            if (!App::core()->error()->flag()) {
                if (!empty($_GET['nocache'])) {
                    App::core()->notice()->success(__('Manual checking of themes update done successfully.'));
                }
            }

            // Updated modules from repo
            $modules = App::core()->themes()->store->get(true);
            if (!empty($modules)) {
                echo '<div class="multi-part" id="update" title="' . Html::escapeHTML(__('Update themes')) . '">' .
                '<h3>' . Html::escapeHTML(__('Update themes')) . '</h3>' .
                '<p>' . sprintf(
                    __('There is one theme to update available from repository.', 'There are %s themes to update available from repository.', count($modules)),
                    count($modules)
                ) . '</p>';

                App::core()->themes()
                    ->setList('theme-update')
                    ->setTab('update')
                    ->setData($modules)
                    ->displayData(
                        ['checkbox', 'name', 'screenshot', 'description', 'author', 'version', 'current_version', 'repository', 'parent'],
                        ['update', 'delete']
                    )
                ;

                echo '<p class="info vertical-separator">' . sprintf(
                    __('Visit %s repository, the resources center for Dotclear.'),
                    '<a href="https://themes.dotaddict.org/dc2/">Dotaddict</a>'
                ) .
                    '</p>' .

                    '</div>';
            } else {
                echo '<form action="' . App::core()->themes()->getURL('', false) . '" method="get">' .
                '<p><input type="hidden" name="nocache" value="1" />' .
                '<input type="submit" value="' . __('Force checking update of themes') . '" /></p>' .
                Form::hidden(['handler'], App::core()->adminurl()->called()) .
                    '</form>';
            }
        }

        // Activated modules
        $modules = App::core()->themes()->getModules();
        if (!empty($modules)) {
            echo '<div class="multi-part" id="themes" title="' . __('Installed themes') . '">' .
            '<h3>' . __('Installed themes') . '</h3>' .
            '<p class="more-info">' . __('You can configure and manage installed themes from this list.') . '</p>';

            App::core()->themes()
                ->setList('theme-activate')
                ->setTab('themes')
                ->setData($modules)
                ->displayData(
                    ['screenshot', 'distrib', 'name', 'config', 'description', 'author', 'version', 'parent'],
                    ['select', 'behavior', 'deactivate', 'clone', 'delete']
                )
            ;

            echo '</div>';
        }

        // Deactivated modules
        if (App::core()->user()->isSuperAdmin()) {
            $modules = App::core()->themes()->getDisabledModules();
            if (!empty($modules)) {
                echo '<div class="multi-part" id="deactivate" title="' . __('Deactivated themes') . '">' .
                '<h3>' . __('Deactivated themes') . '</h3>' .
                '<p class="more-info">' . __('Deactivated themes are installed but not usable. You can activate them from here.') . '</p>';

                App::core()->themes()
                    ->setList('theme-deactivate')
                    ->setTab('themes')
                    ->setData($modules)
                    ->displayData(
                        ['screenshot', 'name', 'distrib', 'description', 'author', 'version'],
                        ['activate', 'delete']
                    )
                ;

                echo '</div>';
            }
        }

        if (App::core()->user()->isSuperAdmin() && App::core()->themes()->isWritablePath()) {
            // New modules from repo
            $search  = App::core()->themes()->getSearch();
            $modules = $search ? App::core()->themes()->store->search($search) : App::core()->themes()->store->get();

            if (!empty($search) || !empty($modules)) {
                echo '<div class="multi-part" id="new" title="' . __('Add themes') . '">' .
                '<h3>' . __('Add themes from repository') . '</h3>';

                App::core()->themes()
                    ->setList('theme-new')
                    ->setTab('new')
                    ->setData($modules)
                    ->displaySearch()
                    ->displayIndex()
                    ->displayData(
                        ['expander', 'screenshot', 'name', 'score', 'config', 'description', 'author', 'version', 'parent', 'details', 'support'],
                        ['install'],
                        true
                    )
                ;

                echo '<p class="info vertical-separator">' . sprintf(
                    __('Visit %s repository, the resources center for Dotclear.'),
                    '<a href="https://themes.dotaddict.org/dc2/">Dotaddict</a>'
                ) .
                    '</p>' .

                    '</div>';
            }

            // Add a new module
            echo '<div class="multi-part" id="addtheme" title="' . __('Install or upgrade manually') . '">' .
            '<h3>' . __('Add themes from a package') . '</h3>' .
            '<p class="more-info">' . __('You can install themes by uploading or downloading zip files.') . '</p>';

            App::core()->themes()->displayManualForm();

            echo '</div>';
        }

        // --BEHAVIOR-- themessToolsTabs
        App::core()->behavior()->call('themesToolsTabs');

        // -- Notice for super admin --
        if (App::core()->user()->isSuperAdmin() && !App::core()->themes()->isWritablePath()) {
            echo '<p class="warning">' . __('Some functions are disabled, please give write access to your themes directory to enable them.') . '</p>';
        }
    }
}
