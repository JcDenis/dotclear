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
use Dotclear\App;
use Dotclear\Helper\GPC\GPC;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Mapper\Strings;
use Dotclear\Process\Admin\Favorite\DashboardIcon;
use Dotclear\Process\Admin\Page\AbstractPage;
use Exception;

/**
 * Admin home page.
 *
 * Dashboard composition:
 * _ main:
 * __ icons
 * __ quickentry
 * __ boxes:
 * ___ items
 * ___ contents
 *
 * @ingroup  Admin Home Handler
 */
class Home extends AbstractPage
{
    private $home_dragndrop_msg = [];

    protected function getPermissions(): string|bool
    {
        // Set default blog
        if (!GPC::get()->empty('default_blog')) {
            try {
                App::core()->users()->setUserDefaultBlog(id: App::core()->user()->userID(), blog: App::core()->blog()->id);
                App::core()->adminurl()->redirect('admin.home');
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
            }
        }

        // Logout
        if (!GPC::get()->empty('logout')) {
            App::core()->session()->destroy();
            if (GPC::cookie()->isset('dc_admin')) {
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

        // --BEHAVIOR-- adminBeforeGetHomePage
        App::core()->behavior('adminBeforeGetHomePage')->call();

        // Check dashboard module prefs
        if (!App::core()->user()->preferences()->getGroup('dashboard')->hasLocalPreference('doclinks')) {
            if (!App::core()->user()->preferences()->getGroup('dashboard')->hasGlobalPreference('doclinks')) {
                App::core()->user()->preferences()->getGroup('dashboard')->putPreference('doclinks', true, 'boolean', '', null, true);
            }
            App::core()->user()->preferences()->getGroup('dashboard')->putPreference('doclinks', true, 'boolean');
        }
        if (!App::core()->user()->preferences()->getGroup('dashboard')->hasLocalPreference('dcnews')) {
            if (!App::core()->user()->preferences()->getGroup('dashboard')->hasGlobalPreference('dcnews')) {
                App::core()->user()->preferences()->getGroup('dashboard')->putPreference('dcnews', true, 'boolean', '', null, true);
            }
            App::core()->user()->preferences()->getGroup('dashboard')->putPreference('dcnews', true, 'boolean');
        }
        if (!App::core()->user()->preferences()->getGroup('dashboard')->hasLocalPreference('quickentry')) {
            if (!App::core()->user()->preferences()->getGroup('dashboard')->hasGlobalPreference('quickentry')) {
                App::core()->user()->preferences()->getGroup('dashboard')->putPreference('quickentry', false, 'boolean', '', null, true);
            }
            App::core()->user()->preferences()->getGroup('dashboard')->putPreference('quickentry', false, 'boolean');
        }
        if (!App::core()->user()->preferences()->getGroup('dashboard')->hasLocalPreference('nodcupdate')) {
            if (!App::core()->user()->preferences()->getGroup('dashboard')->hasGlobalPreference('nodcupdate')) {
                App::core()->user()->preferences()->getGroup('dashboard')->putPreference('nodcupdate', false, 'boolean', '', null, true);
            }
            App::core()->user()->preferences()->getGroup('dashboard')->putPreference('nodcupdate', false, 'boolean');
        }

        // Handle folded/unfolded sections in admin from user preferences
        if (!App::core()->user()->preferences()->getGroup('toggles')->hasLocalPreference('unfolded_sections')) {
            App::core()->user()->preferences()->getGroup('toggles')->putPreference('unfolded_sections', '', 'string', 'Folded sections in admin', null, true);
        }

        // Editor stuff
        $admin_post_behavior = '';
        if (App::core()->user()->preferences()->getGroup('dashboard')->getPreference('quickentry')) {
            if (App::core()->user()->check('usage,contentadmin', App::core()->blog()->id)) {
                $post_format = App::core()->user()->getOption('post_format');
                $post_editor = App::core()->user()->getOption('editor');
                if ($post_editor && !empty($post_editor[$post_format])) {
                    // --BEHAVIOR-- adminBeforeGetPostEditorHead, string, string, array, string
                    $admin_post_behavior = App::core()->behavior('adminBeforeGetPostEditorHead')->call(
                        editor: $post_editor[$post_format],
                        context: 'quickentry',
                        tags: ['#post_content'],
                        syntax: $post_format
                    );
                }
            }
        }

        // Dashboard drag'n'drop switch for its elements
        $dragndrop_head = '';
        if (!App::core()->user()->preferences()->getGroup('accessibility')->getPreference('nodragdrop')) {
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

        // Non production mode alert
        if (App::core()->user()->isSuperAdmin() && !App::core()->isProductionMode()) {
            echo '<p class="static-msg">' .
            __('You are in non production environment. If your blogs plateform is public you should set production directive to true in your dotclear config file.') .
            '</p>';
        }

        $err = [];

        if (App::core()->user()->isSuperAdmin() && !App::core()->themes()->hasModules()) {
            $err[] = '<p>' . __('There seems to be no valid Theme in your themes directories.') . '</p>';
        }

        // Check cache directory
        if (App::core()->user()->isSuperAdmin()) {
            if (!is_dir(App::core()->config()->get('cache_dir')) || !is_writable(App::core()->config()->get('cache_dir'))) {
                $err[] = '<p>' . __('The cache directory does not exist or is not writable. You must create this directory with sufficient rights and affect this location to "cache_dir" in dotclear.conf.php file.') . '</p>';
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

        // --BEHAVIOR-- adminBeforeGetHomePageContent
        App::core()->behavior('adminBeforeGetHomePageContent')->call();

        // Dashboard groups
        $main     = new Strings();
        $boxes    = new Strings();
        $contents = new Strings();
        $items    = new Strings();

        // Dashboard icons
        if (!App::core()->user()->preferences()->getGroup('dashboard')->getPreference('nofavicons')) {
            $icons = '';
            foreach (App::core()->favorite()->getUserItems() as $item) {
                // Move favorites item to editable dashboard icon
                $icon = new DashboardIcon(
                    id: $item->id,
                    title: $item->title,
                    url: $item->url,
                    icons: $item->icons,
                );

                if (!empty($item->dashboard) && is_callable($item->dashboard)) {
                    call_user_func($item->dashboard, $icon);
                }

                // --BEHAVIOR-- adminBeforeAddDashboardIcon, DashboardIcon
                App::core()->behavior('adminBeforeAddDashboardIcon')->call(icon: $icon);

                if ($icon instanceof DashboardIcon) {
                    $icons .= $icon->toHtml();
                }
            }

            if (!empty($icons)) {
                $main->add('<div id="icons">' . $icons . '</div>');
            }
        }

        // Dashboard quick entry
        if (App::core()->user()->preferences()->getGroup('dashboard')->getPreference('quickentry')
            && App::core()->user()->check('usage,contentadmin', App::core()->blog()->id)
        ) {
            // Getting categories
            $categories_combo = App::core()->combo()->getCategoriesCombo(
                App::core()->blog()->categories()->getCategories()
            );

            $main->add(
                '<div id="quick">' .
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
                    '</div>'
            );
        }

        // Documentation links
        if (App::core()->user()->preferences()->getGroup('dashboard')->getPreference('doclinks')) {
            $docs = App::core()->help()->doc();
            if (!empty($docs)) {
                $links = '';
                foreach ($docs as $k => $v) {
                    $links .= '<li><a class="outgoing" href="' . $v . '" title="' . $k . '">' . $k .
                        ' <img src="?df=images/outgoing-link.svg" alt="" /></a></li>';
                }

                $items->add('<div class="box small dc-box" id="doc-and-support"><h3>' . __('Documentation and support') . '</h3><ul>' . $links . '</ul></div>');
            }
        }

        // --BEHAVIOR-- adminBeforeAddDashboardItems, Strings
        App::core()->behavior('adminBeforeAddDashboardItems')->call(items: $items);

        // Dashoboard items
        if ('' != ($items = $this->composeDashboardGroup('boxes_items_order', $items))) {
            $boxes->add('<div class="db-items" id="db-items">' . $items . '</div>');
        }

        // --BEHAVIOR-- adminBeforeAddDashboardContents, Strings
        App::core()->behavior('adminBeforeAddDashboardContents')->call(items: $contents);

        // Dashboard contents
        if ('' != ($contents = $this->composeDashboardGroup('boxes_contents_order', $contents))) {
            $boxes->add('<div class="db-contents" id="db-contents">' . $contents . '</div>');
        }

        // Dashboard groups
        if ($boxes->count()) {
            $main->add('<div id="dashboard-boxes">' . $this->composeDashboardGroup('boxes_order', $boxes) . '</div>');
        }

        // Drog n drop
        if (!App::core()->user()->preferences()->getGroup('accessibility')->getPreference('nodragdrop')) {
            echo '<input type="checkbox" id="dragndrop" class="sr-only" title="' . $this->home_dragndrop_msg['dragndrop_off'] . '" />' .
                '<label for="dragndrop">' .
                '<svg aria-hidden="true" focusable="false" class="dragndrop-svg">' .
                '<use xlink:href="?df=images/dragndrop.svg#mask"></use>' .
                '</svg>' .
                '<span id="dragndrop-label" class="sr-only">' . $this->home_dragndrop_msg['dragndrop_off'] . '</span>' .
                '</label>';
        }

        echo '<div id="dashboard-main">' . $this->composeDashboardGroup('main_order', $main) . '</div>';
    }

    /**
     * Reorder items of a given group.
     *
     * @param string  $part   The group name
     * @param Strings $blocks The group items
     *
     * @return string The sorted group
     */
    private function composeDashboardGroup(string $part, Strings $blocks): string
    {
        $ret   = [];
        $items = $blocks->dump();
        $list  = $this->getDashboardOrder($part);

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

    /**
     * Get items order form user preference.
     *
     * @return array<int,string> The user isrems order preference
     */
    private function getDashboardOrder(string $part): array
    {
        $order = App::core()->user()->preferences()->getGroup('dashboard')->getPreference($part);

        return '' != $order ? explode(',', $order) : [];
    }
}
