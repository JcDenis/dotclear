<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Handler;

// Dotclear\Process\Admin\Handler\User
use ArrayObject;
use Dotclear\App;
use Dotclear\Core\User\UserContainer;
use Dotclear\Core\User\Preference\Preference;
use Dotclear\Database\Param;
use Dotclear\Exception\AdminException;
use Dotclear\Helper\Clock;
use Dotclear\Helper\GPC\GPC;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Process\Admin\Page\AbstractPage;
use Exception;

/**
 * Admin user page.
 *
 * @ingroup  Admin User handler
 */
class User extends AbstractPage
{
    /**
     * @var UserContainer $user
     *                    User container instance
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

    protected function getPermissions(): string|bool
    {
        return '';
    }

    protected function getPagePrepend(): ?bool
    {
        $page_title = __('New user');

        $this->user = new UserContainer();

        $this->user->setProperty('user_lang', App::core()->user()->getInfo('user_lang'));
        $this->user->setProperty('user_tz', App::core()->user()->getInfo('user_tz'));

        // Get user if we have an ID
        if (!GPC::request()->empty('id')) {
            try {
                $param = new Param();
                $param->set('user_id', GPC::request()->string('id'));

                $record = App::core()->users()->getUsers(param: $param);

                $this->user->parseFromRecord($record);

                unset($param, $record);

                $user_prefs               = new Preference($this->user->getProperty('user_id'), 'profile');
                $this->user_profile_mails = $user_prefs->get('profile')->get('mails');
                $this->user_profile_urls  = $user_prefs->get('profile')->get('urls');

                $page_title = $this->user->getProperty('user_id');
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
            }
        }

        // Add or update user
        if (GPC::post()->isset('user_name')) {
            try {
                if (!App::core()->user()->checkPassword(GPC::post()->string('your_pwd'))) {
                    throw new AdminException(__('Password verification failed'));
                }

                $this->user->setProperty('user_id', GPC::post()->string('user_id'));
                $this->user->setProperty('user_super', !GPC::post()->empty('user_super'));
                $this->user->setProperty('user_name', Html::escapeHTML(GPC::post()->string('user_name')));
                $this->user->setProperty('user_firstname', Html::escapeHTML(GPC::post()->string('user_firstname')));
                $this->user->setProperty('user_displayname', Html::escapeHTML(GPC::post()->string('user_displayname')));
                $this->user->setProperty('user_email', Html::escapeHTML(GPC::post()->string('user_email')));
                $this->user->setProperty('user_url', Html::escapeHTML(GPC::post()->string('user_url')));
                $this->user->setProperty('user_lang', Html::escapeHTML(GPC::post()->string('user_lang')));
                $this->user->setProperty('user_tz', Html::escapeHTML(GPC::post()->string('user_tz')));
                $this->user->setProperty('user_post_status', Html::escapeHTML(GPC::post()->string('user_post_status')));

                if ($this->user->getProperty('user_id') == App::core()->user()->userID() && App::core()->user()->isSuperAdmin()) {
                    // force super_user to true if current user
                    $this->user->setProperty('user_super', true);
                }
                if (App::core()->user()->allowPassChange()) {
                    $this->user->setProperty('user_change_pwd', !GPC::post()->empty('user_change_pwd') ? 1 : 0);
                }

                if (!GPC::post()->empty('new_pwd')) {
                    if (GPC::post()->string('new_pwd') != GPC::post()->string('new_pwd_c')) {
                        throw new AdminException(__("Passwords don't match"));
                    }
                    $this->user->setProperty('user_pwd', GPC::post()->string('new_pwd'));
                }

                $this->user->setOption('post_format', Html::escapeHTML(GPC::post()->string('user_post_format')));
                $this->user->setOption('edit_size', GPC::post()->int('user_edit_size'));

                if ($this->user->getOption('edit_size') < 1) {
                    $this->user->setOption('edit_size', 10);
                }

                $cur = $this->user->parseToCursor(App::core()->con()->openCursor(App::core()->prefix() . 'user'));
                $cur->setField('user_options', new ArrayObject($this->user->getOptions()));

                // Udate user
                if (!GPC::request()->empty('id')) {
                    $new_id = App::core()->users()->updateUser(id: $this->user->getProperty('user_id'), cursor: $cur);

                    // Update profile
                    // Sanitize list of secondary mails and urls if any
                    $mails = $urls = '';
                    if (!GPC::post()->empty('user_profile_mails')) {
                        $mails = implode(',', array_filter(filter_var_array(array_map('trim', explode(',', GPC::post()->string('user_profile_mails'))), FILTER_VALIDATE_EMAIL)));
                    }
                    if (!GPC::post()->empty('user_profile_urls')) {
                        $urls = implode(',', array_filter(filter_var_array(array_map('trim', explode(',', GPC::post()->string('user_profile_urls'))), FILTER_VALIDATE_URL)));
                    }
                    $user_prefs = new Preference($this->user->getProperty('user_id'), 'profile');
                    $user_prefs->get('profile')->put('mails', $mails, 'string');
                    $user_prefs->get('profile')->put('urls', $urls, 'string');

                    if ($this->user->getProperty('user_id') == App::core()->user()->userID() && $this->user->getProperty('user_id') != $new_id) {
                        App::core()->session()->destroy();
                    }

                    App::core()->notice()->addSuccessNotice(__('User has been successfully updated.'));
                    App::core()->adminurl()->redirect('admin.user', ['id' => $new_id]);
                }
                // Add user
                else {
                    $param = new Param();
                    $param->set('user_id', $cur->getField('user_id'));
                    if (App::core()->users()->countUsers(param: $param) > 0) {
                        throw new AdminException(sprintf(__('User "%s" already exists.'), Html::escapeHTML($cur->getField('user_id'))));
                    }

                    $new_id = App::core()->users()->createUser(cursor: $cur);

                    // Update profile
                    // Sanitize list of secondary mails and urls if any
                    $mails = $urls = '';
                    if (!GPC::post()->empty('user_profile_mails')) {
                        $mails = implode(',', array_filter(filter_var_array(array_map('trim', explode(',', GPC::post()->string('user_profile_mails'))), FILTER_VALIDATE_EMAIL)));
                    }
                    if (!GPC::post()->empty('user_profile_urls')) {
                        $urls = implode(',', array_filter(filter_var_array(array_map('trim', explode(',', GPC::post()->string('user_profile_urls'))), FILTER_VALIDATE_URL)));
                    }
                    $user_prefs = new Preference($new_id, 'profile');
                    $user_prefs->get('profile')->put('mails', $mails, 'string');
                    $user_prefs->get('profile')->put('urls', $urls, 'string');

                    App::core()->notice()->addSuccessNotice(__('User has been successfully created.'));
                    App::core()->notice()->addWarningNotice(__('User has no permission, he will not be able to login yet. See below to add some.'));
                    if (!GPC::post()->empty('saveplus')) {
                        App::core()->adminurl()->redirect('admin.user');
                    } else {
                        App::core()->adminurl()->redirect('admin.user', ['id' => $new_id]);
                    }
                }
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
            }
        }

        // Page setup
        $this
            ->setPageTitle($page_title)
            ->setPageHelp('core_user')
            ->setPageHead(
                App::core()->resource()->confirmClose('user-form') .
                App::core()->resource()->json('pwstrength', [
                    'min' => sprintf(__('Password strength: %s'), __('weak')),
                    'avg' => sprintf(__('Password strength: %s'), __('medium')),
                    'max' => sprintf(__('Password strength: %s'), __('strong')),
                ]) .
                App::core()->resource()->load('pwstrength.js') .
                App::core()->resource()->load('_user.js') .
                App::core()->behavior()->call('adminUserHeaders')
            )
            ->setPageBreadcrumb([
                __('System') => '',
                __('Users')  => App::core()->adminurl()->get('admin.users'),
                $page_title  => '',
            ])
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        if (!GPC::get()->empty('upd')) {
            App::core()->notice()->success(__('User has been successfully updated.'));
        } elseif (!GPC::get()->empty('add')) {
            App::core()->notice()->success(__('User has been successfully created.'));
        }

        $formaters_combo = App::core()->combo()->getFormatersCombo();

        echo '<form action="' . App::core()->adminurl()->root() . '" method="post" id="user-form">' .
        '<div class="two-cols">' .

        '<div class="col">' .
        '<h3>' . __('User profile') . '</h3>' .

        '<p><label for="user_id" class="required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('User ID:') . '</label> ' .
        Form::field('user_id', 20, 255, [
            'default'      => Html::escapeHTML($this->user->getProperty('user_id')),
            'extra_html'   => 'required placeholder="' . __('Login') . '" aria-describedby="user_id_help user_id_warning"',
            'autocomplete' => 'username',
        ]) .
        '</p>' .
        '<p class="form-note info" id="user_id_help">' . __('At least 2 characters using letters, numbers or symbols.') . '</p>';

        if ($this->user->getProperty('user_id') == App::core()->user()->userID()) {
            echo '<p class="warning" id="user_id_warning">' . __('Warning:') . ' ' .
            __('If you change your username, you will have to log in again.') . '</p>';
        }

        echo '<p>' .
        '<label for="new_pwd" ' . ('' != $this->user->getProperty('user_id') ? '' : 'class="required"') . '>' .
        (''                           != $this->user->getProperty('user_id') ? '' : '<abbr title="' . __('Required field') . '">*</abbr> ') .
        (''                           != $this->user->getProperty('user_id') ? __('New password:') : __('Password:')) . '</label>' .
        Form::password(
            'new_pwd',
            20,
            255,
            [
                'class'        => 'pw-strength',
                'extra_html'   => ('' != $this->user->getProperty('user_id') ? '' : 'aria-describedby="new_pwd_help" required placeholder="' . __('Password') . '"'),
                'autocomplete' => 'new-password', ]
        ) .
        '</p>' .
        '<p class="form-note info" id="new_pwd_help">' . __('Password must contain at least 6 characters.') . '</p>' .

        '<p><label for="new_pwd_c" ' . ('' != $this->user->getProperty('user_id') ? '' : 'class="required"') . '>' .
        (''                                != $this->user->getProperty('user_id') ? '' : '<abbr title="' . __('Required field') . '">*</abbr> ') . __('Confirm password:') . '</label> ' .
        Form::password(
            'new_pwd_c',
            20,
            255,
            [
                'extra_html'   => ('' != $this->user->getProperty('user_id') ? '' : 'required placeholder="' . __('Password') . '"'),
                'autocomplete' => 'new-password', ]
        ) .
            '</p>';

        if (App::core()->user()->allowPassChange()) {
            echo '<p><label for="user_change_pwd" class="classic">' .
            Form::checkbox('user_change_pwd', '1', $this->user->getProperty('user_change_pwd')) . ' ' .
            __('Password change required to connect') . '</label></p>';
        }

        $super_disabled = $this->user->getProperty('user_super') && $this->user->getProperty('user_id') == App::core()->user()->userID();

        echo '<p><label for="user_super" class="classic">' .
        Form::checkbox(
            ($super_disabled ? 'user_super_off' : 'user_super'),
            '1',
            [
                'checked'  => $this->user->getProperty('user_super'),
                'disabled' => $super_disabled,
            ]
        ) .
        ' ' . __('Super administrator') . '</label></p>' .
        ($super_disabled ? Form::hidden(['user_super'], $this->user->getProperty('user_super')) : '') .

        '<p><label for="user_name">' . __('Last Name:') . '</label> ' .
        Form::field('user_name', 20, 255, [
            'default'      => Html::escapeHTML($this->user->getProperty('user_name')),
            'autocomplete' => 'family-name',
        ]) .
        '</p>' .

        '<p><label for="user_firstname">' . __('First Name:') . '</label> ' .
        Form::field('user_firstname', 20, 255, [
            'default'      => Html::escapeHTML($this->user->getProperty('user_firstname')),
            'autocomplete' => 'given-name',
        ]) .
        '</p>' .

        '<p><label for="user_displayname">' . __('Display name:') . '</label> ' .
        Form::field('user_displayname', 20, 255, [
            'default'      => Html::escapeHTML($this->user->getProperty('user_displayname')),
            'autocomplete' => 'nickname',
        ]) .
        '</p>' .

        '<p><label for="user_email">' . __('Email:') . '</label> ' .
        Form::email('user_email', [
            'default'      => Html::escapeHTML($this->user->getProperty('user_email')),
            'extra_html'   => 'aria-describedby="user_email_help"',
            'autocomplete' => 'email',
        ]) .
        '</p>' .
        '<p class="form-note" id="user_email_help">' . __('Mandatory for password recovering procedure.') . '</p>' .

        '<p><label for="user_profile_mails">' . __('Alternate emails (comma separated list):') . '</label>' .
        Form::field('user_profile_mails', 50, 255, [
            'default' => Html::escapeHTML($this->user_profile_mails),
        ]) .
        '</p>' .
        '<p class="form-note info" id="sanitize_emails">' . __('Invalid emails will be automatically removed from list.') . '</p>' .

        '<p><label for="user_url">' . __('URL:') . '</label> ' .
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

        '</div>' .

        '<div class="col">' .
        '<h3>' . __('Options') . '</h3>' .
        '<h4>' . __('Interface') . '</h4>' .
        '<p><label for="user_lang">' . __('Language:') . '</label> ' .
        Form::combo('user_lang', App::core()->combo()->getAdminLangsCombo(), $this->user->getProperty('user_lang'), 'l10n') .
        '</p>' .

        '<p><label for="user_tz">' . __('Timezone:') . '</label> ' .
        Form::combo('user_tz', Clock::getZones(true, true), $this->user->getProperty('user_tz')) .
        '</p>' .

        '<h4>' . __('Edition') . '</h4>' .
        (
            empty($formaters_combo) ?
            Form::hidden('user_post_format', $this->user->getOption('post_format')) :
            '<p><label for="user_post_format">' . __('Preferred format:') . '</label> ' .
            Form::combo('user_post_format', $formaters_combo, $this->user->getOption('post_format')) .
            '</p>'
        ) .

        '<p><label for="user_post_status">' . __('Default entry status:') . '</label> ' .
        Form::combo('user_post_status', App::core()->combo()->getPostStatusesCombo(), $this->user->getProperty('user_post_status')) .
        '</p>' .

        '<p><label for="user_edit_size">' . __('Entry edit field height:') . '</label> ' .
        Form::number('user_edit_size', 10, 999, (string) $this->user->getOption('edit_size')) .
            '</p>';

        // --BEHAVIOR-- adminUserForm, UserContainer
        App::core()->behavior()->call('adminUserForm', user: $this->user);

        unset($param, $record);

        echo '</div>' .
            '</div>';

        echo '<p class="clear vertical-separator"><label for="your_pwd" class="required">' .
        '<abbr title="' . __('Required field') . '">*</abbr> ' . __('Your password:') . '</label>' .
        Form::password(
            'your_pwd',
            20,
            255,
            [
                'extra_html'   => 'required placeholder="' . __('Password') . '"',
                'autocomplete' => 'current-password',
            ]
        ) . '</p>' .
        '<p class="clear"><input type="submit" name="save" accesskey="s" value="' . __('Save') . '" />' .
        ('' != $this->user->getProperty('user_id') ? '' : ' <input type="submit" name="saveplus" value="' . __('Save and create another') . '" />') .
        ('' != $this->user->getProperty('user_id') ? Form::hidden('id', $this->user->getProperty('user_id')) : '') .
        ' <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />' .
        App::core()->adminurl()->getHiddenFormFields('admin.user', [], true) .
            '</p>' .

            '</form>';

        if (!$this->user->getProperty('user_id')) {
            return;
        }

        echo '<div class="clear fieldset">' .
        '<h3>' . __('Permissions') . '</h3>';

        if (!$this->user->getProperty('user_super')) {
            echo '<form action="' . App::core()->adminurl()->root() . '" method="post">' .
            '<p><input type="submit" value="' . __('Add new permissions') . '" />' .
            App::core()->adminurl()->getHiddenFormFields('admin.user.actions', [
                'redir'   => App::core()->adminurl()->get('admin.user', ['id' => $this->user->getProperty('user_id')]),
                'action'  => 'blogs',
                'users[]' => $this->user->getProperty('user_id'),
            ], true) . '</p>' .
                '</form>';

            $permissions = App::core()->users()->getUserPermissions(id: $this->user->getProperty('user_id'));
            $perm_types  = App::core()->user()->getPermissionsTypes();

            if (count($permissions) == 0) {
                echo '<p>' . __('No permissions so far.') . '</p>';
            } else {
                foreach ($permissions as $k => $v) {
                    if (count($v['p']) > 0) {
                        echo '<form action="' . App::core()->adminurl()->root() . '" method="post" class="perm-block">' .
                        '<p class="blog-perm">' . __('Blog:') . ' <a href="' .
                        App::core()->adminurl()->get('admin.blog', ['id' => Html::escapeHTML($k)]) . '">' .
                        Html::escapeHTML($v['name']) . '</a> (' . Html::escapeHTML($k) . ')</p>';

                        echo '<ul class="ul-perm">';
                        foreach ($v['p'] as $p => $V) {
                            if (isset($perm_types[$p])) {
                                echo '<li>' . __($perm_types[$p]) . '</li>';
                            }
                        }
                        echo '</ul>' .
                        '<p class="add-perm"><input type="submit" class="reset" value="' . __('Change permissions') . '" />' .
                        App::core()->adminurl()->getHiddenFormFields('admin.user.actions', [
                            'redir'   => App::core()->adminurl()->get('admin.user', ['id' => $this->user->getProperty('user_id')]),
                            'action'  => 'perms',
                            'users[]' => $this->user->getProperty('user_id'),
                            'blogs[]' => $k,
                        ], true) . '</p>' .
                            '</form>';
                    }
                }
            }
        } else {
            echo '<p>' . sprintf(__('%s is super admin (all rights on all blogs).'), '<strong>' . $this->user->getProperty('user_id') . '</strong>') . '</p>';
        }
        echo '</div>';

        // Informations (direct links)
        echo '<div class="clear fieldset">' .
        '<h3>' . __('Direct links') . '</h3>';
        echo '<p><a href="' . App::core()->adminurl()->get(
            'admin.posts',
            ['user_id' => $this->user->getProperty('user_id')]
        ) . '">' . __('List of posts') . '</a>';
        echo '<p><a href="' . App::core()->adminurl()->get(
            'admin.comments',
            [
                'email' => $this->user->getProperty('user_email'),
                'site'  => $this->user->getProperty('user_url'),
            ]
        ) . '">' . __('List of comments') . '</a>';
        echo '</div>';
    }
}
