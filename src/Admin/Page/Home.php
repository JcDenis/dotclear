<?php
/**
 * @class Dotclear\Admin\Page\Home
 * @brief Dotclear admin home page
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin\Page;

use ArrayObject;

use Dotclear\Exception;

use Dotclear\Core\Core;

use Dotclear\Admin\Page;
use Dotclear\Admin\Menu;

use Dotclear\Html\Html;
use Dotclear\Html\Form;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Home extends Page
{
    protected $workspaces = ['dashboard', 'toggles', 'accessibility'];

    private $dragndrop_msg = ['dashboard', 'toggles', 'accessibility'];

    private $plugins_install = [];

    protected function getPermissions(): string|null|false
    {
        # Set default blog
        if (!empty($_GET['default_blog'])) {
            try {
                $this->core->setUserDefaultBlog($this->core->auth->userID(), $this->core->blog->id);
                $this->core->adminurl->redirect('admin.home');
            } catch (Exception $e) {
                $this->core->error->add($e->getMessage());
            }
        }

        # Logout
        if (!empty($_GET['logout'])) {
            $this->core->session->destroy();
            if (isset($_COOKIE['dc_admin'])) {
                unset($_COOKIE['dc_admin']);
                setcookie('dc_admin', '', -600, '', '', DOTCLEAR_ADMIN_SSL);
            }
            $this->core->adminurl->redirect('admin.auth');
            exit;
        }

        return 'usage,contentadmin';
    }

    protected function getPagePrepend(): ?bool
    {
        $this->dragndrop_msg  = [
            'dragndrop_off' => __("Dashboard area's drag and drop is disabled"),
            'dragndrop_on'  => __("Dashboard area's drag and drop is enabled")
        ];

        # Module Plugin
        if ($this->core->plugins) {
            if ($this->core->plugins->disableModulesDependencies($this->core->adminurl->get('admin.home'))) {
                exit;
            }

            $this->plugins_install = $this->core->plugins->installModules();
        }

        # Check dashboard module prefs
        if (!$this->core->auth->user_prefs->dashboard->prefExists('doclinks')) {
            if (!$this->core->auth->user_prefs->dashboard->prefExists('doclinks', true)) {
                $this->core->auth->user_prefs->dashboard->put('doclinks', true, 'boolean', '', null, true);
            }
            $this->core->auth->user_prefs->dashboard->put('doclinks', true, 'boolean');
        }
        if (!$this->core->auth->user_prefs->dashboard->prefExists('dcnews')) {
            if (!$this->core->auth->user_prefs->dashboard->prefExists('dcnews', true)) {
                $this->core->auth->user_prefs->dashboard->put('dcnews', true, 'boolean', '', null, true);
            }
            $this->core->auth->user_prefs->dashboard->put('dcnews', true, 'boolean');
        }
        if (!$this->core->auth->user_prefs->dashboard->prefExists('quickentry')) {
            if (!$this->core->auth->user_prefs->dashboard->prefExists('quickentry', true)) {
                $this->core->auth->user_prefs->dashboard->put('quickentry', false, 'boolean', '', null, true);
            }
            $this->core->auth->user_prefs->dashboard->put('quickentry', false, 'boolean');
        }
        if (!$this->core->auth->user_prefs->dashboard->prefExists('nodcupdate')) {
            if (!$this->core->auth->user_prefs->dashboard->prefExists('nodcupdate', true)) {
                $this->core->auth->user_prefs->dashboard->put('nodcupdate', false, 'boolean', '', null, true);
            }
            $this->core->auth->user_prefs->dashboard->put('nodcupdate', false, 'boolean');
        }

        // Handle folded/unfolded sections in admin from user preferences
        if (!$this->core->auth->user_prefs->toggles->prefExists('unfolded_sections')) {
            $this->core->auth->user_prefs->toggles->put('unfolded_sections', '', 'string', 'Folded sections in admin', null, true);
        }

        # Editor stuff
        $admin_post_behavior = '';
        if ($this->core->auth->user_prefs->dashboard->quickentry) {
            if ($this->core->auth->check('usage,contentadmin', $this->core->blog->id)) {
                $post_format = $this->core->auth->getOption('post_format');
                $post_editor = $this->core->auth->getOption('editor');
                if ($post_editor && !empty($post_editor[$post_format])) {
                    // context is not post because of tags not available
                    $admin_post_behavior = $this->core->behaviors->call('adminPostEditor', $post_editor[$post_format], 'quickentry', ['#post_content'], $post_format);
                }
            }
        }

        # Dashboard drag'n'drop switch for its elements
        $dragndrop_head = '';
        if (!$this->core->auth->user_prefs->accessibility->nodragdrop) {
            $dragndrop_head = self::jsJson('dotclear_dragndrop', $this->dragndrop_msg);
        }

        $this->setPageHelp('core_dashboard');
        $this->setPageTitle(__('Dashboard'));
        $this->setPageHead(
            self::jsLoad('js/jquery/jquery-ui.custom.js') .
            self::jsLoad('js/jquery/jquery.ui.touch-punch.js') .
            self::jsLoad('js/_index.js') .
            $dragndrop_head .
            $admin_post_behavior
        );
        $this->setPageBreadcrumb(
            [
                __('Dashboard') . ' : ' . Html::escapeHTML($this->core->blog->name) => ''
            ],
            ['home_link' => false]
        );

        return true;
    }

    protected function getPageContent(): void
    {
        # Dashboard icons
        $__dashboard_icons = new ArrayObject();

        $this->core->favs->getUserFavorites();
        $this->core->favs->appendDashboardIcons($__dashboard_icons);

        # Latest news for dashboard
        $__dashboard_items = new ArrayObject([new ArrayObject(), new ArrayObject()]);

        $dashboardItem = 0;

        # Documentation links
        if ($this->core->auth->user_prefs->dashboard->doclinks) {
            if (!empty($this->core->resources['doc'])) {
                $doc_links = '<div class="box small dc-box" id="doc-and-support"><h3>' . __('Documentation and support') . '</h3><ul>';

                foreach ($this->core->resources['doc'] as $k => $v) {
                    $doc_links .= '<li><a class="outgoing" href="' . $v . '" title="' . $k . '">' . $k .
                        ' <img src="?df=images/outgoing-link.svg" alt="" /></a></li>';
                }

                $doc_links .= '</ul></div>';
                $__dashboard_items[$dashboardItem][] = $doc_links;
                $dashboardItem++;
            }
        }

        $this->core->behaviors->call('adminDashboardItems', $__dashboard_items);

        # Dashboard content
        $__dashboard_contents = new ArrayObject([new ArrayObject, new ArrayObject]);
        $this->core->behaviors->call('adminDashboardContents', $__dashboard_contents);

        $dragndrop      = '';
        if (!$this->core->auth->user_prefs->accessibility->nodragdrop) {
            $dragndrop      = '<input type="checkbox" id="dragndrop" class="sr-only" title="' . $this->dragndrop_msg['dragndrop_off'] . '" />' .
                '<label for="dragndrop">' .
                '<svg aria-hidden="true" focusable="false" class="dragndrop-svg">' .
                '<use xlink:href="?df=images/dragndrop.svg#mask"></use>' .
                '</svg>' .
                '<span id="dragndrop-label" class="sr-only">' . $this->dragndrop_msg['dragndrop_off'] . '</span>' .
                '</label>';
        }

        if ($this->core->auth->getInfo('user_default_blog') != $this->core->blog->id && $this->core->auth->getBlogCount() > 1) {
            echo
            '<p><a href="' . $this->core->adminurl->get('admin.home', ['default_blog' => 1]) . '" class="button">' . __('Make this blog my default blog') . '</a></p>';
        }

        if ($this->core->blog->status == 0) {
            echo '<p class="static-msg">' . __('This blog is offline') . '.</p>';
        } elseif ($this->core->blog->status == -1) {
            echo '<p class="static-msg">' . __('This blog is removed') . '.</p>';
        }

        if (!defined('DOTCLEAR_ADMIN_URL') || !DOTCLEAR_ADMIN_URL) {    // @phpstan-ignore-line
            echo
            '<p class="static-msg">' .
            sprintf(__('%s is not defined, you should edit your configuration file.'), 'DC_ADMIN_URL') .
            ' ' . __('See <a href="https://dotclear.org/documentation/2.0/admin/config">documentation</a> for more information.') .
                '</p>';
        }

        if (!defined('DOTCLEAR_ADMIN_MAILFROM') || !DOTCLEAR_ADMIN_MAILFROM) {
            echo
            '<p class="static-msg">' .
            sprintf(__('%s is not defined, you should edit your configuration file.'), 'DOTCLEAR_ADMIN_MAILFROM') .
            ' ' . __('See <a href="https://dotclear.org/documentation/2.0/admin/config">documentation</a> for more information.') .
                '</p>';
        }

        $err = [];

        # Check cache directory
        if ($this->core->auth->isSuperAdmin()) {
            if (!is_dir(DOTCLEAR_CACHE_DIR) || !is_writable(DOTCLEAR_CACHE_DIR)) {
                $err[] = '<p>' . __('The cache directory does not exist or is not writable. You must create this directory with sufficient rights and affect this location to "DOTCLEAR_CACHE_DIR" in inc/config.php file.') . '</p>';
            }
        } else {
            if (!is_dir(DOTCLEAR_CACHE_DIR) || !is_writable(DOTCLEAR_CACHE_DIR)) {
                $err[] = '<p>' . __('The cache directory does not exist or is not writable. You should contact your administrator.') . '</p>';
            }
        }

        # Check public directory
        if ($this->core->auth->isSuperAdmin()) {
            if (!is_dir($this->core->blog->public_path) || !is_writable($this->core->blog->public_path)) {
                $err[] = '<p>' . __('There is no writable directory /public/ at the location set in about:config "public_path". You must create this directory with sufficient rights (or change this setting).') . '</p>';
            }
        } else {
            if (!is_dir($this->core->blog->public_path) || !is_writable($this->core->blog->public_path)) {
                $err[] = '<p>' . __('There is no writable root directory for the media manager. You should contact your administrator.') . '</p>';
            }
        }

        # Error list
        if (count($err) > 0) {
            echo '<div class="error"><p><strong>' . __('Error:') . '</strong></p>' .
            '<ul><li>' . implode('</li><li>', $err) . '</li></ul></div>';
        }

        # Module Plugin
        if ($this->core->plugins) {
            # Plugins install messages
            if (!empty($this->plugins_install['success'])) {
                echo '<div class="success">' . __('Following plugins have been installed:') . '<ul>';
                foreach ($this->plugins_install['success'] as $k => $v) {
                    $info = implode(' - ', $this->core->plugins->getSettingsUrls($k, true));
                    echo '<li>' . $k . ($info !== '' ? ' → ' . $info : '') . '</li>';
                }
                echo '</ul></div>';
            }
            if (!empty($this->plugins_install['failure'])) {
                echo '<div class="error">' . __('Following plugins have not been installed:') . '<ul>';
                foreach ($this->plugins_install['failure'] as $k => $v) {
                    echo '<li>' . $k . ' (' . $v . ')</li>';
                }
                echo '</ul></div>';
            }

            # Errors modules notifications
            if ($this->core->auth->isSuperAdmin()) {
                if ($this->core->plugins->error->flag()) {
                    echo
                    '<div class="error" id="module-errors" class="error"><p>' . __('Errors have occured with following plugins:') . '</p> ' .
                    '<ul><li>' . implode("</li>\n<li>", $this->core->plugins->error->getErrors()) . '</li></ul></div>';
                }
            }
        }

        # Get current main orders
        $main_order = $this->core->auth->user_prefs->dashboard->main_order;
        $main_order = ($main_order != '' ? explode(',', $main_order) : []);

        # Get current boxes orders
        $boxes_order = $this->core->auth->user_prefs->dashboard->boxes_order;
        $boxes_order = ($boxes_order != '' ? explode(',', $boxes_order) : []);

        # Get current boxes items orders
        $boxes_items_order = $this->core->auth->user_prefs->dashboard->boxes_items_order;
        $boxes_items_order = ($boxes_items_order != '' ? explode(',', $boxes_items_order) : []);

        # Get current boxes contents orders
        $boxes_contents_order = $this->core->auth->user_prefs->dashboard->boxes_contents_order;
        $boxes_contents_order = ($boxes_contents_order != '' ? explode(',', $boxes_contents_order) : []);

        # Compose dashboard items (doc, …)
        $dashboardItems = self::composeItems($boxes_items_order, $__dashboard_items);
        # Compose dashboard contents (plugin's modules)
        $dashboardContents = self::composeItems($boxes_contents_order, $__dashboard_contents);

        $__dashboard_boxes = [];
        if ($dashboardItems != '') {
            $__dashboard_boxes[] = '<div class="db-items" id="db-items">' . $dashboardItems . '</div>';
        }
        if ($dashboardContents != '') {
            $__dashboard_boxes[] = '<div class="db-contents" id="db-contents">' . $dashboardContents . '</div>';
        }
        $dashboardBoxes = self::composeItems($boxes_order, $__dashboard_boxes, true);

        # Compose main area
        $__dashboard_main = [];
        if (!$this->core->auth->user_prefs->dashboard->nofavicons) {
            # Dashboard icons
            $dashboardIcons = '<div id="icons">';
            foreach ($__dashboard_icons as $i) {
                $dashboardIcons .= '<p><a href="' . $i[1] . '">' . $this->core->menu->getIconTheme($i[2]) .
                    '<br /><span class="db-icon-title">' . $i[0] . '</span></a></p>';
            }
            $dashboardIcons .= '</div>';
            $__dashboard_main[] = $dashboardIcons;
        }
        if ($this->core->auth->user_prefs->dashboard->quickentry) {
            if ($this->core->auth->check('usage,contentadmin', $this->core->blog->id)) {
                # Getting categories
                $categories_combo = $this->core->combos->getCategoriesCombo(
                    $this->core->blog->getCategories([])
                );

                $dashboardQuickEntry = '<div id="quick">' .
                '<h3>' . __('Quick post') . sprintf(' &rsaquo; %s', $this->core->auth->getOption('post_format')) . '</h3>' .
                '<form id="quick-entry" action="' . $this->core->adminurl->get('admin.post') . '" method="post" class="fieldset">' .
                '<h4>' . __('New post') . '</h4>' .
                '<p class="col"><label for="post_title" class="required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Title:') . '</label>' .
                Form::field('post_title', 20, 255, [
                    'class'      => 'maximal',
                    'extra_html' => 'required placeholder="' . __('Title') . '"'
                ]) .
                '</p>' .
                '<div class="area"><label class="required" ' .
                'for="post_content"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Content:') . '</label> ' .
                Form::textarea('post_content', 50, 10, ['extra_html' => 'required placeholder="' . __('Content') . '"']) .
                '</div>' .
                '<p><label for="cat_id" class="classic">' . __('Category:') . '</label> ' .
                Form::combo('cat_id', $categories_combo) . '</p>' .
                ($this->core->auth->check('categories', $this->core->blog->id)
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
                ($this->core->auth->check('publish', $this->core->blog->id)
                    ? '<input type="hidden" value="' . __('Save and publish') . '" name="save-publish" />'
                    : '') .
                $this->core->formNonce() .
                Form::hidden('post_status', -2) .
                Form::hidden('post_format', $this->core->auth->getOption('post_format')) .
                Form::hidden('post_excerpt', '') .
                Form::hidden('post_lang', $this->core->auth->getInfo('user_lang')) .
                Form::hidden('post_notes', '') .
                    '</p>' .
                    '</form>' .
                    '</div>';
                $__dashboard_main[] = $dashboardQuickEntry;
            }
        }
        if ($dashboardBoxes != '') {
            $__dashboard_main[] = '<div id="dashboard-boxes">' . $dashboardBoxes . '</div>';
        }
        $dashboardMain = self::composeItems($main_order, $__dashboard_main, true);

        echo $dragndrop . '<div id="dashboard-main">' . $dashboardMain . '</div>';
    }

    private static function composeItems($list, $blocks, $flat = false)
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

        # First loop to find ordered indexes
        $order = [];
        $index = 0;
        foreach ($items as $v) {
            if (preg_match('/<div.*?id="([^"].*?)".*?>/ms', $v, $match)) {
                $id       = $match[1];
                $position = array_search($id, $list, true);
                if ($position !== false) {
                    $order[$position] = $index;
                }
            }
            $index++;
        }

        # Second loop to combine ordered items
        $index = 0;
        foreach ($items as $v) {
            $position = array_search($index, $order, true);
            if ($position !== false) {
                $ret[$position] = $v;
            }
            $index++;
        }
        # Reorder items on their position (key)
        ksort($ret);

        # Third loop to combine unordered items
        $index = 0;
        foreach ($items as $v) {
            $position = array_search($index, $order, true);
            if ($position === false) {
                $ret[count($ret)] = $v;
            }
            $index++;
        }

        return join('', $ret);
    }
}
