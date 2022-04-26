<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Handler;

// Dotclear\Process\Admin\Handler\Home
use ArrayObject;
use Dotclear\App;
use Dotclear\Process\Admin\Page\AbstractPage;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Exception;

/**
 * Admin home page.
 *
 * @ingroup  Admin Home Handler
 */
class Home extends AbstractPage
{
    private $home_dragndrop_msg   = ['dashboard', 'toggles', 'accessibility'];
    private $home_plugins_install = [];

    protected function getPermissions(): string|null|false
    {
        // Set default blog
        if (!empty($_GET['default_blog'])) {
            try {
                App::core()->users()->setUserDefaultBlog(App::core()->user()->userID(), App::core()->blog()->id);
                App::core()->adminurl()->redirect('admin.home');
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
            }
        }

        // Logout
        if (!empty($_GET['logout'])) {
            App::core()->session()->destroy();
            if (isset($_COOKIE['dc_admin'])) {
                unset($_COOKIE['dc_admin']);
                setcookie('dc_admin', '', -600, '', '', App::core()->config()->get('admin_ssl'));
            }
            App::core()->adminurl()->redirect('admin.auth');

            exit;
        }

        return 'usage,contentadmin';
    }

    protected function getPagePrepend(): ?bool
    {
        $this->home_dragndrop_msg = [
            'dragndrop_off' => __("Dashboard area's drag and drop is disabled"),
            'dragndrop_on'  => __("Dashboard area's drag and drop is enabled"),
        ];

        // Module Plugin //! move this to Modules Plugin
        if (App::core()->plugins()) {
            if (App::core()->plugins()->disableModulesDependencies(App::core()->adminurl()->get('admin.home'))) {
                exit;
            }

            $this->home_plugins_install = App::core()->plugins()->installModules();
        }

        // Check dashboard module prefs
        if (!App::core()->user()->preference()->get('dashboard')->prefExists('doclinks')) {
            if (!App::core()->user()->preference()->get('dashboard')->prefExists('doclinks', true)) {
                App::core()->user()->preference()->get('dashboard')->put('doclinks', true, 'boolean', '', null, true);
            }
            App::core()->user()->preference()->get('dashboard')->put('doclinks', true, 'boolean');
        }
        if (!App::core()->user()->preference()->get('dashboard')->prefExists('dcnews')) {
            if (!App::core()->user()->preference()->get('dashboard')->prefExists('dcnews', true)) {
                App::core()->user()->preference()->get('dashboard')->put('dcnews', true, 'boolean', '', null, true);
            }
            App::core()->user()->preference()->get('dashboard')->put('dcnews', true, 'boolean');
        }
        if (!App::core()->user()->preference()->get('dashboard')->prefExists('quickentry')) {
            if (!App::core()->user()->preference()->get('dashboard')->prefExists('quickentry', true)) {
                App::core()->user()->preference()->get('dashboard')->put('quickentry', false, 'boolean', '', null, true);
            }
            App::core()->user()->preference()->get('dashboard')->put('quickentry', false, 'boolean');
        }
        if (!App::core()->user()->preference()->get('dashboard')->prefExists('nodcupdate')) {
            if (!App::core()->user()->preference()->get('dashboard')->prefExists('nodcupdate', true)) {
                App::core()->user()->preference()->get('dashboard')->put('nodcupdate', false, 'boolean', '', null, true);
            }
            App::core()->user()->preference()->get('dashboard')->put('nodcupdate', false, 'boolean');
        }

        // Handle folded/unfolded sections in admin from user preferences
        if (!App::core()->user()->preference()->get('toggles')->prefExists('unfolded_sections')) {
            App::core()->user()->preference()->get('toggles')->put('unfolded_sections', '', 'string', 'Folded sections in admin', null, true);
        }

        // Editor stuff
        $admin_post_behavior = '';
        if (App::core()->user()->preference()->get('dashboard')->get('quickentry')) {
            if (App::core()->user()->check('usage,contentadmin', App::core()->blog()->id)) {
                $post_format = App::core()->user()->getOption('post_format');
                $post_editor = App::core()->user()->getOption('editor');
                if ($post_editor && !empty($post_editor[$post_format])) {
                    // context is not post because of tags not available
                    $admin_post_behavior = App::core()->behavior()->call('adminPostEditor', $post_editor[$post_format], 'quickentry', ['#post_content'], $post_format);
                }
            }
        }

        // Dashboard drag'n'drop switch for its elements
        $dragndrop_head = '';
        if (!App::core()->user()->preference()->get('accessibility')->get('nodragdrop')) {
            $dragndrop_head = App::core()->resource()->json('dotclear_dragndrop', $this->home_dragndrop_msg);
        }

        $this->setPageHelp('core_dashboard');
        $this->setPageTitle(__('Dashboard'));
        $this->setPageHead(
            App::core()->resource()->load('jquery/jquery-ui.custom.js') .
            App::core()->resource()->load('jquery/jquery.ui.touch-punch.js') .
            App::core()->resource()->load('_index.js') .
            $dragndrop_head .
            $admin_post_behavior
        );
        $this->setPageBreadcrumb(
            [
                __('Dashboard') . ' : ' . Html::escapeHTML(App::core()->blog()->name) => '',
            ],
            ['home_link' => false]
        );

        return true;
    }

    protected function getPageContent(): void
    {
        // Dashboard icons
        $__dashboard_icons = new ArrayObject();

        App::core()->favorite()->getUserFavorites();
        App::core()->favorite()->appendDashboardIcons($__dashboard_icons);

        // Latest news for dashboard
        $__dashboard_items = new ArrayObject([new ArrayObject(), new ArrayObject()]);

        $dashboardItem = 0;

        // Documentation links
        if (App::core()->user()->preference()->get('dashboard')->get('doclinks')) {
            if (!empty(App::core()->help()->doc())) {
                $doc_links = '<div class="box small dc-box" id="doc-and-support"><h3>' . __('Documentation and support') . '</h3><ul>';

                foreach (App::core()->help()->doc() as $k => $v) {
                    $doc_links .= '<li><a class="outgoing" href="' . $v . '" title="' . $k . '">' . $k .
                        ' <img src="?df=images/outgoing-link.svg" alt="" /></a></li>';
                }

                $doc_links .= '</ul></div>';
                $__dashboard_items[$dashboardItem][] = $doc_links;
                ++$dashboardItem;
            }
        }

        App::core()->behavior()->call('adminDashboardItems', $__dashboard_items);

        // Dashboard content
        $__dashboard_contents = new ArrayObject([new ArrayObject(), new ArrayObject()]);
        App::core()->behavior()->call('adminDashboardContents', $__dashboard_contents);

        $dragndrop = '';
        if (!App::core()->user()->preference()->get('accessibility')->get('nodragdrop')) {
            $dragndrop = '<input type="checkbox" id="dragndrop" class="sr-only" title="' . $this->home_dragndrop_msg['dragndrop_off'] . '" />' .
                '<label for="dragndrop">' .
                '<svg aria-hidden="true" focusable="false" class="dragndrop-svg">' .
                '<use xlink:href="?df=images/dragndrop.svg#mask"></use>' .
                '</svg>' .
                '<span id="dragndrop-label" class="sr-only">' . $this->home_dragndrop_msg['dragndrop_off'] . '</span>' .
                '</label>';
        }

        if (App::core()->user()->getInfo('user_default_blog') != App::core()->blog()->id && App::core()->user()->getBlogCount() > 1) {
            echo '<p><a href="' . App::core()->adminurl()->get('admin.home', ['default_blog' => 1]) . '" class="button">' . __('Make this blog my default blog') . '</a></p>';
        }

        if (App::core()->blog()->status == 0) {
            echo '<p class="static-msg">' . __('This blog is offline') . '.</p>';
        } elseif (App::core()->blog()->status == -1) {
            echo '<p class="static-msg">' . __('This blog is removed') . '.</p>';
        }

        if (!App::core()->config()->get('admin_url')) {
            echo '<p class="static-msg">' .
            sprintf(__('%s is not defined, you should edit your configuration file.'), 'admin_url') .
            ' ' . __('See <a href="https://dotclear.org/documentation/2.0/admin/config">documentation</a> for more information.') .
                '</p>';
        }

        if (!App::core()->config()->get('admin_mailform')) {
            echo '<p class="static-msg">' .
            sprintf(__('%s is not defined, you should edit your configuration file.'), 'admin_mailform') .
            ' ' . __('See <a href="https://dotclear.org/documentation/2.0/admin/config">documentation</a> for more information.') .
                '</p>';
        }

        $err = [];

        // Check cache directory
        if (App::core()->user()->isSuperAdmin()) {
            if (!is_dir(App::core()->config()->get('cache_dir')) || !is_writable(App::core()->config()->get('cache_dir'))) {
                $err[] = '<p>' . __('The cache directory does not exist or is not writable. You must create this directory with sufficient rights and affect this location to "cache_dir" in config.php file.') . '</p>';
            }
        } else {
            if (!is_dir(App::core()->config()->get('cache_dir')) || !is_writable(App::core()->config()->get('cache_dir'))) {
                $err[] = '<p>' . __('The cache directory does not exist or is not writable. You should contact your administrator.') . '</p>';
            }
        }

        // Check public directory
        if (App::core()->user()->isSuperAdmin()) {
            if (!App::core()->blog()->public_path || !is_dir(App::core()->blog()->public_path) || !is_writable(App::core()->blog()->public_path)) {
                $err[] = '<p>' . __('There is no writable directory /public/ at the location set in about:config "public_path". You must create this directory with sufficient rights (or change this setting).') . '</p>';
            }
        } else {
            if (!App::core()->blog()->public_path || !is_dir(App::core()->blog()->public_path) || !is_writable(App::core()->blog()->public_path)) {
                $err[] = '<p>' . __('There is no writable root directory for the media manager. You should contact your administrator.') . '</p>';
            }
        }

        // Error list
        if (count($err) > 0) {
            echo '<div class="error"><p><strong>' . __('Error:') . '</strong></p>' .
            '<ul><li>' . implode('</li><li>', $err) . '</li></ul></div>';
        }

        // Module Plugin
        if (App::core()->plugins()) {
            // Plugins install messages
            if (!empty($this->home_plugins_install['success'])) {
                echo '<div class="success">' . __('Following plugins have been installed:') . '<ul>';
                foreach ($this->home_plugins_install['success'] as $k => $v) {
                    $info = implode(' - ', App::core()->plugins()->getSettingsUrls($k, true));
                    echo '<li>' . $k . ('' !== $info ? ' → ' . $info : '') . '</li>';
                }
                echo '</ul></div>';
            }
            if (!empty($this->home_plugins_install['failure'])) {
                echo '<div class="error">' . __('Following plugins have not been installed:') . '<ul>';
                foreach ($this->home_plugins_install['failure'] as $k => $v) {
                    echo '<li>' . $k . ' (' . $v . ')</li>';
                }
                echo '</ul></div>';
            }

            // Errors modules notifications
            if (App::core()->user()->isSuperAdmin()) {
                if (App::core()->plugins()->error()->flag()) {
                    echo '<div class="error" id="module-errors" class="error"><p>' . __('Errors have occured with following plugins:') . '</p> ' .
                    '<ul><li>' . implode("</li>\n<li>", App::core()->plugins()->error()->dump()) . '</li></ul></div>';
                }
            }
        }

        // Get current main orders
        $main_order = App::core()->user()->preference()->get('dashboard')->get('main_order');
        $main_order = ('' != $main_order ? explode(',', $main_order) : []);

        // Get current boxes orders
        $boxes_order = App::core()->user()->preference()->get('dashboard')->get('boxes_order');
        $boxes_order = ('' != $boxes_order ? explode(',', $boxes_order) : []);

        // Get current boxes items orders
        $boxes_items_order = App::core()->user()->preference()->get('dashboard')->get('boxes_items_order');
        $boxes_items_order = ('' != $boxes_items_order ? explode(',', $boxes_items_order) : []);

        // Get current boxes contents orders
        $boxes_contents_order = App::core()->user()->preference()->get('dashboard')->get('boxes_contents_order');
        $boxes_contents_order = ('' != $boxes_contents_order ? explode(',', $boxes_contents_order) : []);

        // Compose dashboard items (doc, …)
        $dashboardItems = $this->composeItems($boxes_items_order, $__dashboard_items);
        // Compose dashboard contents (plugin's modules)
        $dashboardContents = $this->composeItems($boxes_contents_order, $__dashboard_contents);

        $__dashboard_boxes = [];
        if ('' != $dashboardItems) {
            $__dashboard_boxes[] = '<div class="db-items" id="db-items">' . $dashboardItems . '</div>';
        }
        if ('' != $dashboardContents) {
            $__dashboard_boxes[] = '<div class="db-contents" id="db-contents">' . $dashboardContents . '</div>';
        }
        $dashboardBoxes = $this->composeItems($boxes_order, $__dashboard_boxes, true);

        // Compose main area
        $__dashboard_main = [];
        if (!App::core()->user()->preference()->get('dashboard')->get('nofavicons')) {
            // Dashboard icons
            $dashboardIcons = '<div id="icons">';
            foreach ($__dashboard_icons as $dib => $i) {
                $dashboardIcons .= '<p id="db-icon-' . $dib . '"><a href="' . $i[1] . '">' . App::core()->summary()->getIconTheme($i[2]) .
                    '<br /><span class="db-icon-title">' . $i[0] . '</span></a></p>';
            }
            $dashboardIcons .= '</div>';
            $__dashboard_main[] = $dashboardIcons;
        }
        if (App::core()->user()->preference()->get('dashboard')->get('quickentry')) {
            if (App::core()->user()->check('usage,contentadmin', App::core()->blog()->id)) {
                // Getting categories
                $categories_combo = App::core()->combo()->getCategoriesCombo(
                    App::core()->blog()->categories()->getCategories([])
                );

                $dashboardQuickEntry = '<div id="quick">' .
                '<h3>' . __('Quick post') . sprintf(' &rsaquo; %s', App::core()->user()->getOption('post_format')) . '</h3>' .
                '<form id="quick-entry" action="' . App::core()->adminurl()->root() . '" method="post" class="fieldset">' .
                '<h4>' . __('New post') . '</h4>' .
                '<p class="col"><label for="post_title" class="required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Title:') . '</label>' .
                Form::field('post_title', 20, 255, [
                    'class'      => 'maximal',
                    'extra_html' => 'required placeholder="' . __('Title') . '"',
                ]) .
                '</p>' .
                '<div class="area"><label class="required" ' .
                'for="post_content"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Content:') . '</label> ' .
                Form::textarea('post_content', 50, 10, ['extra_html' => 'required placeholder="' . __('Content') . '"']) .
                '</div>' .
                '<p><label for="cat_id" class="classic">' . __('Category:') . '</label> ' .
                Form::combo('cat_id', $categories_combo) . '</p>' .
                (App::core()->user()->check('categories', App::core()->blog()->id)
                    ? '<div>' .
                    '<p id="new_cat" class="q-cat">' . __('Add a new category') . '</p>' .
                    '<p class="q-cat"><label for="new_cat_title">' . __('Title:') . '</label> ' .
                    Form::field('new_cat_title', 30, 255) . '</p>' .
                    '<p class="q-cat"><label for="new_cat_parent">' . __('Parent:') . '</label> ' .
                    Form::combo('new_cat_parent', $categories_combo) .
                    '</p>' .
                    '<p class="form-note info clear">' . __('This category will be created when you will save your post.') . '</p>' .
                    '</div>'
                    : '') .
                '<p><input type="submit" value="' . __('Save') . '" name="save" /> ' .
                (App::core()->user()->check('publish', App::core()->blog()->id)
                    ? '<input type="hidden" value="' . __('Save and publish') . '" name="save-publish" />'
                    : '') .
                App::core()->adminurl()->getHiddenFormFields('admin.post', [], true) .
                Form::hidden('post_status', -2) .
                Form::hidden('post_format', App::core()->user()->getOption('post_format')) .
                Form::hidden('post_excerpt', '') .
                Form::hidden('post_lang', App::core()->user()->getInfo('user_lang')) .
                Form::hidden('post_notes', '') .
                    '</p>' .
                    '</form>' .
                    '</div>';
                $__dashboard_main[] = $dashboardQuickEntry;
            }
        }
        if ('' != $dashboardBoxes) {
            $__dashboard_main[] = '<div id="dashboard-boxes">' . $dashboardBoxes . '</div>';
        }
        $dashboardMain = $this->composeItems($main_order, $__dashboard_main, true);

        echo $dragndrop . '<div id="dashboard-main">' . $dashboardMain . '</div>';
    }

    private function composeItems($list, $blocks, $flat = false)
    {
        $ret   = [];
        $items = [];

        if ($flat) {
            $items = $blocks;
        } else {
            foreach ($blocks as $i) {
                foreach ($i as $v) {
                    $items[] = $v;
                }
            }
        }

        // First loop to find ordered indexes
        $order = [];
        $index = 0;
        foreach ($items as $v) {
            if (preg_match('/<div.*?id="([^"].*?)".*?>/ms', $v, $match)) {
                $id       = $match[1];
                $position = array_search($id, $list, true);
                if (false !== $position) {
                    $order[$position] = $index;
                }
            }
            ++$index;
        }

        // Second loop to combine ordered items
        $index = 0;
        foreach ($items as $v) {
            $position = array_search($index, $order, true);
            if (false !== $position) {
                $ret[$position] = $v;
            }
            ++$index;
        }
        // Reorder items on their position (key)
        ksort($ret);

        // Third loop to combine unordered items
        $index = 0;
        foreach ($items as $v) {
            $position = array_search($index, $order, true);
            if (false === $position) {
                $ret[count($ret)] = $v;
            }
            ++$index;
        }

        return join('', $ret);
    }
}
