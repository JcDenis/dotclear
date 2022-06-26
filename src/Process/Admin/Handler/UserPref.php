<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Handler;

// Dotclear\Process\Admin\Handler\UserPref
use Dotclear\App;
use Dotclear\Core\User\UserContainer;
use Dotclear\Core\User\Preference\RteFlags;
use Dotclear\Database\Param;
use Dotclear\Exception\AdminException;
use Dotclear\Helper\Clock;
use Dotclear\Helper\GPC\GPC;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Lexical;
use Dotclear\Helper\Mapper\Strings;
use Dotclear\Process\Admin\Page\AbstractPage;
use Exception;

/**
 * Admin user preferences page.
 *
 * @ingroup  Admin User Preference handler
 */
class UserPref extends AbstractPage
{
    /**
     * @var UserContainer $user
     *                    User container
     */
    private $user;

    /**
     * @var string $user_profile_mails
     *             User other emails (comma separated list )
     */
    private $user_profile_mails = '';

    /**
     * @var string $user_profile_urls
     *             User other URLs (comma separated list )
     */
    private $user_profile_urls = '';

    private $format_by_editors = [];
    private $available_formats = [];

    private $rte;

    private $user_dm_doclinks           = '';
    private $user_dm_dcnews             = '';
    private $user_dm_quickentry         = '';
    private $user_dm_nofavicons         = '';
    private $user_dm_nodcupdate         = false;
    private $user_acc_nodragdrop        = false;
    private $user_ui_theme              = '';
    private $user_ui_enhanceduploader   = '';
    private $user_ui_blank_preview      = '';
    private $user_ui_hidemoreinfo       = '';
    private $user_ui_hidehelpbutton     = '';
    private $user_ui_showajaxloader     = '';
    private $user_ui_htmlfontsize       = '';
    private $user_ui_hide_std_favicon   = false;
    private $user_ui_nofavmenu          = '';
    private $user_ui_media_nb_last_dirs = '';
    private $user_ui_nocheckadblocker   = '';

    protected function getPermissions(): string|bool
    {
        return 'usage,contentadmin';
    }

    protected function getPagePrepend(): ?bool
    {
        $page_title = __('My preferences');

        $param = new Param();
        $param->set('user_id', App::core()->user()->userID());

        $this->user = new UserContainer(App::core()->users()->getUsers(param: $param));
        unset($param);

        if (empty($this->user->getOption('editor'))) {
            $this->user->setOption('editor', []);
        }

        $this->user_profile_mails = App::core()->user()->preference()->get('profile')->get('mails');
        $this->user_profile_urls  = App::core()->user()->preference()->get('profile')->get('urls');

        $this->user_dm_doclinks   = App::core()->user()->preference()->get('dashboard')->get('doclinks');
        $this->user_dm_dcnews     = App::core()->user()->preference()->get('dashboard')->get('dcnews');
        $this->user_dm_quickentry = App::core()->user()->preference()->get('dashboard')->get('quickentry');
        $this->user_dm_nofavicons = App::core()->user()->preference()->get('dashboard')->get('nofavicons');
        $this->user_dm_nodcupdate = false;
        if (App::core()->user()->isSuperAdmin()) {
            $this->user_dm_nodcupdate = App::core()->user()->preference()->get('dashboard')->get('nodcupdate');
        }

        $this->user_acc_nodragdrop = App::core()->user()->preference()->get('accessibility')->get('nodragdrop');

        $this->user_ui_theme            = App::core()->user()->preference()->get('interface')->get('theme');
        $this->user_ui_enhanceduploader = App::core()->user()->preference()->get('interface')->get('enhanceduploader');
        $this->user_ui_blank_preview    = App::core()->user()->preference()->get('interface')->get('blank_preview');
        $this->user_ui_hidemoreinfo     = App::core()->user()->preference()->get('interface')->get('hidemoreinfo');
        $this->user_ui_hidehelpbutton   = App::core()->user()->preference()->get('interface')->get('hidehelpbutton');
        $this->user_ui_showajaxloader   = App::core()->user()->preference()->get('interface')->get('showajaxloader');
        $this->user_ui_htmlfontsize     = App::core()->user()->preference()->get('interface')->get('htmlfontsize');
        $this->user_ui_hide_std_favicon = false;
        if (App::core()->user()->isSuperAdmin()) {
            $this->user_ui_hide_std_favicon = App::core()->user()->preference()->get('interface')->get('hide_std_favicon');
        }
        $this->user_ui_nofavmenu          = App::core()->user()->preference()->get('interface')->get('nofavmenu');
        $this->user_ui_media_nb_last_dirs = App::core()->user()->preference()->get('interface')->get('media_nb_last_dirs');
        $this->user_ui_nocheckadblocker   = App::core()->user()->preference()->get('interface')->get('nocheckadblocker');

        $default_tab = !GPC::get()->empty('tab') ? Html::escapeHTML(GPC::get()->string('tab')) : 'user-profile';

        if (!GPC::get()->empty('append')
            || !GPC::get()->empty('removed')
            || !GPC::get()->empty('neworder')
            || !GPC::get()->empty('replaced')
            || !GPC::post()->empty('appendaction')
            || !GPC::post()->empty('removeaction')
            || !GPC::get()->empty('db-updated')
            || !GPC::post()->empty('resetorder')
        ) {
            $default_tab = 'user-favorites';
        } elseif (!GPC::get()->empty('updated')) {
            $default_tab = 'user-options';
        }
        if (('user-profile' != $default_tab) && ('user-options' != $default_tab) && ('user-favorites' != $default_tab)) {
            $default_tab = 'user-profile';
        }

        // Format by editors
        $formaters               = App::core()->formater()->getFormaters();
        $this->format_by_editors = [];
        foreach ($formaters as $editor => $formats) {
            foreach ($formats as $format) {
                $this->format_by_editors[$format][$editor] = $editor;
            }
        }
        $this->available_formats = ['' => ''];
        foreach (array_keys($this->format_by_editors) as $format) {
            $this->available_formats[$format] = $format;
            $this->user->setOption('editor', array_merge([$format => ''], $this->user->getOption('editor')));
        }

        // Ensure Font size is set to default is empty
        if ('' == $this->user_ui_htmlfontsize) {
            $this->user_ui_htmlfontsize = '62.5%';
        }

        // Get 3rd parts xhtml editor flags
        $this->rte = (new RteFlags())->getFlags();

        // Load user settings
        $rte_flags = @App::core()->user()->preference()->get('interface')->get('rte_flags');
        if (is_array($rte_flags)) {
            foreach ($rte_flags as $fk => $fv) {
                if (isset($this->rte[$fk])) {
                    $this->rte[$fk][0] = $fv;
                }
            }
        }

        // Add or update user
        if (GPC::post()->isset('user_name')) {
            try {
                $pwd_check = App::core()->user()->checkPassword(GPC::post()->string('cur_pwd'));

                if (App::core()->user()->allowPassChange() && !$pwd_check && $this->user->getProperty('user_email') != GPC::post()->string('user_email')) {
                    throw new AdminException(__('If you want to change your email or password you must provide your current password.'));
                }

                $cur = App::core()->con()->openCursor(App::core()->getPrefix() . 'user');

                $cur->setField('user_name', $this->user->setProperty('user_name', GPC::post()->string('user_name')));
                $cur->setField('user_firstname', $this->user->setProperty('user_firstname', GPC::post()->string('user_firstname')));
                $cur->setField('user_displayname', $this->user->setProperty('user_displayname', GPC::post()->string('user_displayname')));
                $cur->setField('user_email', $this->user->setProperty('user_email', GPC::post()->string('user_email')));
                $cur->setField('user_url', $this->user->setProperty('user_url', GPC::post()->string('user_url')));
                $cur->setField('user_lang', $this->user->setProperty('user_lang', GPC::post()->string('user_lang')));
                $cur->setField('user_tz', $this->user->setProperty('user_tz', GPC::post()->string('user_tz')));
                $cur->setField('user_options', $this->user->getOptions());

                if (App::core()->user()->allowPassChange() && !GPC::post()->empty('new_pwd')) {
                    if (!$pwd_check) {
                        throw new AdminException(__('If you want to change your email or password you must provide your current password.'));
                    }

                    if (GPC::post()->string('new_pwd') != GPC::post()->string('new_pwd_c')) {
                        throw new AdminException(__("Passwords don't match"));
                    }

                    $cur->setField('user_pwd', GPC::post()->string('new_pwd'));
                }

                // Update user
                App::core()->users()->updateUser(id: App::core()->user()->userID(), cursor: $cur);

                // Update profile
                // Sanitize list of secondary mails and urls if any
                $mails = $urls = '';
                if (!GPC::post()->empty('user_profile_mails')) {
                    $mails = implode(',', array_filter(filter_var_array(array_map('trim', explode(',', GPC::post()->string('user_profile_mails'))), FILTER_VALIDATE_EMAIL)));
                }
                if (!GPC::post()->empty('user_profile_urls')) {
                    $urls = implode(',', array_filter(filter_var_array(array_map('trim', explode(',', GPC::post()->string('user_profile_urls'))), FILTER_VALIDATE_URL)));
                }
                App::core()->user()->preference()->get('profile')->put('mails', $mails, 'string');
                App::core()->user()->preference()->get('profile')->put('urls', $urls, 'string');

                App::core()->notice()->addSuccessNotice(__('Personal information has been successfully updated.'));

                App::core()->adminurl()->redirect('admin.user.pref');
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
            }
        }

        // Update user options
        if (GPC::post()->isset('user_options_submit')) {
            try {
                $cur = App::core()->con()->openCursor(App::core()->getPrefix() . 'user');

                $cur->setField('user_name', $this->user->getProperty('user_name'));
                $cur->setField('user_firstname', $this->user->getProperty('user_firstname'));
                $cur->setField('user_displayname', $this->user->getProperty('user_displayname'));
                $cur->setField('user_email', $this->user->getProperty('user_email'));
                $cur->setField('user_url', $this->user->getProperty('user_url'));
                $cur->setField('user_lang', $this->user->getProperty('user_lang'));
                $cur->setField('user_tz', $this->user->getProperty('user_tz'));
                $cur->setField('user_post_status', $this->user->setProperty('user_post_status', GPC::post()->string('user_post_status')));

                $this->user->setOption('edit_size', GPC::post()->int('user_edit_size'));
                if ($this->user->getOption('edit_size') < 1) {
                    $this->user->setOption('edit_size', 10);
                }
                $this->user->setOption('post_format', GPC::post()->string('user_post_format'));
                $this->user->setOption('editor', GPC::post()->array('user_editor'));
                $this->user->setOption('enable_wysiwyg', !GPC::post()->empty('user_wysiwyg'));
                $this->user->setOption('toolbar_bottom', !GPC::post()->empty('user_toolbar_bottom'));

                $cur->setField('user_options', $this->user->getOptions());

                // --BEHAVIOR-- adminBeforeUserOptionsUpdate
                App::core()->behavior('adminBeforeUserOptionsUpdate')->call($cur, App::core()->user()->userID());

                // Update user prefs
                App::core()->user()->preference()->get('accessibility')->put('nodragdrop', !GPC::post()->empty('user_acc_nodragdrop'), 'boolean');
                App::core()->user()->preference()->get('interface')->put('theme', GPC::post()->string('user_ui_theme'), 'string');
                App::core()->user()->preference()->get('interface')->put('enhanceduploader', !GPC::post()->empty('user_ui_enhanceduploader'), 'boolean');
                App::core()->user()->preference()->get('interface')->put('blank_preview', !GPC::post()->empty('user_ui_blank_preview'), 'boolean');
                App::core()->user()->preference()->get('interface')->put('hidemoreinfo', !GPC::post()->empty('user_ui_hidemoreinfo'), 'boolean');
                App::core()->user()->preference()->get('interface')->put('hidehelpbutton', !GPC::post()->empty('user_ui_hidehelpbutton'), 'boolean');
                App::core()->user()->preference()->get('interface')->put('showajaxloader', !GPC::post()->empty('user_ui_showajaxloader'), 'boolean');
                App::core()->user()->preference()->get('interface')->put('htmlfontsize', GPC::post()->string('user_ui_htmlfontsize'), 'string');
                if (App::core()->user()->isSuperAdmin()) {
                    // Applied to all users
                    App::core()->user()->preference()->get('interface')->put('hide_std_favicon', !GPC::post()->empty('user_ui_hide_std_favicon'), 'boolean', null, true, true);
                }
                App::core()->user()->preference()->get('interface')->put('media_nb_last_dirs', GPC::post()->int('user_ui_media_nb_last_dirs'), 'integer');
                App::core()->user()->preference()->get('interface')->put('media_last_dirs', [], 'array', null, false);
                App::core()->user()->preference()->get('interface')->put('media_fav_dirs', [], 'array', null, false);
                App::core()->user()->preference()->get('interface')->put('nocheckadblocker', !GPC::post()->empty('user_ui_nocheckadblocker'), 'boolean');

                // Update user columns (lists)
                $cu = [];
                foreach (App::core()->listoption()->column()->getGroups() as $group) {
                    $ct = [];
                    foreach ($group->getItems() as $item) {
                        $ct[$item->id] = in_array($item->id, GPC::post()->array('cols_' . $group->id), true) ? true : false;
                    }
                    if (count($ct)) {
                        $cu[$group->id] = $ct;
                    }
                }
                App::core()->user()->preference()->get('interface')->put('cols', $cu, 'array');

                // Update user lists options
                $su = [];
                foreach (App::core()->listoption()->sort()->getGroups() as $group) {
                    if (null !== $group->getSortby()) {
                        $k = 'sorts_' . $group->id . '_sortby';

                        $su[$group->id][0] = in_array(GPC::post()->string($k), $group->combo) ? GPC::post()->string($k) : $group->getSortby();
                    }
                    if (null !== $group->getSortOrder()) {
                        $k = 'sorts_' . $group->id . '_order';

                        $su[$group->id][1] = in_array(GPC::post()->string($k), App::core()->combo()->getOrderCombo()) ? GPC::post()->string($k) : $group->getSortOrder();
                    }
                    if (null !== $group->getSortLimit()) {
                        $k = 'sorts_' . $group->id . '_nb';

                        $su[$group->id][2] = GPC::post()->isset($k) ? abs(GPC::post()->int($k)) : $group->getSortLimit();
                    }
                }
                App::core()->user()->preference()->get('interface')->put('sorts', $su, 'array');
                // All filters
                App::core()->user()->preference()->get('interface')->put('auto_filter', !GPC::post()->empty('user_ui_auto_filter'), 'boolean');

                // Update user xhtml editor flags
                $rf = [];
                foreach ($this->rte as $rk => $rv) {
                    $rf[$rk] = in_array($rk, GPC::post()->array('rte_flags'), true);
                }
                App::core()->user()->preference()->get('interface')->put('rte_flags', $rf, 'array');

                // Update user
                App::core()->users()->updateUser(id: App::core()->user()->userID(), cursor: $cur);

                // --BEHAVIOR-- adminAfterUserOptionsUpdate
                App::core()->behavior('adminAfterUserOptionsUpdate')->call($cur, App::core()->user()->userID());

                App::core()->notice()->addSuccessNotice(__('Personal options has been successfully updated.'));
                App::core()->adminurl()->redirect('admin.user.pref', [], '#user-options');
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
            }
        }

        // Dashboard options
        if (GPC::post()->isset('db-options')) {
            try {
                // --BEHAVIOR-- adminBeforeUserOptionsUpdate
                App::core()->behavior('adminBeforeDashboardOptionsUpdate')->call(App::core()->user()->userID());

                // Update user prefs
                App::core()->user()->preference()->get('dashboard')->put('doclinks', !GPC::post()->empty('user_dm_doclinks'), 'boolean');
                App::core()->user()->preference()->get('dashboard')->put('dcnews', !GPC::post()->empty('user_dm_dcnews'), 'boolean');
                App::core()->user()->preference()->get('dashboard')->put('quickentry', !GPC::post()->empty('user_dm_quickentry'), 'boolean');
                App::core()->user()->preference()->get('dashboard')->put('nofavicons', GPC::post()->empty('user_dm_nofavicons'), 'boolean');
                if (App::core()->user()->isSuperAdmin()) {
                    App::core()->user()->preference()->get('dashboard')->put('nodcupdate', !GPC::post()->empty('user_dm_nodcupdate'), 'boolean');
                }
                App::core()->user()->preference()->get('interface')->put('nofavmenu', GPC::post()->empty('user_ui_nofavmenu'), 'boolean');

                // --BEHAVIOR-- adminAfterUserOptionsUpdate
                App::core()->behavior('adminAfterDashboardOptionsUpdate')->call(App::core()->user()->userID());

                App::core()->notice()->addSuccessNotice(__('Dashboard options has been successfully updated.'));
                App::core()->adminurl()->redirect('admin.user.pref', [], '#user-favorites');
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
            }
        }

        // Add selected favorites
        if (!GPC::post()->empty('appendaction')) {
            try {
                if (GPC::post()->empty('append')) {
                    throw new AdminException(__('No favorite selected'));
                }
                $user_favs = App::core()->favorite()->getLocalIds();
                foreach (GPC::post()->array('append') as $k => $v) {
                    if (App::core()->favorite()->hasItem($v)) {
                        $user_favs->add($v);
                    }
                }
                App::core()->favorite()->setLocalIds($user_favs);

                if (!App::core()->error()->flag()) {
                    App::core()->notice()->addSuccessNotice(__('Favorites have been successfully added.'));
                    App::core()->adminurl()->redirect('admin.user.pref', [], '#user-favorites');
                }
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
            }
        }

        // Delete selected favorites
        if (!GPC::post()->empty('removeaction')) {
            try {
                if (GPC::post()->empty('remove')) {
                    throw new AdminException(__('No favorite selected'));
                }
                $user_fav_ids = App::core()->favorite()->getLocalIds();
                foreach (GPC::post()->array('remove') as $v) {
                    if ($user_fav_ids->exists($v)) {
                        $user_fav_ids->remove($v);
                    }
                }
                App::core()->favorite()->setLocalIds($user_fav_ids);
                if (!App::core()->error()->flag()) {
                    App::core()->notice()->addSuccessNotice(__('Favorites have been successfully removed.'));
                    App::core()->adminurl()->redirect('admin.user.pref', [], '#user-favorites');
                }
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
            }
        }

        // Order favs
        $order = new Strings();
        if (GPC::post()->empty('favs_order') && !GPC::post()->empty('order')) {
            $order = GPC::post()->array('order');
            asort($order);
            $order = new Strings(array_keys($order));
        } elseif (!GPC::post()->empty('favs_order')) {
            $order = new Strings(explode(',', GPC::post()->string('favs_order')));
        }

        if (!GPC::post()->empty('saveorder') && $order->count()) {
            foreach ($order->dump() as $v) {
                if (!App::core()->favorite()->hasItem($v)) {
                    $order->remove($v);
                }
            }
            App::core()->favorite()->setLocalIds($order);
            if (!App::core()->error()->flag()) {
                App::core()->notice()->addSuccessNotice(__('Favorites have been successfully updated.'));
                App::core()->adminurl()->redirect('admin.user.pref', [], '#user-favorites');
            }
        }

        // Replace default favorites by current set (super admin only)
        if (!GPC::post()->empty('replace') && App::core()->user()->isSuperAdmin()) {
            $user_favs = App::core()->favorite()->getLocalIds();
            App::core()->favorite()->setGlobalIds($user_favs);

            if (!App::core()->error()->flag()) {
                App::core()->notice()->addSuccessNotice(__('Default favorites have been successfully updated.'));
                App::core()->adminurl()->redirect('admin.user.pref', [], '#user-favorites');
            }
        }

        // Reset dashboard items order
        if (!GPC::post()->empty('resetorder')) {
            App::core()->user()->preference()->get('dashboard')->drop('main_order');
            App::core()->user()->preference()->get('dashboard')->drop('boxes_order');
            App::core()->user()->preference()->get('dashboard')->drop('boxes_items_order');
            App::core()->user()->preference()->get('dashboard')->drop('boxes_contents_order');

            if (!App::core()->error()->flag()) {
                App::core()->notice()->addSuccessNotice(__('Dashboard items order have been successfully reset.'));
                App::core()->adminurl()->redirect('admin.user.pref', [], '#user-favorites');
            }
        }

        // Page setup
        if (!$this->user_acc_nodragdrop) {
            $this->setPageHead(App::core()->resource()->load('_preferences-dragdrop.js'));
        }
        $this
            ->setPageTitle($page_title)
            ->setpageHelp()
            ->setpageHelp('core_user_pref')
            ->setPageHead(
                App::core()->resource()->load('jquery/jquery-ui.custom.js') .
                App::core()->resource()->load('jquery/jquery.ui.touch-punch.js') .
                App::core()->resource()->json('pwstrength', [
                    'min' => sprintf(__('Password strength: %s'), __('weak')),
                    'avg' => sprintf(__('Password strength: %s'), __('medium')),
                    'max' => sprintf(__('Password strength: %s'), __('strong')),
                ]) .
                App::core()->resource()->load('pwstrength.js') .
                App::core()->resource()->load('_preferences.js') .
                App::core()->resource()->pageTabs($default_tab) .
                App::core()->resource()->confirmClose('user-form', 'opts-forms', 'favs-form', 'db-forms') .

                // --BEHAVIOR-- adminPreferencesHeaders
                App::core()->behavior('adminPreferencesHeaders')->call()
            )
            ->setPageBreadcrumb([
                Html::escapeHTML(App::core()->user()->userID()) => '',
                $page_title                                     => '',
            ])
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        $editors_combo = App::core()->combo()->getEditorsCombo();
        $editors       = array_keys($editors_combo);

        // Themes
        $theme_combo = [
            __('Light')     => 'light',
            __('Dark')      => 'dark',
            __('Automatic') => '',
        ];

        // Body base font size (37.5% = 6px, 50% = 8px, 62.5% = 10px, 75% = 12px, 87.5% = 14px)
        $htmlfontsize_combo = [
            __('Smallest') => '37.5%',
            __('Smaller')  => '50%',
            __('Default')  => '62.5%',
            __('Larger')   => '75%',
            __('Largest')  => '87.5%',
        ];

        $auto_filter = App::core()->user()->preference()->get('interface')->get('auto_filter');

        // User profile
        echo '<div class="multi-part" id="user-profile" title="' . __('My profile') . '">';

        echo '<h3>' . __('My profile') . '</h3>' .
        '<form action="' . App::core()->adminurl()->root() . '" method="post" id="user-form">' .

        '<p><label for="user_name">' . __('Last Name:') . '</label>' .
        Form::field('user_name', 20, 255, [
            'default'      => Html::escapeHTML($this->user->getProperty('user_name')),
            'autocomplete' => 'family-name',
        ]) .
        '</p>' .

        '<p><label for="user_firstname">' . __('First Name:') . '</label>' .
        Form::field('user_firstname', 20, 255, [
            'default'      => Html::escapeHTML($this->user->getProperty('user_firstname')),
            'autocomplete' => 'given-name',
        ]) .
        '</p>' .

        '<p><label for="user_displayname">' . __('Display name:') . '</label>' .
        Form::field('user_displayname', 20, 255, [
            'default'      => Html::escapeHTML($this->user->getProperty('user_displayname')),
            'autocomplete' => 'nickname',
        ]) .
        '</p>' .

        '<p><label for="user_email">' . __('Email:') . '</label>' .
        Form::email('user_email', [
            'default'      => Html::escapeHTML($this->user->getProperty('user_email')),
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
            'default'      => Html::escapeHTML($this->user->getProperty('user_url')),
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
        Form::combo('user_lang', App::core()->combo()->getAdminLangsCombo(), $this->user->getProperty('user_lang'), 'l10n') . '</p>' .

        '<p><label for="user_tz">' . __('My timezone:') . '</label>' .
        Form::combo('user_tz', Clock::getZones(true, true), $this->user->getProperty('user_tz')) . '</p>';

        if (App::core()->user()->allowPassChange()) {
            echo '<h4 class="vertical-separator pretty-title">' . __('Change my password') . '</h4>' .

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

        echo '<p class="clear vertical-separator">' .
        App::core()->adminurl()->getHiddenFormFields('admin.user.pref', [], true) .
        '<input type="submit" accesskey="s" value="' . __('Update my profile') . '" />' .
        ' <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />' .
            '</p>' .
            '</form>' .

            '</div>';

        // User options : some from actual user profile, dashboard modules, ...
        echo '<div class="multi-part" id="user-options" title="' . __('My options') . '">';

        echo '<form action="' . App::core()->adminurl()->root() . '#user-options" method="post" id="opts-forms">' .
        '<h3>' . __('My options') . '</h3>';

        echo '<div class="fieldset">' .
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

        echo '<p><label for="user_ui_media_nb_last_dirs" class="classic">' . __('Number of recent folders proposed in media manager:') . '</label> ' .
        Form::number('user_ui_media_nb_last_dirs', 0, 999, (string) $this->user_ui_media_nb_last_dirs, '', '', false, 'aria-describedby="user_ui_media_nb_last_dirs_help"') . '</p>' .
        '<p class="clear form-note" id="user_ui_media_nb_last_dirs_help">' . __('Leave empty to ignore, displayed only if Javascript is enabled in your browser.') . '</p>';

        if (App::core()->user()->isSuperAdmin()) {
            echo '<p><label for="user_ui_hide_std_favicon" class="classic">' .
            Form::checkbox('user_ui_hide_std_favicon', 1, $this->user_ui_hide_std_favicon, '', '', false, 'aria-describedby="user_ui_hide_std_favicon_help"') . ' ' .
            __('Do not use standard favicon') . '</label> ' .
            '<span class="clear form-note warn" id="user_ui_hide_std_favicon_help">' . __('This will be applied for all users') . '.</span>' .
                '</p>'; // Opera sucks;
        }

        echo '<p><label for="user_ui_nocheckadblocker" class="classic">' .
        Form::checkbox('user_ui_nocheckadblocker', 1, $this->user_ui_nocheckadblocker, '', '', false, 'aria-describedby="user_ui_nocheckadblocker_help"') . ' ' .
        __('Disable Ad-blocker check') . '</label></p>' .
        '<p class="clear form-note" id="user_ui_nocheckadblocker_help">' . __('Some ad-blockers (Ghostery, Adblock plus, uBloc origin, …) may interfere with some feature as inserting link or media in entries with CKEditor; in this case you should disable it for this Dotclear installation (backend only). Note that Dotclear do not add ads ot trackers in the backend.') . '</p>';

        echo '</div>';

        echo '<div class="fieldset">' .
        '<h4 id="user_options_columns">' . __('Optional columns displayed in lists') . '</h4>';
        $odd = true;
        foreach (App::core()->listoption()->column()->getGroups() as $group) {
            echo '<div class="two-boxes ' . ($odd ? 'odd' : 'even') . '">';
            echo '<h5>' . $group->title . '</h5>';
            foreach ($group->getItems() as $item) {
                echo '<p><label for="cols_' . $group->id . '-' . $item->id . '" class="classic">' .
                Form::checkbox(['cols_' . $group->id . '[]', 'cols_' . $group->id . '-' . $item->id], $item->id, $item->isActive()) . $item->title . '</label>';
            }
            echo '</div>';
            $odd = !$odd;
        }
        echo '</div>';

        echo '<div class="fieldset">' .
        '<h4 id="user_options_lists">' . __('Options for lists') . '</h4>' .
        '<p><label for="user_ui_auto_filter" class="classic">' .
        Form::checkbox('user_ui_auto_filter', 1, $auto_filter) . ' ' .
        __('Apply filters on the fly') . '</label></p>';

        $odd = true;
        foreach (App::core()->listoption()->sort()->getGroups() as $group) {
            if ($odd) {
                echo '<hr />';
            }
            echo '<div class="two-boxes ' . ($odd ? 'odd' : 'even') . '">';
            echo '<h5>' . $group->title . '</h5>';
            if (null !== $group->getSortBy()) {
                echo '<p class="field"><label for="sorts_' . $group->id . '_sortby">' . __('Order by:') . '</label> ' .
                Form::combo('sorts_' . $group->id . '_sortby', $group->combo, $group->getSortBy()) . '</p>';
            }
            if (null !== $group->getSortOrder()) {
                echo '<p class="field"><label for="sorts_' . $group->id . '_order">' . __('Sort:') . '</label> ' .
                Form::combo('sorts_' . $group->id . '_order', App::core()->combo()->getOrderCombo(), $group->getSortOrder()) . '</p>';
            }
            if (null !== $group->getSortLimit()) {
                echo '<p><span class="label ib">' . __('Show') . '</span> <label for="sorts_' . $group->id . '_nb" class="classic">' .
                Form::number('sorts_' . $group->id . '_nb', 0, 999, (string) $group->getSortLimit()) . ' ' .
                $group->keyword . '</label></p>';
            }
            echo '</div>';
            $odd = !$odd;
        }
        echo '</div>';

        echo '<div class="fieldset">' .
        '<h4 id="user_options_edition">' . __('Edition') . '</h4>';

        echo '<div class="two-boxes odd">';
        $user_editors = $this->user->getOption('editor');
        foreach ($this->format_by_editors as $format => $editors) {
            echo '<p class="field"><label for="user_editor_' . $format . '">' . sprintf(__('Preferred editor for %s:'), $format) . '</label>' .
            Form::combo(
                ['user_editor[' . $format . ']', 'user_editor_' . $format],
                array_merge([__('Choose an editor') => ''], $editors),
                $user_editors[$format]
            ) . '</p>';
        }
        echo '<p class="field"><label for="user_post_format">' . __('Preferred format:') . '</label>' .
        Form::combo('user_post_format', $this->available_formats, $this->user->getOption('post_format')) . '</p>';

        echo '<p class="field"><label for="user_post_status">' . __('Default entry status:') . '</label>' .
        Form::combo('user_post_status', App::core()->combo()->getPostStatusesCombo(), $this->user->getProperty('user_post_status')) . '</p>' .

        '<p class="field"><label for="user_edit_size">' . __('Entry edit field height:') . '</label>' .
        Form::number('user_edit_size', 10, 999, (string) $this->user->getOption('edit_size')) . '</p>' .

        '<p><label for="user_wysiwyg" class="classic">' .
        Form::checkbox('user_wysiwyg', 1, $this->user->getOption('enable_wysiwyg')) . ' ' .
        __('Enable WYSIWYG mode') . '</label></p>' .

        '<p><label for="user_toolbar_bottom" class="classic">' .
        Form::checkbox('user_toolbar_bottom', 1, $this->user->getOption('toolbar_bottom')) . ' ' .
        __('Display editor\'s toolbar at bottom of textarea (if possible)') . '</label></p>' .

            '</div>';

        echo '<div class="two-boxes even">';
        echo '<h5>' . __('Use xhtml editor for:') . '</h5>';
        foreach ($this->rte as $rk => $rv) {
            echo '<p><label for="rte_' . $rk . '" class="classic">' .
            Form::checkbox(['rte_flags[]', 'rte_' . $rk], $rk, $rv[0]) . $rv[1] . '</label>';
        }
        echo '</div>';

        echo '</div>'; // fieldset

        echo '<h4 class="pretty-title">' . __('Other options') . '</h4>';

        // --BEHAVIOR-- adminPreferencesForm, UserContainer
        App::core()->behavior('adminPreferencesForm')->call($this->user);

        echo '<p class="clear vertical-separator">' .
        App::core()->adminurl()->getHiddenFormFields('admin.user.pref', [], true) .
        '<input name="user_options_submit" type="submit" accesskey="s" value="' . __('Save my options') . '" />' .
        ' <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />' .
            '</p>' .
            '</form>';

        echo '</div>';

        // My dashboard
        echo '<div class="multi-part" id="user-favorites" title="' . __('My dashboard') . '">';
        echo '<h3>' . __('My dashboard') . '</h3>';

        // Favorites
        echo '<form action="' . App::core()->adminurl()->root() . '" method="post" id="favs-form" class="two-boxes odd">';

        echo '<div id="my-favs" class="fieldset"><h4>' . __('My favorites') . '</h4>';

        $count    = 0;
        $user_fav = App::core()->favorite()->getLocalIds();
        foreach ($user_fav->dump() as $id) {
            $item = App::core()->favorite()->getItem($id);
            if (!empty($item)) {
                // User favorites only
                if (0 == $count) {
                    echo '<ul class="fav-list">';
                }

                ++$count;
                $icon = App::core()->menu()->getIconTheme($item->icons);
                $zoom = App::core()->menu()->getIconTheme($item->icons, false);
                if ('' !== $zoom) {
                    $icon .= ' <span class="zoom">' . $zoom . '</span>';
                }
                echo '<li id="fu-' . $id . '">' . '<label for="fuk-' . $id . '">' . $icon .
                Form::number(['order[' . $id . ']'], [
                    'min'        => 1,
                    'max'        => $user_fav->count(),
                    'default'    => (string) $count,
                    'class'      => 'position',
                    'extra_html' => 'title="' . sprintf(__('position of %s'), $item->title) . '"',
                ]) .
                Form::hidden(['dynorder[]', 'dynorder-' . $id . ''], $id) .
                Form::checkbox(['remove[]', 'fuk-' . $id], $id) . __($item->title) . '</label>' .
                    '</li>';
            }
        }
        if (0 < $count) {
            echo '</ul>';
        }

        if (0 < $count) {
            echo '<div class="clear">' .
            '<p>' . Form::hidden('favs_order', '') .
            '<input type="submit" name="saveorder" value="' . __('Save order') . '" /> ' .

            '<input type="submit" class="delete" name="removeaction" ' .
            'value="' . __('Delete selected favorites') . '" ' .
            'onclick="return window.confirm(\'' . Html::escapeJS(
                __('Are you sure you want to remove selected favorites?')
            ) . '\');" /></p>' .

                (App::core()->user()->isSuperAdmin() ?
                '<div class="info">' .
                '<p>' . __('If you are a super administrator, you may define this set of favorites to be used by default on all blogs of this installation.') . '</p>' .
                '<p><input class="reset" type="submit" name="replace" value="' . __('Define as default favorites') . '" />' . '</p>' .
                '</div>'
                :
                '') .

                '</div>';
        } else {
            echo '<p>' . __('Currently no personal favorites.') . '</p>';
        }

        $avail_fav       = App::core()->favorite()->getItems(App::core()->favorite()->getIds());
        $default_fav_ids = App::core()->favorite()->getGlobalIds();

        echo '</div>'; // /box my-fav

        echo '<div class="fieldset" id="available-favs">';
        // Available favorites
        echo '<h5 class="pretty-title">' . __('Other available favorites') . '</h5>';
        $count = 0;
        uasort($avail_fav, function ($a, $b) {
            return strcoll(
                strtolower(Lexical::removeDiacritics($a->title)),
                strtolower(Lexical::removeDiacritics($b->title))
            );
        });

        foreach ($avail_fav as $id => $item) {
            if ($user_fav->exists($id)) {
                unset($avail_fav[$id]);
            }
        }
        foreach ($avail_fav as $k => $item) {
            if (0 == $count) {
                echo '<ul class="fav-list">';
            }

            ++$count;
            $icon = App::core()->menu()->getIconTheme($item->icons);
            $zoom = App::core()->menu()->getIconTheme($item->icons, false);
            if ('' !== $zoom) {
                $icon .= ' <span class="zoom">' . $zoom . '</span>';
            }
            echo '<li id="fa-' . $k . '">' . '<label for="fak-' . $k . '">' . $icon .
            Form::checkbox(['append[]', 'fak-' . $k], $k) .
                $item->title . '</label>' .
                ($default_fav_ids->exists($k) ? ' <span class="default-fav"><img src="?df=images/selected.png" alt="' . __('(default favorite)') . '" /></span>' : '') .
                '</li>';
        }
        if (0 < $count) {
            echo '</ul>';
        }

        echo '<p>' .
        App::core()->adminurl()->getHiddenFormFields('admin.user.pref', [], true) .
        '<input type="submit" name="appendaction" value="' . __('Add to my favorites') . '" /></p>';
        echo '</div>'; // /available favorites

        echo '</form>';

        // Dashboard items
        echo '<form action="' . App::core()->adminurl()->root() . '" method="post" id="db-forms" class="two-boxes even">' .

        '<div class="fieldset">' .
        '<h4>' . __('Menu') . '</h4>' .
        '<p><label for="user_ui_nofavmenu" class="classic">' .
        Form::checkbox('user_ui_nofavmenu', 1, !$this->user_ui_nofavmenu) . ' ' .
        __('Display favorites at the top of the menu') . '</label></p></div>';

        echo '<div class="fieldset">' .
        '<h4>' . __('Dashboard icons') . '</h4>' .
        '<p><label for="user_dm_nofavicons" class="classic">' .
        Form::checkbox('user_dm_nofavicons', 1, !$this->user_dm_nofavicons) . ' ' .
        __('Display dashboard icons') . '</label></p>';
        echo '</div>';

        echo '<div class="fieldset">' .
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

        if (App::core()->user()->isSuperAdmin()) {
            echo '<p><label for="user_dm_nodcupdate" class="classic">' .
            Form::checkbox('user_dm_nodcupdate', 1, $this->user_dm_nodcupdate) . ' ' .
            __('Do not display Dotclear updates') . '</label></p>';
        }

        echo '</div>';

        // --BEHAVIOR-- adminDashboardOptionsForm
        App::core()->behavior('adminDashboardOptionsForm')->call(App::core());

        echo '<p>' .
        Form::hidden('db-options', '-') .
        App::core()->adminurl()->getHiddenFormFields('admin.user.pref', [], true) .
        '<input type="submit" accesskey="s" value="' . __('Save my dashboard options') . '" />' .
        ' <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />' .
            '</p>' .
            '</form>';

        // Dashboard items order (reset)
        echo '<form action="' . App::core()->adminurl()->root() . '" method="post" id="order-reset" class="two-boxes even">';
        echo '<div class="fieldset"><h4>' . __('Dashboard items order') . '</h4>';
        echo '<p>' .
        App::core()->adminurl()->getHiddenFormFields('admin.user.pref', [], true) .
        '<input type="submit" name="resetorder" value="' . __('Reset dashboard items order') . '" /></p>';
        echo '</div>';
        echo '</form>';

        echo '</div>'; // /multipart-user-favorites
    }
}
