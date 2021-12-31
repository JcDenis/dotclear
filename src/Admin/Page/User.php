<?php
/**
 * @class Dotclear\Admin\Page\User
 * @brief Dotclear admin user page
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
use Dotclear\Exception\AdminException;

use Dotclear\Core\Core;
use Dotclear\Core\Prefs;

use Dotclear\Admin\Page;
use Dotclear\Admin\Notices;
use Dotclear\Admin\Combos;

use Dotclear\Html\Html;
use Dotclear\Html\Form;
use Dotclear\Utils\Dt;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class User extends Page
{
    private $user_id            = '';
    private $user_super         = '';
    private $user_pwd           = '';
    private $user_change_pwd    = '';
    private $user_name          = '';
    private $user_firstname     = '';
    private $user_displayname   = '';
    private $user_email         = '';
    private $user_url           = '';
    private $user_lang          = '';
    private $user_tz            = '';
    private $user_post_status   = '';
    private $user_options       = [];
    private $user_profile_mails = '';
    private $user_profile_urls  = '';

    protected function getPermissions(): string|null|false
    {
        return null;
    }

    protected function getPagePrepend(): ?bool
    {
        $page_title = __('New user');

        $this->user_lang    = $this->core->auth->getInfo('user_lang');
        $this->user_tz      = $this->core->auth->getInfo('user_tz');
        $this->user_options = $this->core->userDefaults();

        # Get user if we have an ID
        if (!empty($_REQUEST['id'])) {
            try {
                $rs = $this->core->getUser($_REQUEST['id']);

                $this->user_id          = $rs->user_id;
                $this->user_super       = $rs->user_super;
                $this->user_pwd         = $rs->user_pwd;
                $this->user_change_pwd  = $rs->user_change_pwd;
                $this->user_name        = $rs->user_name;
                $this->user_firstname   = $rs->user_firstname;
                $this->user_displayname = $rs->user_displayname;
                $this->user_email       = $rs->user_email;
                $this->user_url         = $rs->user_url;
                $this->user_lang        = $rs->user_lang;
                $this->user_tz          = $rs->user_tz;
                $this->user_post_status = $rs->user_post_status;

                $this->user_options = array_merge($this->user_options, $rs->options());

                $user_prefs = new Prefs($this->core, $this->user_id, 'profile');
                $user_prefs->addWorkspace('profile');
                $this->user_profile_mails = $user_prefs->profile->mails;
                $this->user_profile_urls  = $user_prefs->profile->urls;

                $page_title = $this->user_id;
            } catch (Exception $e) {
                $this->core->error->add($e->getMessage());
            }
        }

        # Add or update user
        if (isset($_POST['user_name'])) {
            try {
                if (empty($_POST['your_pwd']) || !$this->core->auth->checkPassword($_POST['your_pwd'])) {
                    throw new AdminException(__('Password verification failed'));
                }

                $cur = $this->core->con->openCursor($this->core->prefix . 'user');

                $cur->user_id          = $_POST['user_id'];
                $cur->user_super       = $this->user_super       = !empty($_POST['user_super']) ? 1 : 0;
                $cur->user_name        = $this->user_name        = Html::escapeHTML($_POST['user_name']);
                $cur->user_firstname   = $this->user_firstname   = Html::escapeHTML($_POST['user_firstname']);
                $cur->user_displayname = $this->user_displayname = Html::escapeHTML($_POST['user_displayname']);
                $cur->user_email       = $this->user_email       = Html::escapeHTML($_POST['user_email']);
                $cur->user_url         = $this->user_url         = Html::escapeHTML($_POST['user_url']);
                $cur->user_lang        = $this->user_lang        = Html::escapeHTML($_POST['user_lang']);
                $cur->user_tz          = $this->user_tz          = Html::escapeHTML($_POST['user_tz']);
                $cur->user_post_status = $this->user_post_status = Html::escapeHTML($_POST['user_post_status']);

                if ($this->user_id && $cur->user_id == $this->core->auth->userID() && $this->core->auth->isSuperAdmin()) {
                    // force super_user to true if current user
                    $cur->user_super = $this->user_super = true;
                }
                if ($this->core->auth->allowPassChange()) {
                    $cur->user_change_pwd = !empty($_POST['user_change_pwd']) ? 1 : 0;
                }

                if (!empty($_POST['new_pwd'])) {
                    if ($_POST['new_pwd'] != $_POST['new_pwd_c']) {
                        throw new AdminException(__("Passwords don't match"));
                    }
                    $cur->user_pwd = $_POST['new_pwd'];
                }

                $this->user_options['post_format'] = Html::escapeHTML($_POST['user_post_format']);
                $this->user_options['edit_size']   = (integer) $_POST['user_edit_size'];

                if ($this->user_options['edit_size'] < 1) {
                    $this->user_options['edit_size'] = 10;
                }

                $cur->user_options = new ArrayObject($this->user_options);

                # Udate user
                if ($this->user_id) {
                    # --BEHAVIOR-- adminBeforeUserUpdate
                    $this->core->behaviors->call('adminBeforeUserUpdate', $cur, $this->user_id);

                    $new_id = $this->core->updUser($this->user_id, $cur);

                    # Update profile
                    # Sanitize list of secondary mails and urls if any
                    $mails = $urls = '';
                    if (!empty($_POST['user_profile_mails'])) {
                        $mails = implode(',', array_filter(filter_var_array(array_map('trim', explode(',', $_POST['user_profile_mails'])), FILTER_VALIDATE_EMAIL)));
                    }
                    if (!empty($_POST['user_profile_urls'])) {
                        $urls = implode(',', array_filter(filter_var_array(array_map('trim', explode(',', $_POST['user_profile_urls'])), FILTER_VALIDATE_URL)));
                    }
                    $user_prefs = new Prefs($this->core, $this->user_id, 'profile');
                    $user_prefs->addWorkspace('profile');
                    $user_prefs->profile->put('mails', $mails, 'string');
                    $user_prefs->profile->put('urls', $urls, 'string');

                    # --BEHAVIOR-- adminAfterUserUpdate
                    $this->core->behaviors->call('adminAfterUserUpdate', $cur, $new_id);

                    if ($this->user_id == $this->core->auth->userID() && $this->user_id != $new_id) {
                        $this->core->session->destroy();
                    }

                    Notices::addSuccessNotice(__('User has been successfully updated.'));
                    $this->core->adminurl->redirect('admin.user', ['id' => $new_id]);
                }
                # Add user
                else {
                    if ($this->core->getUsers(['user_id' => $cur->user_id], true)->f(0) > 0) {
                        throw new AdminException(sprintf(__('User "%s" already exists.'), Html::escapeHTML($cur->user_id)));
                    }

                    # --BEHAVIOR-- adminBeforeUserCreate
                    $this->core->behaviors->call('adminBeforeUserCreate', $cur);

                    $new_id = $this->core->addUser($cur);

                    # Update profile
                    # Sanitize list of secondary mails and urls if any
                    $mails = $urls = '';
                    if (!empty($_POST['user_profile_mails'])) {
                        $mails = implode(',', array_filter(filter_var_array(array_map('trim', explode(',', $_POST['user_profile_mails'])), FILTER_VALIDATE_EMAIL)));
                    }
                    if (!empty($_POST['user_profile_urls'])) {
                        $urls = implode(',', array_filter(filter_var_array(array_map('trim', explode(',', $_POST['user_profile_urls'])), FILTER_VALIDATE_URL)));
                    }
                    $user_prefs = new Prefs($this->core, $new_id, 'profile');
                    $user_prefs->addWorkspace('profile');
                    $user_prefs->profile->put('mails', $mails, 'string');
                    $user_prefs->profile->put('urls', $urls, 'string');

                    # --BEHAVIOR-- adminAfterUserCreate
                    $this->core->behaviors->call('adminAfterUserCreate', $cur, $new_id);

                    Notices::addSuccessNotice(__('User has been successfully created.'));
                    Notices::addWarningNotice(__('User has no permission, he will not be able to login yet. See below to add some.'));
                    if (!empty($_POST['saveplus'])) {
                        $this->core->adminurl->redirect('admin.user');
                    } else {
                        $this->core->adminurl->redirect('admin.user', ['id' => $new_id]);
                    }
                }
            } catch (Exception $e) {
                $this->core->error->add($e->getMessage());
            }
        }

        $this
            ->setPageTitle($page_title)
            ->setPageHelp('core_user')
            ->setPageHead(
                static::jsConfirmClose('user-form') .
                static::jsJson('pwstrength', [
                    'min' => sprintf(__('Password strength: %s'), __('weak')),
                    'avg' => sprintf(__('Password strength: %s'), __('medium')),
                    'max' => sprintf(__('Password strength: %s'), __('strong'))
                ]) .
                static::jsLoad('js/pwstrength.js') .
                static::jsLoad('js/_user.js') .
                $this->core->behaviors->call('adminUserHeaders')
            )
            ->setPageBreadcrumb([
                __('System') => '',
                __('Users')  => $this->core->adminurl->get('admin.users'),
                $page_title  => ''
            ])
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        if (!empty($_GET['upd'])) {
            Notices::success(__('User has been successfully updated.'));
        }

        if (!empty($_GET['add'])) {
            Notices::success(__('User has been successfully created.'));
        }

        # Formaters combo
        $formaters_combo = Combos::getFormatersCombo();

        $status_combo = Combos::getPostStatusesCombo();

        # Language codes
        $lang_combo = Combos::getAdminLangsCombo();

        echo
        '<form action="' . $this->core->adminurl->get('admin.user') . '" method="post" id="user-form">' .
        '<div class="two-cols">' .

        '<div class="col">' .
        '<h3>' . __('User profile') . '</h3>' .

        '<p><label for="user_id" class="required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('User ID:') . '</label> ' .
        Form::field('user_id', 20, 255, [
            'default'      => Html::escapeHTML($this->user_id),
            'extra_html'   => 'required placeholder="' . __('Login') . '" aria-describedby="user_id_help user_id_warning"',
            'autocomplete' => 'username'
        ]) .
        '</p>' .
        '<p class="form-note info" id="user_id_help">' . __('At least 2 characters using letters, numbers or symbols.') . '</p>';

        if ($this->user_id == $this->core->auth->userID()) {
            echo
            '<p class="warning" id="user_id_warning">' . __('Warning:') . ' ' .
            __('If you change your username, you will have to log in again.') . '</p>';
        }

        echo
        '<p>' .
        '<label for="new_pwd" ' . ($this->user_id != '' ? '' : 'class="required"') . '>' .
        ($this->user_id != '' ? '' : '<abbr title="' . __('Required field') . '">*</abbr> ') .
        ($this->user_id != '' ? __('New password:') : __('Password:')) . '</label>' .
        Form::password('new_pwd', 20, 255,
            [
                'class'        => 'pw-strength',
                'extra_html'   => ($this->user_id != '' ? '' : 'aria-describedby="new_pwd_help" required placeholder="' . __('Password') . '"'),
                'autocomplete' => 'new-password']
        ) .
        '</p>' .
        '<p class="form-note info" id="new_pwd_help">' . __('Password must contain at least 6 characters.') . '</p>' .

        '<p><label for="new_pwd_c" ' . ($this->user_id != '' ? '' : 'class="required"') . '>' .
        ($this->user_id != '' ? '' : '<abbr title="' . __('Required field') . '">*</abbr> ') . __('Confirm password:') . '</label> ' .
        Form::password('new_pwd_c', 20, 255,
            [
                'extra_html'   => ($this->user_id != '' ? '' : 'required placeholder="' . __('Password') . '"'),
                'autocomplete' => 'new-password']) .
            '</p>';

        if ($this->core->auth->allowPassChange()) {
            echo
            '<p><label for="user_change_pwd" class="classic">' .
            Form::checkbox('user_change_pwd', '1', $this->user_change_pwd) . ' ' .
            __('Password change required to connect') . '</label></p>';
        }

        $super_disabled = $this->user_super && $this->user_id == $this->core->auth->userID();

        echo
        '<p><label for="user_super" class="classic">' .
        Form::checkbox(($super_disabled ? 'user_super_off' : 'user_super'), '1',
            [
                'checked'  => $this->user_super,
                'disabled' => $super_disabled
            ]) .
        ' ' . __('Super administrator') . '</label></p>' .
        ($super_disabled ? Form::hidden(['user_super'], $this->user_super) : '') .

        '<p><label for="user_name">' . __('Last Name:') . '</label> ' .
        Form::field('user_name', 20, 255, [
            'default'      => Html::escapeHTML($this->user_name),
            'autocomplete' => 'family-name'
        ]) .
        '</p>' .

        '<p><label for="user_firstname">' . __('First Name:') . '</label> ' .
        Form::field('user_firstname', 20, 255, [
            'default'      => Html::escapeHTML($this->user_firstname),
            'autocomplete' => 'given-name'
        ]) .
        '</p>' .

        '<p><label for="user_displayname">' . __('Display name:') . '</label> ' .
        Form::field('user_displayname', 20, 255, [
            'default'      => Html::escapeHTML($this->user_displayname),
            'autocomplete' => 'nickname'
        ]) .
        '</p>' .

        '<p><label for="user_email">' . __('Email:') . '</label> ' .
        Form::email('user_email', [
            'default'      => Html::escapeHTML($this->user_email),
            'extra_html'   => 'aria-describedby="user_email_help"',
            'autocomplete' => 'email'
        ]) .
        '</p>' .
        '<p class="form-note" id="user_email_help">' . __('Mandatory for password recovering procedure.') . '</p>' .

        '<p><label for="user_profile_mails">' . __('Alternate emails (comma separated list):') . '</label>' .
        Form::field('user_profile_mails', 50, 255, [
            'default' => Html::escapeHTML($this->user_profile_mails)
        ]) .
        '</p>' .
        '<p class="form-note info" id="sanitize_emails">' . __('Invalid emails will be automatically removed from list.') . '</p>' .

        '<p><label for="user_url">' . __('URL:') . '</label> ' .
        Form::url('user_url', [
            'size'         => 30,
            'default'      => Html::escapeHTML($this->user_url),
            'autocomplete' => 'url'
        ]) .
        '</p>' .

        '<p><label for="user_profile_urls">' . __('Alternate URLs (comma separated list):') . '</label>' .
        Form::field('user_profile_urls', 50, 255, [
            'default' => Html::escapeHTML($this->user_profile_urls)
        ]) .
        '</p>' .
        '<p class="form-note info" id="sanitize_urls">' . __('Invalid URLs will be automatically removed from list.') . '</p>' .

        '</div>' .

        '<div class="col">' .
        '<h3>' . __('Options') . '</h3>' .
        '<h4>' . __('Interface') . '</h4>' .
        '<p><label for="user_lang">' . __('Language:') . '</label> ' .
        Form::combo('user_lang', $lang_combo, $this->user_lang, 'l10n') .
        '</p>' .

        '<p><label for="user_tz">' . __('Timezone:') . '</label> ' .
        Form::combo('user_tz', Dt::getZones(true, true), $this->user_tz) .
        '</p>' .

        '<h4>' . __('Edition') . '</h4>' .
        (empty($formaters_combo) ?
            Form::hidden('user_post_format', $this->user_options['post_format']) :
            '<p><label for="user_post_format">' . __('Preferred format:') . '</label> ' .
            Form::combo('user_post_format', $formaters_combo, $this->user_options['post_format']) .
            '</p>'
        ) .

        '<p><label for="user_post_status">' . __('Default entry status:') . '</label> ' .
        Form::combo('user_post_status', $status_combo, $this->user_post_status) .
        '</p>' .

        '<p><label for="user_edit_size">' . __('Entry edit field height:') . '</label> ' .
        Form::number('user_edit_size', 10, 999, (string) $this->user_options['edit_size']) .
            '</p>';

        # --BEHAVIOR-- adminUserForm
        $this->core->behaviors->call('adminUserForm', $rs ?? null);

        echo
            '</div>' .
            '</div>';

        echo
        '<p class="clear vertical-separator"><label for="your_pwd" class="required">' .
        '<abbr title="' . __('Required field') . '">*</abbr> ' . __('Your password:') . '</label>' .
        Form::password('your_pwd', 20, 255,
            [
                'extra_html'   => 'required placeholder="' . __('Password') . '"',
                'autocomplete' => 'current-password'
            ]
        ) . '</p>' .
        '<p class="clear"><input type="submit" name="save" accesskey="s" value="' . __('Save') . '" />' .
        ($this->user_id != '' ? '' : ' <input type="submit" name="saveplus" value="' . __('Save and create another') . '" />') .
        ($this->user_id != '' ? Form::hidden('id', $this->user_id) : '') .
        ' <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />' .
        $this->core->formNonce() .
            '</p>' .

            '</form>';

        if (!$this->user_id) {
            return;
        }

        echo '<div class="clear fieldset">' .
        '<h3>' . __('Permissions') . '</h3>';

        if (!$this->user_super) {
            echo
            '<form action="' . $this->core->adminurl->get('admin.user.actions') . '" method="post">' .
            '<p><input type="submit" value="' . __('Add new permissions') . '" />' .
            Form::hidden(['redir'], $this->core->adminurl->get('admin.user', ['id' => $this->user_id])) .
            Form::hidden(['action'], 'blogs') .
            Form::hidden(['users[]'], $this->user_id) .
            $this->core->formNonce() .
                '</p>' .
                '</form>';

            $permissions = $this->core->getUserPermissions($this->user_id);
            $perm_types  = $this->core->auth->getPermissionsTypes();

            if (count($permissions) == 0) {
                echo '<p>' . __('No permissions so far.') . '</p>';
            } else {
                foreach ($permissions as $k => $v) {
                    if (count($v['p']) > 0) {
                        echo
                        '<form action="' . $this->core->adminurl->get('admin.user.actions') . '" method="post" class="perm-block">' .
                        '<p class="blog-perm">' . __('Blog:') . ' <a href="' .
                        $this->core->adminurl->get('admin.blog', ['id' => Html::escapeHTML($k)]) . '">' .
                        Html::escapeHTML($v['name']) . '</a> (' . Html::escapeHTML($k) . ')</p>';

                        echo '<ul class="ul-perm">';
                        foreach ($v['p'] as $p => $V) {
                            if (isset($perm_types[$p])) {
                                echo '<li>' . __($perm_types[$p]) . '</li>';
                            }
                        }
                        echo
                        '</ul>' .
                        '<p class="add-perm"><input type="submit" class="reset" value="' . __('Change permissions') . '" />' .
                        Form::hidden(['redir'], $this->core->adminurl->get('admin.user', ['id' => $this->user_id])) .
                        Form::hidden(['action'], 'perms') .
                        Form::hidden(['users[]'], $this->user_id) .
                        Form::hidden(['blogs[]'], $k) .
                        $this->core->formNonce() .
                            '</p>' .
                            '</form>';
                    }
                }
            }
        } else {
            echo '<p>' . sprintf(__('%s is super admin (all rights on all blogs).'), '<strong>' . $this->user_id . '</strong>') . '</p>';
        }
        echo '</div>';

        // Informations (direct links)
        echo '<div class="clear fieldset">' .
        '<h3>' . __('Direct links') . '</h3>';
        echo '<p><a href="' . $this->core->adminurl->get('admin.posts',
            ['user_id' => $this->user_id]
        ) . '">' . __('List of posts') . '</a>';
        echo '<p><a href="' . $this->core->adminurl->get('admin.comments',
            [
                'email' => $this->core->auth->getInfo('user_email', $this->user_id),
                'site'  => $this->core->auth->getInfo('user_url', $this->user_id),
            ]
        ) . '">' . __('List of comments') . '</a>';
        echo '</div>';
    }
}
