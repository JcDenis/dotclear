<?php
/**
 * @package Dotclear
 * @subpackage Backend
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */

use Dotclear\Helper\File\Files;
use Dotclear\Helper\File\Path;
use Dotclear\Helper\Html\Form\Form;
use Dotclear\Helper\Html\Form\Hidden;
use Dotclear\Helper\Html\Form\Para;
use Dotclear\Helper\Html\Form\Submit;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Http;
use Dotclear\Module\Define;
use Dotclear\Module\Themes;

require __DIR__ . '/../inc/admin/prepend.php';

class adminBlogTheme
{
    /**
     * Initializes the page.
     *
     * @return     bool  True if we must return immediatly
     */
    public static function init(): bool
    {
        dcPage::check(dcCore::app()->auth->makePermissions([
            dcAuth::PERMISSION_ADMIN,
        ]));

        // Loading themes
        dcCore::app()->themes = new Themes();
        dcCore::app()->themes->loadModules(dcCore::app()->blog->themes_path, null);

        // Page helper
        dcCore::app()->admin->list = new adminThemesList(
            dcCore::app()->themes,
            dcCore::app()->blog->themes_path,
            dcCore::app()->blog->settings->system->store_theme_url,
            !empty($_GET['nocache'])
        );
        // deprecated since 2.26
        adminThemesList::$distributed_modules = explode(',', DC_DISTRIB_THEMES);

        if (dcCore::app()->themes->disableDepModules(dcCore::app()->adminurl->get('admin.blog.theme', []))) {
            // A redirection occured, so we should never go further here
            exit;
        }

        if (dcCore::app()->admin->list->setConfiguration(dcCore::app()->blog->settings->system->theme)) {
            // Display module configuration page

            // Get content before page headers
            $include = dcCore::app()->admin->list->includeConfiguration();
            if ($include) {
                include $include;
            }

            // Gather content
            dcCore::app()->admin->list->getConfiguration();

            // Display page
            dcPage::open(
                __('Blog appearance'),
                dcPage::jsPageTabs() .

                # --BEHAVIOR-- themesToolsHeaders -- bool
                dcCore::app()->callBehavior('themesToolsHeadersV2', true),
                dcPage::breadcrumb(
                    [
                        // Active links
                        Html::escapeHTML(dcCore::app()->blog->name) => '',
                        __('Blog appearance')                       => dcCore::app()->admin->list->getURL('', false),
                        // inactive link
                        '<span class="page-title">' . __('Theme configuration') . '</span>' => '',
                    ]
                )
            );

            // Display previously gathered content
            dcCore::app()->admin->list->displayConfiguration();

            dcPage::helpBlock('core_blog_theme_conf');
            dcPage::close();

            // Stop reading code here
            return true;
        }

        // Execute actions
        try {
            dcCore::app()->admin->list->doActions();
        } catch (Exception $e) {
            dcCore::app()->error->add($e->getMessage());
        }

        return false;
    }

    /**
     * Processes the request(s).
     */
    public static function process()
    {
        if (!empty($_GET['shot'])) {
            // Get a theme screenshot
            $filename = Path::real(
                empty($_GET['src']) ?
                dcCore::app()->blog->themes_path . '/' . $_GET['shot'] . '/screenshot.jpg' :
                dcCore::app()->blog->themes_path . '/' . $_GET['shot'] . '/' . Path::clean($_GET['src'])
            );

            if (!file_exists($filename)) {
                $filename = __DIR__ . '/images/noscreenshot.png';
            }

            Http::cache([...[$filename], ...get_included_files()]);

            header('Content-Type: ' . Files::getMimeType($filename));
            header('Content-Length: ' . filesize($filename));
            readfile($filename);

            // File sent, so bye bye
            exit;
        }
    }

    /**
     * Renders the page.
     */
    public static function render()
    {
        // Page header
        dcPage::open(
            __('Themes management'),
            dcPage::jsLoad('js/_blog_theme.js') .
            dcPage::jsPageTabs() .

            # --BEHAVIOR-- themesToolsHeaders -- bool
            dcCore::app()->callBehavior('themesToolsHeadersV2', false),
            dcPage::breadcrumb(
                [
                    Html::escapeHTML(dcCore::app()->blog->name)                     => '',
                    '<span class="page-title">' . __('Blog appearance') . '</span>' => '',
                ]
            )
        );

        // Display themes lists --
        if (dcCore::app()->auth->isSuperAdmin()) {
            if (!dcCore::app()->error->flag() && !empty($_GET['nocache'])) {
                dcPage::success(__('Manual checking of themes update done successfully.'));
            }

            echo
            (new Form('force-checking'))
                ->action(dcCore::app()->admin->list->getURL('', false))
                ->method('get')
                ->fields([
                    (new Para())
                    ->items([
                        (new Hidden('nocache', '1')),
                        (new Submit('force-checking-update', __('Force checking update of themes'))),
                    ]),
                ])
                ->render();

            // Updated themes from repo
            $defines = dcCore::app()->admin->list->store->getDefines(true);
            if (!empty($defines)) {
                echo
                '<div class="multi-part" id="update" title="' . Html::escapeHTML(__('Update themes')) . '">' .
                '<h3>' . Html::escapeHTML(__('Update themes')) . '</h3>' .
                '<p>' . sprintf(
                    __('There is one theme to update available from repository.', 'There are %s themes to update available from repository.', count($defines)),
                    count($defines)
                ) . '</p>';

                dcCore::app()->admin->list
                    ->setList('theme-update')
                    ->setTab('themes')
                    ->setDefines($defines)
                    ->displayModules(
                        // cols
                        ['checkbox', 'name', 'sshot', 'desc', 'author', 'version', 'current_version', 'repository', 'parent'],
                        // actions
                        ['update', 'delete']
                    );

                echo
                '<p class="info vertical-separator">' . sprintf(
                    __('Visit %s repository, the resources center for Dotclear.'),
                    '<a href="https://themes.dotaddict.org/galerie-dc2/">Dotaddict</a>'
                ) .
                '</p>' .
                '</div>';
            }
        }

        // Activated themes
        $defines = dcCore::app()->admin->list->modules->searchDefines(
            ['state' => dcCore::app()->admin->list->modules->safeMode() ? Define::STATE_SOFT_DISABLED : Define::STATE_ENABLED]
        );
        if (!empty($defines)) {
            echo
            '<div class="multi-part" id="themes" title="' . __('Installed themes') . '">' .
            '<h3>' .
            (dcCore::app()->auth->isSuperAdmin() ? __('Activated themes') : __('Installed themes')) .
            (dcCore::app()->admin->list->modules->safeMode() ? ' ' . __('(in normal mode)') : '') .
            '</h3>' .
            '<p class="more-info">' . __('You can configure and manage installed themes from this list.') . '</p>';

            dcCore::app()->admin->list
                ->setList('theme-activate')
                ->setTab('themes')
                ->setDefines($defines)
                ->displayModules(
                    // cols
                    ['sshot', 'distrib', 'name', 'config', 'desc', 'author', 'version', 'parent'],
                    // actions
                    ['select', 'behavior', 'deactivate', 'clone', 'delete']
                );

            echo
            '</div>';
        }

        // Deactivated modules
        $defines = dcCore::app()->admin->list->modules->searchDefines(['state' => Define::STATE_HARD_DISABLED]);
        if (!empty($defines)) {
            echo
            '<div class="multi-part" id="deactivate" title="' . __('Deactivated themes') . '">' .
            '<h3>' . __('Deactivated themes') . '</h3>' .
            '<p class="more-info">' . __('Deactivated themes are installed but not usable. You can activate them from here.') . '</p>';

            dcCore::app()->admin->list
                ->setList('theme-deactivate')
                ->setTab('themes')
                ->setDefines($defines)
                ->displayModules(
                    // cols
                    ['sshot', 'name', 'distrib', 'desc', 'author', 'version'],
                    // actions
                    ['activate', 'delete']
                );

            echo
            '</div>';
        }

        if (dcCore::app()->auth->isSuperAdmin() && dcCore::app()->admin->list->isWritablePath()) {
            // New modules from repo
            $search  = dcCore::app()->admin->list->getSearch();
            $defines = $search ? dcCore::app()->admin->list->store->searchDefines($search) : dcCore::app()->admin->list->store->getDefines();

            if (!empty($search) || !empty($defines)) {
                echo
                '<div class="multi-part" id="new" title="' . __('Add themes') . '">' .
                '<h3>' . __('Add themes from repository') . '</h3>';

                dcCore::app()->admin->list
                    ->setList('theme-new')
                    ->setTab('new')
                    ->setDefines($defines)
                    ->displaySearch()
                    ->displayIndex()
                    ->displayModules(
                        // cols
                        ['expander', 'sshot', 'name', 'score', 'config', 'desc', 'author', 'version', 'parent', 'details', 'support'],
                        // actions
                        ['install'],
                        // nav limit
                        true
                    );

                echo
                '<p class="info vertical-separator">' . sprintf(
                    __('Visit %s repository, the resources center for Dotclear.'),
                    '<a href="https://themes.dotaddict.org/galerie-dc2/">Dotaddict</a>'
                ) .
                '</p>' .
                '</div>';
            }

            // Add a new theme
            echo
            '<div class="multi-part" id="addtheme" title="' . __('Install or upgrade manually') . '">' .
            '<h3>' . __('Add themes from a package') . '</h3>' .
            '<p class="more-info">' . __('You can install themes by uploading or downloading zip files.') . '</p>';

            dcCore::app()->admin->list->displayManualForm();

            echo
            '</div>';
        }

        # --BEHAVIOR-- themesToolsTabs --
        dcCore::app()->callBehavior('themesToolsTabsV2');

        // Notice for super admin
        if (dcCore::app()->auth->isSuperAdmin() && !dcCore::app()->admin->list->isWritablePath()) {
            echo
            '<p class="warning">' . __('Some functions are disabled, please give write access to your themes directory to enable them.') . '</p>';
        }

        dcPage::helpBlock('core_blog_theme');
        dcPage::close();
    }
}

if (adminBlogTheme::init()) {
    return;
}
adminBlogTheme::process();
adminBlogTheme::render();
