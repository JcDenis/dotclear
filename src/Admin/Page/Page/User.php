<?php
/**
 * @class Dotclear\Admin\Page\Page\User
 * @brief Dotclear admin user page
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
use Dotclear\Core\User\Preference\Preference;
use Dotclear\Exception\AdminException;
use Dotclear\Html\Form;
use Dotclear\Html\Html;
use Dotclear\Utils\Dt;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class User extends Page
{
    /** @var ContainerUser  User container instance */
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

        $this->container = new ContainerUser();

        $this->container->setLang(dotclear()->user()->getInfo('user_lang'));
        $this->container->setTZ(dotclear()->user()->getInfo('user_tz'));

        # Get user if we have an ID
        if (!empty($_REQUEST['id'])) {
            try {
                $rs = dotclear()->users()->getUser($_REQUEST['id']);

                $this->container->fromRecord($rs);

                $user_prefs = new Preference($this->container->getId(), 'profile');
                $user_prefs->addWorkspace('profile');
                $this->user_profile_mails = $user_prefs->profile->mails;
                $this->user_profile_urls  = $user_prefs->profile->urls;

                $page_title = $this->container->getId();
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

                $cur = dotclear()->con()->openCursor(dotclear()->prefix . 'user');

                $cur->user_id          = $this->container->setId($_POST['user_id']);
                $cur->user_super       = $this->container->setSuper(!empty($_POST['user_super']));
                $cur->user_name        = $this->container->setName(Html::escapeHTML($_POST['user_name']));
                $cur->user_firstname   = $this->container->setFirstname(Html::escapeHTML($_POST['user_firstname']));
                $cur->user_displayname = $this->container->setDisplayname(Html::escapeHTML($_POST['user_displayname']));
                $cur->user_email       = $this->container->setEmail(Html::escapeHTML($_POST['user_email']));
                $cur->user_url         = $this->container->setURL(Html::escapeHTML($_POST['user_url']));
                $cur->user_lang        = $this->container->setLang(Html::escapeHTML($_POST['user_lang']));
                $cur->user_tz          = $this->container->setTZ(Html::escapeHTML($_POST['user_tz']));
                $cur->user_post_status = $this->container->setPostStatus(Html::escapeHTML($_POST['user_post_status']));

                if ($this->container->getId() && $cur->user_id == dotclear()->user()->userID() && dotclear()->user()->isSuperAdmin()) {
                    // force super_user to true if current user
                    $cur->user_super = $this->container->setSuper(true);
                }
                if (dotclear()->user()->allowPassChange()) {
                    $cur->user_change_pwd = !empty($_POST['user_change_pwd']) ? 1 : 0;
                }

                if (!empty($_POST['new_pwd'])) {
                    if ($_POST['new_pwd'] != $_POST['new_pwd_c']) {
                        throw new AdminException(__("Passwords don't match"));
                    }
                    $cur->user_pwd = $_POST['new_pwd'];
                }

                $this->container->setOption('post_format', Html::escapeHTML($_POST['user_post_format']));
                $this->container->setOption('edit_size', $_POST['user_edit_size'], 'int');

                if ($this->container->getOption('edit_size') < 1) {
                    $this->container->setOption('edit_size', 10);
                }

                $cur->user_options = new ArrayObject($this->container->getOptions());

                # Udate user
                if ($this->container->getId()) {
                    # --BEHAVIOR-- adminBeforeUserUpdate
                    dotclear()->behavior()->call('adminBeforeUserUpdate', $cur, $this->container->getId());

                    $new_id = dotclear()->users()->updUser($this->container->getId(), $cur);

                    # Update profile
                    # Sanitize list of secondary mails and urls if any
                    $mails = $urls = '';
                    if (!empty($_POST['user_profile_mails'])) {
                        $mails = implode(',', array_filter(filter_var_array(array_map('trim', explode(',', $_POST['user_profile_mails'])), FILTER_VALIDATE_EMAIL)));
                    }
                    if (!empty($_POST['user_profile_urls'])) {
                        $urls = implode(',', array_filter(filter_var_array(array_map('trim', explode(',', $_POST['user_profile_urls'])), FILTER_VALIDATE_URL)));
                    }
                    $user_prefs = new Preference($this->container->getId(), 'profile');
                    $user_prefs->addWorkspace('profile');
                    $user_prefs->profile->put('mails', $mails, 'string');
                    $user_prefs->profile->put('urls', $urls, 'string');

                    # --BEHAVIOR-- adminAfterUserUpdate
                    dotclear()->behavior()->call('adminAfterUserUpdate', $cur, $new_id);

                    if ($this->container->getId() == dotclear()->user()->userID() && $this->container->getId() != $new_id) {
                        dotclear()->session()->destroy();
                    }

                    dotclear()->notice()->addSuccessNotice(__('User has been successfully updated.'));
                    dotclear()->adminurl()->redirect('admin.user', ['id' => $new_id]);
                }
                # Add user
                else {
                    if (dotclear()->users()->getUsers(['user_id' => $cur->user_id], true)->f(0) > 0) {
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
                    $user_prefs->addWorkspace('profile');
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
                static::jsConfirmClose('user-form') .
                static::jsJson('pwstrength', [
                    'min' => sprintf(__('Password strength: %s'), __('weak')),
                    'avg' => sprintf(__('Password strength: %s'), __('medium')),
                    'max' => sprintf(__('Password strength: %s'), __('strong'))
                ]) .
                static::jsLoad('js/pwstrength.js') .
                static::jsLoad('js/_user.js') .
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
        '<form action="' . dotclear()->adminurl()->get('admin.user') . '" method="post" id="user-form">' .
        '<div class="two-cols">' .

        '<div class="col">' .
        '<h3>' . __('User profile') . '</h3>' .

        '<p><label for="user_id" class="required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('User ID:') . '</label> ' .
        Form::field('user_id', 20, 255, [
            'default'      => Html::escapeHTML($this->container->getId()),
            'extra_html'   => 'required placeholder="' . __('Login') . '" aria-describedby="user_id_help user_id_warning"',
            'autocomplete' => 'username'
        ]) .
        '</p>' .
        '<p class="form-note info" id="user_id_help">' . __('At least 2 characters using letters, numbers or symbols.') . '</p>';

        if ($this->container->getId() == dotclear()->user()->userID()) {
            echo
            '<p class="warning" id="user_id_warning">' . __('Warning:') . ' ' .
            __('If you change your username, you will have to log in again.') . '</p>';
        }

        echo
        '<p>' .
        '<label for="new_pwd" ' . ($this->container->getId() != '' ? '' : 'class="required"') . '>' .
        ($this->container->getId() != '' ? '' : '<abbr title="' . __('Required field') . '">*</abbr> ') .
        ($this->container->getId() != '' ? __('New password:') : __('Password:')) . '</label>' .
        Form::password('new_pwd', 20, 255,
            [
                'class'        => 'pw-strength',
                'extra_html'   => ($this->container->getId() != '' ? '' : 'aria-describedby="new_pwd_help" required placeholder="' . __('Password') . '"'),
                'autocomplete' => 'new-password']
        ) .
        '</p>' .
        '<p class="form-note info" id="new_pwd_help">' . __('Password must contain at least 6 characters.') . '</p>' .

        '<p><label for="new_pwd_c" ' . ($this->container->getId() != '' ? '' : 'class="required"') . '>' .
        ($this->container->getId() != '' ? '' : '<abbr title="' . __('Required field') . '">*</abbr> ') . __('Confirm password:') . '</label> ' .
        Form::password('new_pwd_c', 20, 255,
            [
                'extra_html'   => ($this->container->getId() != '' ? '' : 'required placeholder="' . __('Password') . '"'),
                'autocomplete' => 'new-password']) .
            '</p>';

        if (dotclear()->user()->allowPassChange()) {
            echo
            '<p><label for="user_change_pwd" class="classic">' .
            Form::checkbox('user_change_pwd', '1', $this->container->getChangePwd()) . ' ' .
            __('Password change required to connect') . '</label></p>';
        }

        $super_disabled = $this->container->getSuper() && $this->container->getId() == dotclear()->user()->userID();

        echo
        '<p><label for="user_super" class="classic">' .
        Form::checkbox(($super_disabled ? 'user_super_off' : 'user_super'), '1',
            [
                'checked'  => $this->container->getSuper(),
                'disabled' => $super_disabled
            ]) .
        ' ' . __('Super administrator') . '</label></p>' .
        ($super_disabled ? Form::hidden(['user_super'], $this->container->getSuper()) : '') .

        '<p><label for="user_name">' . __('Last Name:') . '</label> ' .
        Form::field('user_name', 20, 255, [
            'default'      => Html::escapeHTML($this->container->getName()),
            'autocomplete' => 'family-name'
        ]) .
        '</p>' .

        '<p><label for="user_firstname">' . __('First Name:') . '</label> ' .
        Form::field('user_firstname', 20, 255, [
            'default'      => Html::escapeHTML($this->container->getFirstname()),
            'autocomplete' => 'given-name'
        ]) .
        '</p>' .

        '<p><label for="user_displayname">' . __('Display name:') . '</label> ' .
        Form::field('user_displayname', 20, 255, [
            'default'      => Html::escapeHTML($this->container->getDisplayname()),
            'autocomplete' => 'nickname'
        ]) .
        '</p>' .

        '<p><label for="user_email">' . __('Email:') . '</label> ' .
        Form::email('user_email', [
            'default'      => Html::escapeHTML($this->container->getEmail()),
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
            'default'      => Html::escapeHTML($this->container->getURL()),
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
        Form::combo('user_lang', dotclear()->combo()->getAdminLangsCombo(), $this->container->getLang(), 'l10n') .
        '</p>' .

        '<p><label for="user_tz">' . __('Timezone:') . '</label> ' .
        Form::combo('user_tz', Dt::getZones(true, true), $this->container->getTZ()) .
        '</p>' .

        '<h4>' . __('Edition') . '</h4>' .
        (empty($formaters_combo) ?
            Form::hidden('user_post_format', $this->container->getOption('post_format')) :
            '<p><label for="user_post_format">' . __('Preferred format:') . '</label> ' .
            Form::combo('user_post_format', $formaters_combo, $this->container->getOption('post_format')) .
            '</p>'
        ) .

        '<p><label for="user_post_status">' . __('Default entry status:') . '</label> ' .
        Form::combo('user_post_status', dotclear()->combo()->getPostStatusesCombo(), $this->container->getPostStatus()) .
        '</p>' .

        '<p><label for="user_edit_size">' . __('Entry edit field height:') . '</label> ' .
        Form::number('user_edit_size', 10, 999, (string) $this->container->getOption('edit_size')) .
            '</p>';

        # --BEHAVIOR-- adminUserForm
        dotclear()->behavior()->call('adminUserForm', $rs ?? null);

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
        ($this->container->getId() != '' ? '' : ' <input type="submit" name="saveplus" value="' . __('Save and create another') . '" />') .
        ($this->container->getId() != '' ? Form::hidden('id', $this->container->getId()) : '') .
        ' <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />' .
        dotclear()->nonce()->form() .
            '</p>' .

            '</form>';

        if (!$this->container->getId()) {
            return;
        }

        echo '<div class="clear fieldset">' .
        '<h3>' . __('Permissions') . '</h3>';

        if (!$this->container->getSuper()) {
            echo
            '<form action="' . dotclear()->adminurl()->get('admin.user.actions') . '" method="post">' .
            '<p><input type="submit" value="' . __('Add new permissions') . '" />' .
            Form::hidden(['redir'], dotclear()->adminurl()->get('admin.user', ['id' => $this->container->getId()])) .
            Form::hidden(['action'], 'blogs') .
            Form::hidden(['users[]'], $this->container->getId()) .
            dotclear()->nonce()->form() .
                '</p>' .
                '</form>';

            $permissions = dotclear()->users()->getUserPermissions($this->container->getId());
            $perm_types  = dotclear()->user()->getPermissionsTypes();

            if (count($permissions) == 0) {
                echo '<p>' . __('No permissions so far.') . '</p>';
            } else {
                foreach ($permissions as $k => $v) {
                    if (count($v['p']) > 0) {
                        echo
                        '<form action="' . dotclear()->adminurl()->get('admin.user.actions') . '" method="post" class="perm-block">' .
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
                        Form::hidden(['redir'], dotclear()->adminurl()->get('admin.user', ['id' => $this->container->getId()])) .
                        Form::hidden(['action'], 'perms') .
                        Form::hidden(['users[]'], $this->container->getId()) .
                        Form::hidden(['blogs[]'], $k) .
                        dotclear()->nonce()->form() .
                            '</p>' .
                            '</form>';
                    }
                }
            }
        } else {
            echo '<p>' . sprintf(__('%s is super admin (all rights on all blogs).'), '<strong>' . $this->container->getId() . '</strong>') . '</p>';
        }
        echo '</div>';

        // Informations (direct links)
        echo '<div class="clear fieldset">' .
        '<h3>' . __('Direct links') . '</h3>';
        echo '<p><a href="' . dotclear()->adminurl()->get('admin.posts',
            ['user_id' => $this->container->getId()]
        ) . '">' . __('List of posts') . '</a>';
        echo '<p><a href="' . dotclear()->adminurl()->get('admin.comments',
            [
                'email' => dotclear()->user()->getInfo('user_email', $this->container->getId()),
                'site'  => dotclear()->user()->getInfo('user_url', $this->container->getId()),
            ]
        ) . '">' . __('List of comments') . '</a>';
        echo '</div>';
    }
}
