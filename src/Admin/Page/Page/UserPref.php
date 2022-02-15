<?php
/**
 * @class Dotclear\Admin\Page\Page\UserPref
 * @brief Dotclear admin user preferences page
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin\Page\Page;

use ArrayObject;

use Dotclear\Admin\Page\Page;
use Dotclear\Container\User as ContainerUser;
use Dotclear\Core\Utils;
use Dotclear\Exception\AdminException;
use Dotclear\Html\Form;
use Dotclear\Html\Html;
use Dotclear\Utils\Dt;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class UserPref extends Page
{
    /** @var string     User other emails (comma separated list ) */
    protected $user_profile_mails = '';

    /** @var string     User other URLs (comma separated list ) */
    protected $user_profile_urls = '';

    protected $format_by_editors = [];
    protected $available_formats = [];

    protected $cols;
    protected $sorts;

    protected $rte;

    protected $user_dm_doclinks   = '';
    protected $user_dm_dcnews     = '';
    protected $user_dm_quickentry = '';
    protected $user_dm_nofavicons ='';
    protected $user_dm_nodcupdate = false;

    protected $user_acc_nodragdrop = false;

    protected $user_ui_theme            = '';
    protected $user_ui_enhanceduploader = '';
    protected $user_ui_blank_preview    = '';
    protected $user_ui_hidemoreinfo     = '';
    protected $user_ui_hidehelpbutton   = '';
    protected $user_ui_showajaxloader   = '';
    protected $user_ui_htmlfontsize     = '';
    protected $user_ui_hide_std_favicon = false;
    protected $user_ui_iconset            = '';
    protected $user_ui_nofavmenu          = '';
    protected $user_ui_media_nb_last_dirs = '';
    protected $user_ui_nocheckadblocker   = '';

    protected $workspaces = ['profile', 'dashboard', 'accessibility', 'interface', 'favorites'];

    protected function getPermissions(): string|null|false
    {
        return 'usage,contentadmin';
    }

    protected function getPagePrepend(): ?bool
    {
        $page_title = __('My preferences');

        $this->container = new ContainerUser();

        $this->container->fromRecord(dotclear()->users()->getUser(dotclear()->auth()->userID()));

        if (empty($this->container->getOption('editor'))) {
            $this->container->setOption('editor', []);
        }

        $this->user_profile_mails = dotclear()->auth()->user_prefs->profile->mails;
        $this->user_profile_urls  = dotclear()->auth()->user_prefs->profile->urls;

        $this->user_dm_doclinks   = dotclear()->auth()->user_prefs->dashboard->doclinks;
        $this->user_dm_dcnews     = dotclear()->auth()->user_prefs->dashboard->dcnews;
        $this->user_dm_quickentry = dotclear()->auth()->user_prefs->dashboard->quickentry;
        $this->user_dm_nofavicons = dotclear()->auth()->user_prefs->dashboard->nofavicons;
        $this->user_dm_nodcupdate = false;
        if (dotclear()->auth()->isSuperAdmin()) {
            $this->user_dm_nodcupdate = dotclear()->auth()->user_prefs->dashboard->nodcupdate;
        }

        $this->user_acc_nodragdrop = dotclear()->auth()->user_prefs->accessibility->nodragdrop;

        $this->user_ui_theme            = dotclear()->auth()->user_prefs->interface->theme;
        $this->user_ui_enhanceduploader = dotclear()->auth()->user_prefs->interface->enhanceduploader;
        $this->user_ui_blank_preview    = dotclear()->auth()->user_prefs->interface->blank_preview;
        $this->user_ui_hidemoreinfo     = dotclear()->auth()->user_prefs->interface->hidemoreinfo;
        $this->user_ui_hidehelpbutton   = dotclear()->auth()->user_prefs->interface->hidehelpbutton;
        $this->user_ui_showajaxloader   = dotclear()->auth()->user_prefs->interface->showajaxloader;
        $this->user_ui_htmlfontsize     = dotclear()->auth()->user_prefs->interface->htmlfontsize;
        $this->user_ui_hide_std_favicon = false;
        if (dotclear()->auth()->isSuperAdmin()) {
            $this->user_ui_hide_std_favicon = dotclear()->auth()->user_prefs->interface->hide_std_favicon;
        }
        $this->user_ui_iconset            = @dotclear()->auth()->user_prefs->interface->iconset;
        $this->user_ui_nofavmenu          = dotclear()->auth()->user_prefs->interface->nofavmenu;
        $this->user_ui_media_nb_last_dirs = dotclear()->auth()->user_prefs->interface->media_nb_last_dirs;
        $this->user_ui_nocheckadblocker   = dotclear()->auth()->user_prefs->interface->nocheckadblocker;

        $default_tab = !empty($_GET['tab']) ? Html::escapeHTML($_GET['tab']) : 'user-profile';

        if (!empty($_GET['append']) || !empty($_GET['removed']) || !empty($_GET['neworder']) || !empty($_GET['replaced']) || !empty($_POST['appendaction']) || !empty($_POST['removeaction']) || !empty($_GET['db-updated']) || !empty($_POST['resetorder'])) {
            $default_tab = 'user-favorites';
        } elseif (!empty($_GET['updated'])) {
            $default_tab = 'user-options';
        }
        if (($default_tab != 'user-profile') && ($default_tab != 'user-options') && ($default_tab != 'user-favorites')) {
            $default_tab = 'user-profile';
        }

        # Format by editors
        $formaters         = dotclear()->formater()->getFormaters();
        $this->format_by_editors = [];
        foreach ($formaters as $editor => $formats) {
            foreach ($formats as $format) {
                $this->format_by_editors[$format][$editor] = $editor;
            }
        }
        $this->available_formats = ['' => ''];
        foreach (array_keys($this->format_by_editors) as $format) {
            $this->available_formats[$format] = $format;
            $this->container->setOption('editor', array_merge([$format => ''], $this->container->getOption('editor')));
        }

        # Ensure Font size is set to default is empty
        if ($this->user_ui_htmlfontsize == '') {
            $this->user_ui_htmlfontsize = '62.5%';
        }

        # Get 3rd parts xhtml editor flags
        $rte = [
            'blog_descr' => [true, __('Blog description (in blog parameters)')],
            'cat_descr'  => [true, __('Category description')],
        ];
        $this->rte = new ArrayObject($rte);
        dotclear()->behavior()->call('adminRteFlags', $this->rte);
        # Load user settings
        $rte_flags = @dotclear()->auth()->user_prefs->interface->rte_flags;
        if (is_array($rte_flags)) {
            foreach ($rte_flags as $fk => $fv) {
                if (isset($this->rte[$fk])) {
                    $this->rte[$fk][0] = $fv;
                }
            }
        }

        # Get default colums (admin lists)
        $this->cols = dotclear()->preference()->getUserColumns();

        # Get default sortby, order, nbperpage (admin lists)
        $this->sorts = dotclear()->preference()->getUserFilters();

        # Add or update user
        if (isset($_POST['user_name'])) {
            try {
                $pwd_check = !empty($_POST['cur_pwd']) && dotclear()->auth()->checkPassword($_POST['cur_pwd']);

                if (dotclear()->auth()->allowPassChange() && !$pwd_check && $this->container->getEmail() != $_POST['user_email']) {
                    throw new AdminException(__('If you want to change your email or password you must provide your current password.'));
                }

                $cur = dotclear()->con()->openCursor(dotclear()->prefix . 'user');

                $cur->user_name        = $this->container->setName($_POST['user_name']);
                $cur->user_firstname   = $this->container->setFirstname($_POST['user_firstname']);
                $cur->user_displayname = $this->container->setDisplayname($_POST['user_displayname']);
                $cur->user_email       = $this->container->setEmail($_POST['user_email']);
                $cur->user_url         = $this->container->setURL($_POST['user_url']);
                $cur->user_lang        = $this->container->setLang($_POST['user_lang']);
                $cur->user_tz          = $this->container->setTZ($_POST['user_tz']);

                $cur->user_options = new ArrayObject($this->container->getOptions());

                if (dotclear()->auth()->allowPassChange() && !empty($_POST['new_pwd'])) {
                    if (!$pwd_check) {
                        throw new AdminException(__('If you want to change your email or password you must provide your current password.'));
                    }

                    if ($_POST['new_pwd'] != $_POST['new_pwd_c']) {
                        throw new AdminException(__("Passwords don't match"));
                    }

                    $cur->user_pwd = $_POST['new_pwd'];
                }

                # --BEHAVIOR-- adminBeforeUserUpdate
                dotclear()->behavior()->call('adminBeforeUserProfileUpdate', $cur, dotclear()->auth()->userID());

                # Udate user
                dotclear()->users()->updUser(dotclear()->auth()->userID(), $cur);

                # Update profile
                # Sanitize list of secondary mails and urls if any
                $mails = $urls = '';
                if (!empty($_POST['user_profile_mails'])) {
                    $mails = implode(',', array_filter(filter_var_array(array_map('trim', explode(',', $_POST['user_profile_mails'])), FILTER_VALIDATE_EMAIL)));
                }
                if (!empty($_POST['user_profile_urls'])) {
                    $urls = implode(',', array_filter(filter_var_array(array_map('trim', explode(',', $_POST['user_profile_urls'])), FILTER_VALIDATE_URL)));
                }
                dotclear()->auth()->user_prefs->profile->put('mails', $mails, 'string');
                dotclear()->auth()->user_prefs->profile->put('urls', $urls, 'string');

                # --BEHAVIOR-- adminAfterUserUpdate
                dotclear()->behavior()->call('adminAfterUserProfileUpdate', $cur, dotclear()->auth()->userID());

                dotclear()->notice()->addSuccessNotice(__('Personal information has been successfully updated.'));

                dotclear()->adminurl()->redirect('admin.user.pref');
            } catch (\Exception $e) {
                dotclear()->error()->add($e->getMessage());
            }
        }

        # Update user options
        if (isset($_POST['user_options_submit'])) {
            try {
                $cur = dotclear()->con()->openCursor(dotclear()->prefix . 'user');

                $cur->user_name        = $this->container->getName();
                $cur->user_firstname   = $this->container->getFirstname();
                $cur->user_displayname = $this->container->getDisplayname();
                $cur->user_email       = $this->container->getEmail();
                $cur->user_url         = $this->container->getURL();
                $cur->user_lang        = $this->container->getLang();
                $cur->user_tz          = $this->container->getTZ();
                $cur->user_post_status = $this->container->setPostStatus($_POST['user_post_status']);

                $this->container->setOption('edit_size', $_POST['user_edit_size'], 'int');
                if ($this->container->getOption('edit_size') < 1) {
                    $this->container->setOption('edit_size', 10);
                }
                $this->container->setOption('post_format', $_POST['user_post_format']);
                $this->container->setOption('editor', $_POST['user_editor'], 'array');
                $this->container->setOption('enable_wysiwyg', !empty($_POST['user_wysiwyg']), 'int');
                $this->container->setOption('toolbar_bottom', !empty($_POST['user_toolbar_bottom']), 'int');

                $cur->user_options = new ArrayObject($this->container->getOptions());

                # --BEHAVIOR-- adminBeforeUserOptionsUpdate
                dotclear()->behavior()->call('adminBeforeUserOptionsUpdate', $cur, dotclear()->auth()->userID());

                # Update user prefs
                dotclear()->auth()->user_prefs->accessibility->put('nodragdrop', !empty($_POST['user_acc_nodragdrop']), 'boolean');
                dotclear()->auth()->user_prefs->interface->put('theme', $_POST['user_ui_theme'], 'string');
                dotclear()->auth()->user_prefs->interface->put('enhanceduploader', !empty($_POST['user_ui_enhanceduploader']), 'boolean');
                dotclear()->auth()->user_prefs->interface->put('blank_preview', !empty($_POST['user_ui_blank_preview']), 'boolean');
                dotclear()->auth()->user_prefs->interface->put('hidemoreinfo', !empty($_POST['user_ui_hidemoreinfo']), 'boolean');
                dotclear()->auth()->user_prefs->interface->put('hidehelpbutton', !empty($_POST['user_ui_hidehelpbutton']), 'boolean');
                dotclear()->auth()->user_prefs->interface->put('showajaxloader', !empty($_POST['user_ui_showajaxloader']), 'boolean');
                dotclear()->auth()->user_prefs->interface->put('htmlfontsize', $_POST['user_ui_htmlfontsize'], 'string');
                if (dotclear()->auth()->isSuperAdmin()) {
                    # Applied to all users
                    dotclear()->auth()->user_prefs->interface->put('hide_std_favicon', !empty($_POST['user_ui_hide_std_favicon']), 'boolean', null, true, true);
                }
                dotclear()->auth()->user_prefs->interface->put('media_nb_last_dirs', (int) $_POST['user_ui_media_nb_last_dirs'], 'integer');
                dotclear()->auth()->user_prefs->interface->put('media_last_dirs', [], 'array', null, false);
                dotclear()->auth()->user_prefs->interface->put('media_fav_dirs', [], 'array', null, false);
                dotclear()->auth()->user_prefs->interface->put('nocheckadblocker', !empty($_POST['user_ui_nocheckadblocker']), 'boolean');

                # Update user columns (lists)
                $cu = [];
                foreach ($this->cols as $col_type => $cols_list) {
                    $ct = [];
                    foreach ($cols_list[1] as $col_name => $col_data) {
                        $ct[$col_name] = isset($_POST['cols_' . $col_type]) && in_array($col_name, $_POST['cols_' . $col_type], true) ? true : false;
                    }
                    if (count($ct)) {   // @phpstan-ignore-line
                        $cu[$col_type] = $ct;
                    }
                }
                dotclear()->auth()->user_prefs->interface->put('cols', $cu, 'array');

                # Update user lists options
                $su = [];
                foreach ($this->sorts as $sort_type => $sort_data) {
                    if (null !== $sort_data[1]) {
                        $k = 'sorts_' . $sort_type . '_sortby';

                        $su[$sort_type][0] = isset($_POST[$k]) && in_array($_POST[$k], $sort_data[1]) ? $_POST[$k] : $sort_data[2];
                    }
                    if (null !== $sort_data[3]) {
                        $k = 'sorts_' . $sort_type . '_order';

                        $su[$sort_type][1] = isset($_POST[$k]) && in_array($_POST[$k], ['asc', 'desc']) ? $_POST[$k] : $sort_data[3];
                    }
                    if (null !== $sort_data[4]) {
                        $k = 'sorts_' . $sort_type . '_nb';

                        $su[$sort_type][2] = isset($_POST[$k]) ? abs((int) $_POST[$k]) : $sort_data[4][1];
                    }
                }
                dotclear()->auth()->user_prefs->interface->put('sorts', $su, 'array');
                // All filters
                dotclear()->auth()->user_prefs->interface->put('auto_filter', !empty($_POST['user_ui_auto_filter']), 'boolean');

                # Update user xhtml editor flags
                $rf = [];
                foreach ($this->rte as $rk => $rv) {
                    $rf[$rk] = isset($_POST['rte_flags']) && in_array($rk, $_POST['rte_flags'], true) ? true : false;
                }
                dotclear()->auth()->user_prefs->interface->put('rte_flags', $rf, 'array');

                # Update user
                dotclear()->users()->updUser(dotclear()->auth()->userID(), $cur);

                # --BEHAVIOR-- adminAfterUserOptionsUpdate
                dotclear()->behavior()->call('adminAfterUserOptionsUpdate', $cur, dotclear()->auth()->userID());

                dotclear()->notice()->addSuccessNotice(__('Personal options has been successfully updated.'));
                dotclear()->adminurl()->redirect('admin.user.pref', [], '#user-options');
            } catch (\Exception $e) {
                dotclear()->error()->add($e->getMessage());
            }
        }

        # Dashboard options
        if (isset($_POST['db-options'])) {
            try {
                # --BEHAVIOR-- adminBeforeUserOptionsUpdate
                dotclear()->behavior()->call('adminBeforeDashboardOptionsUpdate', dotclear()->auth()->userID());

                # Update user prefs
                dotclear()->auth()->user_prefs->dashboard->put('doclinks', !empty($_POST['user_dm_doclinks']), 'boolean');
                dotclear()->auth()->user_prefs->dashboard->put('dcnews', !empty($_POST['user_dm_dcnews']), 'boolean');
                dotclear()->auth()->user_prefs->dashboard->put('quickentry', !empty($_POST['user_dm_quickentry']), 'boolean');
                dotclear()->auth()->user_prefs->dashboard->put('nofavicons', empty($_POST['user_dm_nofavicons']), 'boolean');
                if (dotclear()->auth()->isSuperAdmin()) {
                    dotclear()->auth()->user_prefs->dashboard->put('nodcupdate', !empty($_POST['user_dm_nodcupdate']), 'boolean');
                }
                dotclear()->auth()->user_prefs->interface->put('iconset', (!empty($_POST['user_ui_iconset']) ? $_POST['user_ui_iconset'] : ''));
                dotclear()->auth()->user_prefs->interface->put('nofavmenu', empty($_POST['user_ui_nofavmenu']), 'boolean');

                # --BEHAVIOR-- adminAfterUserOptionsUpdate
                dotclear()->behavior()->call('adminAfterDashboardOptionsUpdate', dotclear()->auth()->userID());

                dotclear()->notice()->addSuccessNotice(__('Dashboard options has been successfully updated.'));
                dotclear()->adminurl()->redirect('admin.user.pref', [], '#user-favorites');
            } catch (\Exception $e) {
                dotclear()->error()->add($e->getMessage());
            }
        }

        # Add selected favorites
        if (!empty($_POST['appendaction'])) {
            try {
                if (empty($_POST['append'])) {
                    throw new AdminException(__('No favorite selected'));
                }
                $user_favs = dotclear()->favorite()->getFavoriteIDs(false);
                foreach ($_POST['append'] as $k => $v) {
                    if (dotclear()->favorite()->exists($v)) {
                        $user_favs[] = $v;
                    }
                }
                dotclear()->favorite()->setFavoriteIDs($user_favs, false);

                if (!dotclear()->error()->flag()) {
                    dotclear()->notice()->addSuccessNotice(__('Favorites have been successfully added.'));
                    dotclear()->adminurl()->redirect('admin.user.pref', [], '#user-favorites');
                }
            } catch (\Exception $e) {
                dotclear()->error()->add($e->getMessage());
            }
        }

        # Delete selected favorites
        if (!empty($_POST['removeaction'])) {
            try {
                if (empty($_POST['remove'])) {
                    throw new AdminException(__('No favorite selected'));
                }
                $user_fav_ids = [];
                foreach (dotclear()->favorite()->getFavoriteIDs(false) as $v) {
                    $user_fav_ids[$v] = true;
                }
                foreach ($_POST['remove'] as $v) {
                    if (isset($user_fav_ids[$v])) {
                        unset($user_fav_ids[$v]);
                    }
                }
                dotclear()->favorite()->setFavoriteIDs(array_keys($user_fav_ids), false);
                if (!dotclear()->error()->flag()) {
                    dotclear()->notice()->addSuccessNotice(__('Favorites have been successfully removed.'));
                    dotclear()->adminurl()->redirect('admin.user.pref', [], '#user-favorites');
                }
            } catch (\Exception $e) {
                dotclear()->error()->add($e->getMessage());
            }
        }

        # Order favs
        $order = [];
        if (empty($_POST['favs_order']) && !empty($_POST['order'])) {
            $order = $_POST['order'];
            asort($order);
            $order = array_keys($order);
        } elseif (!empty($_POST['favs_order'])) {
            $order = explode(',', $_POST['favs_order']);
        }

        if (!empty($_POST['saveorder']) && !empty($order)) {
            foreach ($order as $k => $v) {
                if (!dotclear()->favorite()->exists($v)) {
                    unset($order[$k]);
                }
            }
            dotclear()->favorite()->setFavoriteIDs($order, false);
            if (!dotclear()->error()->flag()) {
                dotclear()->notice()->addSuccessNotice(__('Favorites have been successfully updated.'));
                dotclear()->adminurl()->redirect('admin.user.pref', [], '#user-favorites');
            }
        }

        # Replace default favorites by current set (super admin only)
        if (!empty($_POST['replace']) && dotclear()->auth()->isSuperAdmin()) {
            $user_favs = dotclear()->favorite()->getFavoriteIDs(false);
            dotclear()->favorite()->setFavoriteIDs($user_favs, true);

            if (!dotclear()->error()->flag()) {
                dotclear()->notice()->addSuccessNotice(__('Default favorites have been successfully updated.'));
                dotclear()->adminurl()->redirect('admin.user.pref', [], '#user-favorites');
            }
        }

        # Reset dashboard items order
        if (!empty($_POST['resetorder'])) {
            dotclear()->auth()->user_prefs->dashboard->drop('main_order');
            dotclear()->auth()->user_prefs->dashboard->drop('boxes_order');
            dotclear()->auth()->user_prefs->dashboard->drop('boxes_items_order');
            dotclear()->auth()->user_prefs->dashboard->drop('boxes_contents_order');

            if (!dotclear()->error()->flag()) {
                dotclear()->notice()->addSuccessNotice(__('Dashboard items order have been successfully reset.'));
                dotclear()->adminurl()->redirect('admin.user.pref', [], '#user-favorites');
            }
        }


        # Page setup
        if (!$this->user_acc_nodragdrop) {
            $this->setPageHead(static::jsLoad('js/_preferences-dragdrop.js'));
        }
        $this
            ->setPageTitle($page_title)
            ->setpageHelp()
            ->setpageHelp('core_user_pref')
            ->setPageHead(
                static::jsLoad('js/jquery/jquery-ui.custom.js') .
                static::jsLoad('js/jquery/jquery.ui.touch-punch.js') .
                static::jsJson('pwstrength', [
                    'min' => sprintf(__('Password strength: %s'), __('weak')),
                    'avg' => sprintf(__('Password strength: %s'), __('medium')),
                    'max' => sprintf(__('Password strength: %s'), __('strong')),
                ]) .
                static::jsLoad('js/pwstrength.js') .
                static::jsLoad('js/_preferences.js') .
                static::jsPageTabs($default_tab) .
                static::jsConfirmClose('user-form', 'opts-forms', 'favs-form', 'db-forms') .

                # --BEHAVIOR-- adminPreferencesHeaders
                dotclear()->behavior()->call('adminPreferencesHeaders')
            )
            ->setPageBreadcrumb([
                Html::escapeHTML(dotclear()->auth()->userID()) => '',
                $page_title                                   => '',
            ])
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        $editors_combo = dotclear()->combo()->getEditorsCombo();
        $editors       = array_keys($editors_combo);

        $iconsets_combo = dotclear()->combo()->getIconsetCombo();

        # Themes
        $theme_combo = [
            __('Light')     => 'light',
            __('Dark')      => 'dark',
            __('Automatic') => '',
        ];

        # Body base font size (37.5% = 6px, 50% = 8px, 62.5% = 10px, 75% = 12px, 87.5% = 14px)
        $htmlfontsize_combo = [
            __('Smallest') => '37.5%',
            __('Smaller')  => '50%',
            __('Default')  => '62.5%',
            __('Larger')   => '75%',
            __('Largest')  => '87.5%',
        ];

        $auto_filter = dotclear()->auth()->user_prefs->interface->auto_filter;

        # User profile
        echo '<div class="multi-part" id="user-profile" title="' . __('My profile') . '">';

        echo
        '<h3>' . __('My profile') . '</h3>' .
        '<form action="' . dotclear()->adminurl()->get('admin.user.pref') . '" method="post" id="user-form">' .

        '<p><label for="user_name">' . __('Last Name:') . '</label>' .
        Form::field('user_name', 20, 255, [
            'default'      => Html::escapeHTML($this->container->getName()),
            'autocomplete' => 'family-name',
        ]) .
        '</p>' .

        '<p><label for="user_firstname">' . __('First Name:') . '</label>' .
        Form::field('user_firstname', 20, 255, [
            'default'      => Html::escapeHTML($this->container->getFirstname()),
            'autocomplete' => 'given-name',
        ]) .
        '</p>' .

        '<p><label for="user_displayname">' . __('Display name:') . '</label>' .
        Form::field('user_displayname', 20, 255, [
            'default'      => Html::escapeHTML($this->container->getDisplayname()),
            'autocomplete' => 'nickname',
        ]) .
        '</p>' .

        '<p><label for="user_email">' . __('Email:') . '</label>' .
        Form::email('user_email', [
            'default'      => Html::escapeHTML($this->container->getEmail()),
            'autocomplete' => 'email',
        ]) .
        '</p>' .

        '<p><label for="user_profile_mails">' . __('Alternate emails (comma separated list):') . '</label>' .
        Form::field('user_profile_mails', 50, 255, [
            'default' => Html::escapeHTML($this->user_profile_mails),
        ]) .
        '</p>' .
        '<p class="form-note info" id="sanitize_emails">' . __('Invalid emails will be automatically removed from list.') . '</p>' .

        '<p><label for="user_url">' . __('URL:') . '</label>' .
        Form::url('user_url', [
            'size'         => 30,
            'default'      => Html::escapeHTML($this->container->getURL()),
            'autocomplete' => 'url',
        ]) .
        '</p>' .

        '<p><label for="user_profile_urls">' . __('Alternate URLs (comma separated list):') . '</label>' .
        Form::field('user_profile_urls', 50, 255, [
            'default' => Html::escapeHTML($this->user_profile_urls),
        ]) .
        '</p>' .
        '<p class="form-note info" id="sanitize_urls">' . __('Invalid URLs will be automatically removed from list.') . '</p>' .

        '<p><label for="user_lang">' . __('Language for my interface:') . '</label>' .
        Form::combo('user_lang', dotclear()->combo()->getAdminLangsCombo(), $this->container->getlang(), 'l10n') . '</p>' .

        '<p><label for="user_tz">' . __('My timezone:') . '</label>' .
        Form::combo('user_tz', Dt::getZones(true, true), $this->container->getTZ()) . '</p>';

        if (dotclear()->auth()->allowPassChange()) {
            echo
            '<h4 class="vertical-separator pretty-title">' . __('Change my password') . '</h4>' .

            '<p><label for="new_pwd">' . __('New password:') . '</label>' .
            Form::password(
                'new_pwd',
                20,
                255,
                [
                    'class'        => 'pw-strength',
                    'autocomplete' => 'new-password', ]
            ) .
            '</p>' .

            '<p><label for="new_pwd_c">' . __('Confirm new password:') . '</label>' .
            Form::password(
                'new_pwd_c',
                20,
                255,
                [
                    'autocomplete' => 'new-password', ]
            ) . '</p>' .

            '<p><label for="cur_pwd">' . __('Your current password:') . '</label>' .
            Form::password(
                'cur_pwd',
                20,
                255,
                [
                    'autocomplete' => 'current-password',
                    'extra_html'   => 'aria-describedby="cur_pwd_help"',
                ]
            ) . '</p>' .
            '<p class="form-note warn" id="cur_pwd_help">' .
            __('If you have changed your email or password you must provide your current password to save these modifications.') .
                '</p>';
        }

        echo
        '<p class="clear vertical-separator">' .
        dotclear()->nonce()->form() .
        '<input type="submit" accesskey="s" value="' . __('Update my profile') . '" />' .
        ' <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />' .
            '</p>' .
            '</form>' .

            '</div>';

        # User options : some from actual user profile, dashboard modules, ...
        echo '<div class="multi-part" id="user-options" title="' . __('My options') . '">';

        echo
        '<form action="' . dotclear()->adminurl()->get('admin.user.pref') . '#user-options" method="post" id="opts-forms">' .
        '<h3>' . __('My options') . '</h3>';

        echo
        '<div class="fieldset">' .
        '<h4 id="user_options_interface">' . __('Interface') . '</h4>' .

        '<p><label for="user_ui_theme" class="classic">' . __('Theme:') . '</label>' . ' ' .
        Form::combo('user_ui_theme', $theme_combo, $this->user_ui_theme) . '</p>' .

        '<p><label for="user_ui_enhanceduploader" class="classic">' .
        Form::checkbox('user_ui_enhanceduploader', 1, $this->user_ui_enhanceduploader) . ' ' .
        __('Activate enhanced uploader in media manager') . '</label></p>' .

        '<p><label for="user_ui_blank_preview" class="classic">' .
        Form::checkbox('user_ui_blank_preview', 1, $this->user_ui_blank_preview) . ' ' .
        __('Preview the entry being edited in a blank window or tab (depending on your browser settings).') . '</label></p>' .

        '<p><label for="user_acc_nodragdrop" class="classic">' .
        Form::checkbox('user_acc_nodragdrop', 1, $this->user_acc_nodragdrop, '', '', false, 'aria-describedby="user_acc_nodragdrop_help"') . ' ' .
        __('Disable javascript powered drag and drop for ordering items') . '</label></p>' .
        '<p class="clear form-note" id="user_acc_nodragdrop_help">' . __('If checked, numeric fields will allow to type the elements\' ordering number.') . '</p>' .

        '<p><label for="user_ui_hidemoreinfo" class="classic">' .
        Form::checkbox('user_ui_hidemoreinfo', 1, $this->user_ui_hidemoreinfo) . ' ' .
        __('Hide all secondary information and notes') . '</label></p>' .

        '<p><label for="user_ui_hidehelpbutton" class="classic">' .
        Form::checkbox('user_ui_hidehelpbutton', 1, $this->user_ui_hidehelpbutton) . ' ' .
        __('Hide help button') . '</label></p>' .

        '<p><label for="user_ui_showajaxloader" class="classic">' .
        Form::checkbox('user_ui_showajaxloader', 1, $this->user_ui_showajaxloader) . ' ' .
        __('Show asynchronous requests indicator') . '</label></p>' .

        '<p><label for="user_ui_htmlfontsize" class="classic">' . __('Font size:') . '</label>' . ' ' .
        Form::combo('user_ui_htmlfontsize', $htmlfontsize_combo, $this->user_ui_htmlfontsize) . '</p>';

        echo
        '<p><label for="user_ui_media_nb_last_dirs" class="classic">' . __('Number of recent folders proposed in media manager:') . '</label> ' .
        Form::number('user_ui_media_nb_last_dirs', 0, 999, (string) $this->user_ui_media_nb_last_dirs, '', '', false, 'aria-describedby="user_ui_media_nb_last_dirs_help"') . '</p>' .
        '<p class="clear form-note" id="user_ui_media_nb_last_dirs_help">' . __('Leave empty to ignore, displayed only if Javascript is enabled in your browser.') . '</p>';

        if (dotclear()->auth()->isSuperAdmin()) {
            echo
            '<p><label for="user_ui_hide_std_favicon" class="classic">' .
            Form::checkbox('user_ui_hide_std_favicon', 1, $this->user_ui_hide_std_favicon, '', '', false, 'aria-describedby="user_ui_hide_std_favicon_help"') . ' ' .
            __('Do not use standard favicon') . '</label> ' .
            '<span class="clear form-note warn" id="user_ui_hide_std_favicon_help">' . __('This will be applied for all users') . '.</span>' .
                '</p>'; //Opera sucks;
        }

        echo
        '<p><label for="user_ui_nocheckadblocker" class="classic">' .
        Form::checkbox('user_ui_nocheckadblocker', 1, $this->user_ui_nocheckadblocker, '', '', false, 'aria-describedby="user_ui_nocheckadblocker_help"') . ' ' .
        __('Disable Ad-blocker check') . '</label></p>' .
        '<p class="clear form-note" id="user_ui_nocheckadblocker_help">' . __('Some ad-blockers (Ghostery, Adblock plus, uBloc origin, …) may interfere with some feature as inserting link or media in entries with CKEditor; in this case you should disable it for this Dotclear installation (backend only). Note that Dotclear do not add ads ot trackers in the backend.') . '</p>';

        echo
            '</div>';

        echo
        '<div class="fieldset">' .
        '<h4 id="user_options_columns">' . __('Optional columns displayed in lists') . '</h4>';
        $odd = true;
        foreach ($this->cols as $col_type => $col_list) {
            echo '<div class="two-boxes ' . ($odd ? 'odd' : 'even') . '">';
            echo '<h5>' . $col_list[0] . '</h5>';
            foreach ($col_list[1] as $col_name => $col_data) {
                echo
                '<p><label for="cols_' . $col_type . '-' . $col_name . '" class="classic">' .
                Form::checkbox(['cols_' . $col_type . '[]', 'cols_' . $col_type . '-' . $col_name], $col_name, $col_data[0]) . $col_data[1] . '</label>';
            }
            echo '</div>';
            $odd = !$odd;
        }
        echo '</div>';

        echo
        '<div class="fieldset">' .
        '<h4 id="user_options_lists">' . __('Options for lists') . '</h4>' .
        '<p><label for="user_ui_auto_filter" class="classic">' .
        Form::checkbox('user_ui_auto_filter', 1, $auto_filter) . ' ' .
        __('Apply filters on the fly') . '</label></p>';

        $odd = true;
        foreach ($this->sorts as $sort_type => $sort_data) {
            if ($odd) {
                echo '<hr />';
            }
            echo '<div class="two-boxes ' . ($odd ? 'odd' : 'even') . '">';
            echo '<h5>' . $sort_data[0] . '</h5>';
            if (null !== $sort_data[1]) {
                echo
                '<p class="field"><label for="sorts_' . $sort_type . '_sortby">' . __('Order by:') . '</label> ' .
                Form::combo('sorts_' . $sort_type . '_sortby', $sort_data[1], $sort_data[2]) . '</p>';
            }
            if (null !== $sort_data[3]) {
                echo
                '<p class="field"><label for="sorts_' . $sort_type . '_order">' . __('Sort:') . '</label> ' .
                Form::combo('sorts_' . $sort_type . '_order', dotclear()->combo()->getOrderCombo(), $sort_data[3]) . '</p>';
            }
            if (is_array($sort_data[4])) {
                echo
                '<p><span class="label ib">' . __('Show') . '</span> <label for="sorts_' . $sort_type . '_nb" class="classic">' .
                Form::number('sorts_' . $sort_type . '_nb', 0, 999, (string) $sort_data[4][1]) . ' ' .
                $sort_data[4][0] . '</label></p>';
            }
            echo '</div>';
            $odd = !$odd;
        }
        echo '</div>';

        echo
        '<div class="fieldset">' .
        '<h4 id="user_options_edition">' . __('Edition') . '</h4>';

        echo '<div class="two-boxes odd">';
        $user_editors = $this->container->getOption('editor');
        foreach ($this->format_by_editors as $format => $editors) {
            echo
            '<p class="field"><label for="user_editor_' . $format . '">' . sprintf(__('Preferred editor for %s:'), $format) . '</label>' .
            Form::combo(
                ['user_editor[' . $format . ']', 'user_editor_' . $format],
                array_merge([__('Choose an editor') => ''], $editors),
                $user_editors[$format]
            ) . '</p>';
        }
        echo
        '<p class="field"><label for="user_post_format">' . __('Preferred format:') . '</label>' .
        Form::combo('user_post_format', $this->available_formats, $this->container->getOption('post_format')) . '</p>';

        echo
        '<p class="field"><label for="user_post_status">' . __('Default entry status:') . '</label>' .
        Form::combo('user_post_status', dotclear()->combo()->getPostStatusesCombo(), $this->container->getPostStatus()) . '</p>' .

        '<p class="field"><label for="user_edit_size">' . __('Entry edit field height:') . '</label>' .
        Form::number('user_edit_size', 10, 999, (string) $this->container->getOption('edit_size')) . '</p>' .

        '<p><label for="user_wysiwyg" class="classic">' .
        Form::checkbox('user_wysiwyg', 1, $this->container->getOption('enable_wysiwyg')) . ' ' .
        __('Enable WYSIWYG mode') . '</label></p>' .

        '<p><label for="user_toolbar_bottom" class="classic">' .
        Form::checkbox('user_toolbar_bottom', 1, $this->container->getOption('toolbar_bottom')) . ' ' .
        __('Display editor\'s toolbar at bottom of textarea (if possible)') . '</label></p>' .

            '</div>';

        echo '<div class="two-boxes even">';
        echo '<h5>' . __('Use xhtml editor for:') . '</h5>';
        foreach ($this->rte as $rk => $rv) {
            echo
            '<p><label for="rte_' . $rk . '" class="classic">' .
            Form::checkbox(['rte_flags[]', 'rte_' . $rk], $rk, $rv[0]) . $rv[1] . '</label>';
        }
        echo '</div>';

        echo '</div>'; // fieldset

        echo
        '<h4 class="pretty-title">' . __('Other options') . '</h4>';

        # --BEHAVIOR-- adminPreferencesForm
        dotclear()->behavior()->call('adminPreferencesForm');

        echo
        '<p class="clear vertical-separator">' .
        dotclear()->nonce()->form() .
        '<input name="user_options_submit" type="submit" accesskey="s" value="' . __('Save my options') . '" />' .
        ' <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />' .
            '</p>' .
            '</form>';

        echo '</div>';

        # My dashboard
        echo '<div class="multi-part" id="user-favorites" title="' . __('My dashboard') . '">';
        echo '<h3>' . __('My dashboard') . '</h3>';

        # Favorites
        echo '<form action="' . dotclear()->adminurl()->get('admin.user.pref') . '" method="post" id="favs-form" class="two-boxes odd">';

        echo '<div id="my-favs" class="fieldset"><h4>' . __('My favorites') . '</h4>';

        $count    = 0;
        $user_fav = dotclear()->favorite()->getFavoriteIDs(false);
        foreach ($user_fav as $id) {
            $fav = dotclear()->favorite()->getFavorite($id);
            if (!empty($fav)) {
                // User favorites only
                if ($count == 0) {
                    echo '<ul class="fav-list">';
                }

                $count++;
                $icon = dotclear()->summary()->getIconTheme($fav['small-icon']);
                $zoom = dotclear()->summary()->getIconTheme($fav['large-icon'], false);
                if ($zoom !== '') {
                    $icon .= ' <span class="zoom">' . $zoom . '</span>';
                }
                echo '<li id="fu-' . $id . '">' . '<label for="fuk-' . $id . '">' . $icon .
                Form::number(['order[' . $id . ']'], [
                    'min'        => 1,
                    'max'        => count($user_fav),
                    'default'    => (string) $count,
                    'class'      => 'position',
                    'extra_html' => 'title="' . sprintf(__('position of %s'), $fav['title']) . '"',
                ]) .
                Form::hidden(['dynorder[]', 'dynorder-' . $id . ''], $id) .
                Form::checkbox(['remove[]', 'fuk-' . $id], $id) . __($fav['title']) . '</label>' .
                    '</li>';
            }
        }
        if ($count > 0) {
            echo '</ul>';
        }

        if ($count > 0) {
            echo
            '<div class="clear">' .
            '<p>' . Form::hidden('favs_order', '') .
            dotclear()->nonce()->form() .
            '<input type="submit" name="saveorder" value="' . __('Save order') . '" /> ' .

            '<input type="submit" class="delete" name="removeaction" ' .
            'value="' . __('Delete selected favorites') . '" ' .
            'onclick="return window.confirm(\'' . Html::escapeJS(
                __('Are you sure you want to remove selected favorites?')
            ) . '\');" /></p>' .

                (dotclear()->auth()->isSuperAdmin() ?
                '<div class="info">' .
                '<p>' . __('If you are a super administrator, you may define this set of favorites to be used by default on all blogs of this installation.') . '</p>' .
                '<p><input class="reset" type="submit" name="replace" value="' . __('Define as default favorites') . '" />' . '</p>' .
                '</div>'
                :
                '') .

                '</div>';
        } else {
            echo
            '<p>' . __('Currently no personal favorites.') . '</p>';
        }

        $avail_fav       = dotclear()->favorite()->getFavorites(dotclear()->favorite()->getAvailableFavoritesIDs());
        $default_fav_ids = [];
        foreach (dotclear()->favorite()->getFavoriteIDs(true) as $v) {
            $default_fav_ids[$v] = true;
        }
        echo '</div>'; # /box my-fav

        echo '<div class="fieldset" id="available-favs">';
        # Available favorites
        echo '<h5 class="pretty-title">' . __('Other available favorites') . '</h5>';
        $count = 0;
        uasort($avail_fav, function ($a, $b) {
            return strcoll(
                strtolower(Utils::removeDiacritics($a['title'])),
                strtolower(Utils::removeDiacritics($b['title']))
            );
        });

        foreach ($avail_fav as $k => $v) {
            if (in_array($k, $user_fav)) {
                unset($avail_fav[$k]);
            }
        }
        foreach ($avail_fav as $k => $fav) {
            if ($count == 0) {
                echo '<ul class="fav-list">';
            }

            $count++;
            $icon = dotclear()->summary()->getIconTheme($fav['small-icon']);
            $zoom = dotclear()->summary()->getIconTheme($fav['large-icon'], false);
            if ($zoom !== '') {
                $icon .= ' <span class="zoom">' . $zoom . '</span>';
            }
            echo '<li id="fa-' . $k . '">' . '<label for="fak-' . $k . '">' . $icon .
            Form::checkbox(['append[]', 'fak-' . $k], $k) .
                $fav['title'] . '</label>' .
                (isset($default_fav_ids[$k]) ? ' <span class="default-fav"><img src="?df=images/selected.png" alt="' . __('(default favorite)') . '" /></span>' : '') .
                '</li>';
        }
        if ($count > 0) {
            echo '</ul>';
        }

        echo
        '<p>' .
        dotclear()->nonce()->form() .
        '<input type="submit" name="appendaction" value="' . __('Add to my favorites') . '" /></p>';
        echo '</div>'; # /available favorites

        echo '</form>';

        # Dashboard items
        echo
        '<form action="' . dotclear()->adminurl()->get('admin.user.pref') . '" method="post" id="db-forms" class="two-boxes even">' .

        '<div class="fieldset">' .
        '<h4>' . __('Menu') . '</h4>' .
        '<p><label for="user_ui_nofavmenu" class="classic">' .
        Form::checkbox('user_ui_nofavmenu', 1, !$this->user_ui_nofavmenu) . ' ' .
        __('Display favorites at the top of the menu') . '</label></p></div>';

        echo
        '<div class="fieldset">' .
        '<h4>' . __('Dashboard icons') . '</h4>' .
        '<p><label for="user_dm_nofavicons" class="classic">' .
        Form::checkbox('user_dm_nofavicons', 1, !$this->user_dm_nofavicons) . ' ' .
        __('Display dashboard icons') . '</label></p>';


        if (count($iconsets_combo) > 1) {
            echo
            '<p><label for="user_ui_iconset" class="classic">' . __('Iconset:') . '</label> ' .
            Form::combo('user_ui_iconset', $iconsets_combo, $this->user_ui_iconset) . '</p>';
        } else {
            echo '<p class="hidden">' . Form::hidden('user_ui_iconset', '') . '</p>';
        }
        echo
            '</div>';

        echo
        '<div class="fieldset">' .
        '<h4>' . __('Dashboard modules') . '</h4>' .

        '<p><label for="user_dm_doclinks" class="classic">' .
        Form::checkbox('user_dm_doclinks', 1, $this->user_dm_doclinks) . ' ' .
        __('Display documentation links') . '</label></p>' .

        '<p><label for="user_dm_dcnews" class="classic">' .
        Form::checkbox('user_dm_dcnews', 1, $this->user_dm_dcnews) . ' ' .
        __('Display Dotclear news') . '</label></p>' .

        '<p><label for="user_dm_quickentry" class="classic">' .
        Form::checkbox('user_dm_quickentry', 1, $this->user_dm_quickentry) . ' ' .
        __('Display quick entry form') . '</label></p>';

        if (dotclear()->auth()->isSuperAdmin()) {
            echo
            '<p><label for="user_dm_nodcupdate" class="classic">' .
            Form::checkbox('user_dm_nodcupdate', 1, $this->user_dm_nodcupdate) . ' ' .
            __('Do not display Dotclear updates') . '</label></p>';
        }

        echo '</div>';

        # --BEHAVIOR-- adminDashboardOptionsForm
        dotclear()->behavior()->call('adminDashboardOptionsForm', dotclear());

        echo
        '<p>' .
        Form::hidden('db-options', '-') .
        dotclear()->nonce()->form() .
        '<input type="submit" accesskey="s" value="' . __('Save my dashboard options') . '" />' .
        ' <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />' .
            '</p>' .
            '</form>';

        # Dashboard items order (reset)
        echo '<form action="' . dotclear()->adminurl()->get('admin.user.pref') . '" method="post" id="order-reset" class="two-boxes even">';
        echo '<div class="fieldset"><h4>' . __('Dashboard items order') . '</h4>';
        echo
        '<p>' .
        dotclear()->nonce()->form() .
        '<input type="submit" name="resetorder" value="' . __('Reset dashboard items order') . '" /></p>';
        echo '</div>';
        echo '</form>';

        echo '</div>'; # /multipart-user-favorites
    }
}
