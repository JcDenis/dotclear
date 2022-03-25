<?php
/**
 * @class Dotclear\Process\Admin\Handler\User
 * @brief Dotclear admin user page
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Handler;

use ArrayObject;

use Dotclear\Process\Admin\Page\Page;
use Dotclear\Container\UserContainer;
use Dotclear\Core\User\Preference\Preference;
use Dotclear\Exception\AdminException;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Dt;

class User extends Page
{
    /** @var UserContainer  User container instance */
    protected $container;

    /** @var string     User other emails (comma separated list ) */
    protected $user_profile_mails = '';

    /** @var string     User other URLs (comma separated list ) */
    protected $user_profile_urls = '';

    protected function getPermissions(): string|null|false
    {
        return null;
    }

    protected function getPagePrepend(): ?bool
    {
        $page_title = __('New user');

        $this->container = new UserContainer();

        $this->container->user_lang = dotclear()->user()->getInfo('user_lang');
        $this->container->user_tz   = dotclear()->user()->getInfo('user_tz');

        # Get user if we have an ID
        if (!empty($_REQUEST['id'])) {
            try {
                $rs = dotclear()->users()->getUser($_REQUEST['id']);

                $this->container->fromRecord($rs);

                $user_prefs = new Preference($this->container->user_id, 'profile');
                $this->user_profile_mails = $user_prefs->profile->mails;
                $this->user_profile_urls  = $user_prefs->profile->urls;

                $page_title = $this->container->user_id;
            } catch (\Exception $e) {
                dotclear()->error()->add($e->getMessage());
            }
        }

        # Add or update user
        if (isset($_POST['user_name'])) {
            try {
                if (empty($_POST['your_pwd']) || !dotclear()->user()->checkPassword($_POST['your_pwd'])) {
                    throw new AdminException(__('Password verification failed'));
                }

                $this->container->user_id = $_POST['user_id'];
                $this->container->user_super = !empty($_POST['user_super']);
                $this->container->user_name = Html::escapeHTML($_POST['user_name']);
                $this->container->user_firstname = Html::escapeHTML($_POST['user_firstname']);
                $this->container->user_displayname = Html::escapeHTML($_POST['user_displayname']);
                $this->container->user_email = Html::escapeHTML($_POST['user_email']);
                $this->container->user_url = Html::escapeHTML($_POST['user_url']);
                $this->container->user_lang = Html::escapeHTML($_POST['user_lang']);
                $this->container->user_tz = Html::escapeHTML($_POST['user_tz']);
                $this->container->user_post_status = Html::escapeHTML($_POST['user_post_status']);

                if ($this->container->user_id == dotclear()->user()->userID() && dotclear()->user()->isSuperAdmin()) {
                    // force super_user to true if current user
                    $this->container->user_super = true;
                }
                if (dotclear()->user()->allowPassChange()) {
                    $this->container->user_change_pwd = !empty($_POST['user_change_pwd']) ? 1 : 0;
                }

                if (!empty($_POST['new_pwd'])) {
                    if ($_POST['new_pwd'] != $_POST['new_pwd_c']) {
                        throw new AdminException(__("Passwords don't match"));
                    }
                    $this->container->user_pwd = $_POST['new_pwd'];
                }

                $this->container->setOption('post_format', Html::escapeHTML($_POST['user_post_format']));
                $this->container->setOption('edit_size', $_POST['user_edit_size'], 'int');

                if ($this->container->getOption('edit_size') < 1) {
                    $this->container->setOption('edit_size', 10);
                }

                $cur = $this->container->toCursor(dotclear()->con()->openCursor(dotclear()->prefix . 'user'));
                $cur->user_options = new ArrayObject($this->container->getOptions());

                # Udate user
                if (!empty($_REQUEST['id'])) {
                    # --BEHAVIOR-- adminBeforeUserUpdate
                    dotclear()->behavior()->call('adminBeforeUserUpdate', $cur, $this->container->user_id);

                    $new_id = dotclear()->users()->updUser($this->container->user_id, $cur);

                    # Update profile
                    # Sanitize list of secondary mails and urls if any
                    $mails = $urls = '';
                    if (!empty($_POST['user_profile_mails'])) {
                        $mails = implode(',', array_filter(filter_var_array(array_map('trim', explode(',', $_POST['user_profile_mails'])), FILTER_VALIDATE_EMAIL)));
                    }
                    if (!empty($_POST['user_profile_urls'])) {
                        $urls = implode(',', array_filter(filter_var_array(array_map('trim', explode(',', $_POST['user_profile_urls'])), FILTER_VALIDATE_URL)));
                    }
                    $user_prefs = new Preference($this->container->user_id, 'profile');
                    $user_prefs->profile->put('mails', $mails, 'string');
                    $user_prefs->profile->put('urls', $urls, 'string');

                    # --BEHAVIOR-- adminAfterUserUpdate
                    dotclear()->behavior()->call('adminAfterUserUpdate', $cur, $new_id);

                    if ($this->container->user_id == dotclear()->user()->userID() && $this->container->user_id != $new_id) {
                        dotclear()->session()->destroy();
                    }

                    dotclear()->notice()->addSuccessNotice(__('User has been successfully updated.'));
                    dotclear()->adminurl()->redirect('admin.user', ['id' => $new_id]);
                }
                # Add user
                else {
                    if (dotclear()->users()->getUsers(['user_id' => $cur->user_id], true)->fInt() > 0) {
                        throw new AdminException(sprintf(__('User "%s" already exists.'), Html::escapeHTML($cur->user_id)));
                    }

                    # --BEHAVIOR-- adminBeforeUserCreate
                    dotclear()->behavior()->call('adminBeforeUserCreate', $cur);

                    $new_id = dotclear()->users()->addUser($cur);

                    # Update profile
                    # Sanitize list of secondary mails and urls if any
                    $mails = $urls = '';
                    if (!empty($_POST['user_profile_mails'])) {
                        $mails = implode(',', array_filter(filter_var_array(array_map('trim', explode(',', $_POST['user_profile_mails'])), FILTER_VALIDATE_EMAIL)));
                    }
                    if (!empty($_POST['user_profile_urls'])) {
                        $urls = implode(',', array_filter(filter_var_array(array_map('trim', explode(',', $_POST['user_profile_urls'])), FILTER_VALIDATE_URL)));
                    }
                    $user_prefs = new Preference($new_id, 'profile');
                    $user_prefs->profile->put('mails', $mails, 'string');
                    $user_prefs->profile->put('urls', $urls, 'string');

                    # --BEHAVIOR-- adminAfterUserCreate
                    dotclear()->behavior()->call('adminAfterUserCreate', $cur, $new_id);

                    dotclear()->notice()->addSuccessNotice(__('User has been successfully created.'));
                    dotclear()->notice()->addWarningNotice(__('User has no permission, he will not be able to login yet. See below to add some.'));
                    if (!empty($_POST['saveplus'])) {
                        dotclear()->adminurl()->redirect('admin.user');
                    } else {
                        dotclear()->adminurl()->redirect('admin.user', ['id' => $new_id]);
                    }
                }
            } catch (\Exception $e) {
                dotclear()->error()->add($e->getMessage());
            }
        }

        # Page setup
        $this
            ->setPageTitle($page_title)
            ->setPageHelp('core_user')
            ->setPageHead(
                dotclear()->resource()->confirmClose('user-form') .
                dotclear()->resource()->json('pwstrength', [
                    'min' => sprintf(__('Password strength: %s'), __('weak')),
                    'avg' => sprintf(__('Password strength: %s'), __('medium')),
                    'max' => sprintf(__('Password strength: %s'), __('strong'))
                ]) .
                dotclear()->resource()->load('pwstrength.js') .
                dotclear()->resource()->load('_user.js') .
                dotclear()->behavior()->call('adminUserHeaders')
            )
            ->setPageBreadcrumb([
                __('System') => '',
                __('Users')  => dotclear()->adminurl()->get('admin.users'),
                $page_title  => ''
            ])
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        if (!empty($_GET['upd'])) {
            dotclear()->notice()->success(__('User has been successfully updated.'));
        }

        if (!empty($_GET['add'])) {
            dotclear()->notice()->success(__('User has been successfully created.'));
        }

        $formaters_combo = dotclear()->combo()->getFormatersCombo();

        echo
        '<form action="' . dotclear()->adminurl()->root() . '" method="post" id="user-form">' .
        '<div class="two-cols">' .

        '<div class="col">' .
        '<h3>' . __('User profile') . '</h3>' .

        '<p><label for="user_id" class="required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('User ID:') . '</label> ' .
        Form::field('user_id', 20, 255, [
            'default'      => Html::escapeHTML($this->container->user_id),
            'extra_html'   => 'required placeholder="' . __('Login') . '" aria-describedby="user_id_help user_id_warning"',
            'autocomplete' => 'username'
        ]) .
        '</p>' .
        '<p class="form-note info" id="user_id_help">' . __('At least 2 characters using letters, numbers or symbols.') . '</p>';

        if ($this->container->user_id == dotclear()->user()->userID()) {
            echo
            '<p class="warning" id="user_id_warning">' . __('Warning:') . ' ' .
            __('If you change your username, you will have to log in again.') . '</p>';
        }

        echo
        '<p>' .
        '<label for="new_pwd" ' . ($this->container->user_id != '' ? '' : 'class="required"') . '>' .
        ($this->container->user_id != '' ? '' : '<abbr title="' . __('Required field') . '">*</abbr> ') .
        ($this->container->user_id != '' ? __('New password:') : __('Password:')) . '</label>' .
        Form::password('new_pwd', 20, 255,
            [
                'class'        => 'pw-strength',
                'extra_html'   => ($this->container->user_id != '' ? '' : 'aria-describedby="new_pwd_help" required placeholder="' . __('Password') . '"'),
                'autocomplete' => 'new-password']
        ) .
        '</p>' .
        '<p class="form-note info" id="new_pwd_help">' . __('Password must contain at least 6 characters.') . '</p>' .

        '<p><label for="new_pwd_c" ' . ($this->container->user_id != '' ? '' : 'class="required"') . '>' .
        ($this->container->user_id != '' ? '' : '<abbr title="' . __('Required field') . '">*</abbr> ') . __('Confirm password:') . '</label> ' .
        Form::password('new_pwd_c', 20, 255,
            [
                'extra_html'   => ($this->container->user_id != '' ? '' : 'required placeholder="' . __('Password') . '"'),
                'autocomplete' => 'new-password']) .
            '</p>';

        if (dotclear()->user()->allowPassChange()) {
            echo
            '<p><label for="user_change_pwd" class="classic">' .
            Form::checkbox('user_change_pwd', '1', $this->container->user_change_pwd) . ' ' .
            __('Password change required to connect') . '</label></p>';
        }

        $super_disabled = $this->container->user_super && $this->container->user_id == dotclear()->user()->userID();

        echo
        '<p><label for="user_super" class="classic">' .
        Form::checkbox(($super_disabled ? 'user_super_off' : 'user_super'), '1',
            [
                'checked'  => $this->container->user_super,
                'disabled' => $super_disabled
            ]) .
        ' ' . __('Super administrator') . '</label></p>' .
        ($super_disabled ? Form::hidden(['user_super'], $this->container->user_super) : '') .

        '<p><label for="user_name">' . __('Last Name:') . '</label> ' .
        Form::field('user_name', 20, 255, [
            'default'      => Html::escapeHTML($this->container->user_name),
            'autocomplete' => 'family-name'
        ]) .
        '</p>' .

        '<p><label for="user_firstname">' . __('First Name:') . '</label> ' .
        Form::field('user_firstname', 20, 255, [
            'default'      => Html::escapeHTML($this->container->user_firstname),
            'autocomplete' => 'given-name'
        ]) .
        '</p>' .

        '<p><label for="user_displayname">' . __('Display name:') . '</label> ' .
        Form::field('user_displayname', 20, 255, [
            'default'      => Html::escapeHTML($this->container->user_displayname),
            'autocomplete' => 'nickname'
        ]) .
        '</p>' .

        '<p><label for="user_email">' . __('Email:') . '</label> ' .
        Form::email('user_email', [
            'default'      => Html::escapeHTML($this->container->user_email),
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
            'default'      => Html::escapeHTML($this->container->user_url),
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
        Form::combo('user_lang', dotclear()->combo()->getAdminLangsCombo(), $this->container->user_lang, 'l10n') .
        '</p>' .

        '<p><label for="user_tz">' . __('Timezone:') . '</label> ' .
        Form::combo('user_tz', Dt::getZones(true, true), $this->container->user_tz) .
        '</p>' .

        '<h4>' . __('Edition') . '</h4>' .
        (empty($formaters_combo) ?
            Form::hidden('user_post_format', $this->container->getOption('post_format')) :
            '<p><label for="user_post_format">' . __('Preferred format:') . '</label> ' .
            Form::combo('user_post_format', $formaters_combo, $this->container->getOption('post_format')) .
            '</p>'
        ) .

        '<p><label for="user_post_status">' . __('Default entry status:') . '</label> ' .
        Form::combo('user_post_status', dotclear()->combo()->getPostStatusesCombo(), $this->container->user_post_status) .
        '</p>' .

        '<p><label for="user_edit_size">' . __('Entry edit field height:') . '</label> ' .
        Form::number('user_edit_size', 10, 999, (string) $this->container->getOption('edit_size')) .
            '</p>';

        # --BEHAVIOR-- adminUserForm
        dotclear()->behavior()->call('adminUserForm', $this->container->user_id ? dotclear()->users()->getUser($this->container->user_id) : null);

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
        ($this->container->user_id != '' ? '' : ' <input type="submit" name="saveplus" value="' . __('Save and create another') . '" />') .
        ($this->container->user_id != '' ? Form::hidden('id', $this->container->user_id) : '') .
        ' <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />' .
        dotclear()->adminurl()->getHiddenFormFields('admin.user', [], true) .
            '</p>' .

            '</form>';

        if (!$this->container->user_id) {
            return;
        }

        echo '<div class="clear fieldset">' .
        '<h3>' . __('Permissions') . '</h3>';

        if (!$this->container->user_super) {
            echo
            '<form action="' . dotclear()->adminurl()->root() . '" method="post">' .
            '<p><input type="submit" value="' . __('Add new permissions') . '" />' .
            dotclear()->adminurl()->getHiddenFormFields('admin.user.actions', [
                'redir'   => dotclear()->adminurl()->get('admin.user', ['id' => $this->container->user_id]),
                'action'  => 'blogs',
                'users[]' => $this->container->user_id,
            ], true) . '</p>' .
                '</form>';

            $permissions = dotclear()->users()->getUserPermissions($this->container->user_id);
            $perm_types  = dotclear()->user()->getPermissionsTypes();

            if (count($permissions) == 0) {
                echo '<p>' . __('No permissions so far.') . '</p>';
            } else {
                foreach ($permissions as $k => $v) {
                    if (count($v['p']) > 0) {
                        echo
                        '<form action="' . dotclear()->adminurl()->root() . '" method="post" class="perm-block">' .
                        '<p class="blog-perm">' . __('Blog:') . ' <a href="' .
                        dotclear()->adminurl()->get('admin.blog', ['id' => Html::escapeHTML($k)]) . '">' .
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
                        dotclear()->adminurl()->getHiddenFormFields('admin.user.actions', [
                            'redir'   => dotclear()->adminurl()->get('admin.user', ['id' => $this->container->user_id]),
                            'action'  => 'perms',
                            'users[]' => $this->container->user_id,
                            'blogs[]' => $k,
                        ], true) . '</p>' .
                            '</form>';
                    }
                }
            }
        } else {
            echo '<p>' . sprintf(__('%s is super admin (all rights on all blogs).'), '<strong>' . $this->container->user_id . '</strong>') . '</p>';
        }
        echo '</div>';

        // Informations (direct links)
        echo '<div class="clear fieldset">' .
        '<h3>' . __('Direct links') . '</h3>';
        echo '<p><a href="' . dotclear()->adminurl()->get('admin.posts',
            ['user_id' => $this->container->user_id]
        ) . '">' . __('List of posts') . '</a>';
        echo '<p><a href="' . dotclear()->adminurl()->get('admin.comments',
            [
                'email' => dotclear()->user()->getInfo('user_email', $this->container->user_id),
                'site'  => dotclear()->user()->getInfo('user_url', $this->container->user_id),
            ]
        ) . '">' . __('List of comments') . '</a>';
        echo '</div>';
    }
}