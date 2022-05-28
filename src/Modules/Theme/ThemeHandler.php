<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Modules\Theme;

// Dotclear\Modules\Theme\ThemeHandler
use Dotclear\App;
use Dotclear\Helper\GPC\GPC;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Process\Admin\Page\AbstractPage;
use Exception;

/**
 * Theme modules admin page.
 *
 * @ingroup  Module Admin Theme
 */
class ThemeHandler extends AbstractPage
{
    /**
     * @var ThemeList $m_list
     *                Modules list manager
     */
    private $m_list;

    /**
     * @var array<string,array> $m_installed
     *                          Freashly installed modules
     */
    private $m_installed = [];

    /**
     * @var bool $m_from_configuration
     *           Use a configuration method
     */
    private $m_from_configuration = false;

    // AbstractPage method
    protected function getPermissions(): string|bool
    {
        return 'admin';
    }

    // AbstractPage method
    protected function getPagePrepend(): ?bool
    {
        $this->m_list = new ThemeList(App::core()->themes());

        if ($this->m_list->modules()->disableDependencies(App::core()->adminurl()->get('admin.theme'))) {
            exit;
        }

        // Module configuration
        if ($this->m_list->loadModuleConfiguration()) {
            $this->m_list->parseModuleConfiguration();

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
                __('Blog appearance')                                                => $this->m_list->getURL('', false),
                '<span class="page-title">' . __('Theme configuration') . '</span>'  => '',
            ]);

            // Stop reading code here
            $this->m_from_configuration = true;

        // Modules list
        } else {
            // -- Execute actions --
            try {
                $this->m_list->doActions();
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
            }

            // -- Plugin install --
            if (!App::core()->error()->flag()) {
                $this->m_installed = $this->m_list->modules()->installModules();
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

    // AbstractPage method
    protected function getPageContent(): void
    {
        // -- Modules install messages --
        if (!empty($this->m_installed['success'])) {
            echo '<div class="static-msg">' . __('Following themes have been installed:') . '<ul>';

            foreach ($this->m_installed['success'] as $k => $v) {
                $info = implode(' - ', $this->m_list->modules()->getSettingsUrls($k, true));
                echo '<li>' . $k . ('' !== $info ? ' → ' . $info : '') . '</li>';
            }

            echo '</ul></div>';
        }
        if (!empty($this->m_installed['failure'])) {
            echo '<div class="error">' . __('Following themes have not been installed:') . '<ul>';

            foreach ($this->m_installed['failure'] as $k => $v) {
                echo '<li>' . $k . ' (' . $v . ')</li>';
            }

            echo '</ul></div>';
        }

        if ($this->m_from_configuration) {
            echo $this->m_list->displayModuleConfiguration();

            return;
        }

        // -- Display modules lists --
        if (App::core()->user()->isSuperAdmin()) {
            if (!App::core()->error()->flag()) {
                if (!GPC::get()->empty('nocache')) {
                    App::core()->notice()->success(__('Manual checking of themes update done successfully.'));
                }
            }

            // Updated modules from repo
            $modules = $this->m_list->modules()->store()->get(true);
            if (!empty($modules)) {
                echo '<div class="multi-part" id="update" title="' . Html::escapeHTML(__('Update themes')) . '">' .
                '<h3>' . Html::escapeHTML(__('Update themes')) . '</h3>' .
                '<p>' . sprintf(
                    __('There is one theme to update available from repository.', 'There are %s themes to update available from repository.', count($modules)),
                    count($modules)
                ) . '</p>';

                $this->m_list
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
                echo '<form action="' . $this->m_list->getURL('', false) . '" method="get">' .
                '<p><input type="hidden" name="nocache" value="1" />' .
                '<input type="submit" value="' . __('Force checking update of themes') . '" /></p>' .
                Form::hidden(['handler'], App::core()->adminurl()->called()) .
                    '</form>';
            }
        }

        // Activated modules
        $modules = $this->m_list->modules()->getModules();
        if (!empty($modules)) {
            echo '<div class="multi-part" id="themes" title="' . __('Installed themes') . '">' .
            '<h3>' . __('Installed themes') . '</h3>' .
            '<p class="more-info">' . __('You can configure and manage installed themes from this list.') . '</p>';

            $this->m_list
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
            $modules = $this->m_list->modules()->getDisabledModules();
            if (!empty($modules)) {
                echo '<div class="multi-part" id="deactivate" title="' . __('Deactivated themes') . '">' .
                '<h3>' . __('Deactivated themes') . '</h3>' .
                '<p class="more-info">' . __('Deactivated themes are installed but not usable. You can activate them from here.') . '</p>';

                $this->m_list
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

        if (App::core()->user()->isSuperAdmin() && $this->m_list->isWritablePath()) {
            // New modules from repo
            $search  = $this->m_list->getSearch();
            $modules = $search ? $this->m_list->modules()->store()->search($search) : $this->m_list->modules()->store()->get();

            if (!empty($search) || !empty($modules)) {
                echo '<div class="multi-part" id="new" title="' . __('Add themes') . '">' .
                '<h3>' . __('Add themes from repository') . '</h3>';

                $this->m_list
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

            $this->m_list->displayManualForm();

            echo '</div>';
        }

        // --BEHAVIOR-- themessToolsTabs
        App::core()->behavior()->call('themesToolsTabs');

        // -- Notice for super admin --
        if (App::core()->user()->isSuperAdmin() && !$this->m_list->isWritablePath()) {
            echo '<p class="warning">' . __('Some functions are disabled, please give write access to your themes directory to enable them.') . '</p>';
        }
    }
}
