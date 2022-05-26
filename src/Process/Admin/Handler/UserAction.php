<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Handler;

// Dotclear\Process\Admin\Handler\UserAction
use Dotclear\App;
use Dotclear\Process\Admin\Page\AbstractPage;
use Dotclear\Exception\AdminException;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Http;
use Exception;

/**
 * Admin user action page.
 *
 * @ingroup  Admin User Handler
 */
class UserAction extends AbstractPage
{
    private $user_action = '';
    private $user_redir  = '';
    private $users       = [];
    private $blogs       = [];

    protected function getPermissions(): string|bool
    {
        return '';
    }

    protected function getPagePrepend(): ?bool
    {
        $this->users = [];
        if (!empty($_POST['users']) && is_array($_POST['users'])) {
            foreach ($_POST['users'] as $user) {
                if (App::core()->users()->userExists(id: $user)) {
                    $this->users[] = $user;
                }
            }
        }

        $this->blogs = [];
        if (!empty($_POST['blogs']) && is_array($_POST['blogs'])) {
            foreach ($_POST['blogs'] as $blog) {
                if (App::core()->blogs()->blogExists(id: $blog)) {
                    $this->blogs[] = $blog;
                }
            }
        }

        if (!empty($_POST['action']) && !empty($_POST['users'])) {
            $this->user_action = $_POST['action'];

            if (isset($_POST['redir']) && !str_contains($_POST['redir'], '://')) {
                $this->user_redir = $_POST['redir'];
            } else {
                $this->user_redir = App::core()->adminurl()->get('admin.users', [
                    'q'      => $_POST['q'] ?? '',
                    'sortby' => $_POST['sortby'] ?? '',
                    'order'  => $_POST['order'] ?? '',
                    'page'   => $_POST['page'] ?? '',
                    'nb'     => $_POST['nb'] ?? '',
                ], '&');
            }

            if (empty($this->users)) {
                App::core()->error()->add(__('No blog or user given.'));
            }

            // --BEHAVIOR-- adminUsersActions
            App::core()->behavior()->call('adminUsersActions', $this->users, $this->blogs, $this->user_action, $this->user_redir);

            // Delete users
            if ('deleteuser' == $this->user_action && !empty($this->users)) {
                foreach ($this->users as $user) {
                    try {
                        if (App::core()->user()->userID() == $user) {
                            throw new AdminException(__('You cannot delete yourself.'));
                        }

                        // --BEHAVIOR-- adminBeforeUserDelete
                        App::core()->behavior()->call('adminBeforeUserDelete', $user);

                        App::core()->users()->delUser(id: $user);
                    } catch (Exception $e) {
                        App::core()->error()->add($e->getMessage());
                    }
                }
                if (!App::core()->error()->flag()) {
                    App::core()->notice()->addSuccessNotice(__('User has been successfully deleted.'));
                    Http::redirect($this->user_redir);
                }
            }

            // Update users perms
            if ('updateperm' == $this->user_action && !empty($this->users) && !empty($this->blogs)) {
                try {
                    if (empty($_POST['your_pwd']) || !App::core()->user()->checkPassword($_POST['your_pwd'])) {
                        throw new AdminException(__('Password verification failed'));
                    }

                    foreach ($this->users as $user) {
                        foreach ($this->blogs as $blog) {
                            $set_perms = [];

                            if (!empty($_POST['perm'][$blog])) {
                                foreach ($_POST['perm'][$blog] as $perm_id => $v) {
                                    if ($v) {
                                        $set_perms[$perm_id] = true;
                                    }
                                }
                            }

                            App::core()->users()->setUserBlogPermissions(id: $user, blog: $blog, permissions: $set_perms, delete: true);
                        }
                    }
                } catch (Exception $e) {
                    App::core()->error()->add($e->getMessage());
                }
                if (!App::core()->error()->flag()) {
                    App::core()->notice()->addSuccessNotice(__('User has been successfully updated.'));
                    Http::redirect($this->user_redir);
                }
            }
        }

        if (!empty($this->users) && empty($this->blogs) && 'blogs' == $this->user_action) {
            $this->setPageBreadcrumb([
                __('System')      => '',
                __('Users')       => App::core()->adminurl()->get('admin.users'),
                __('Permissions') => '',
            ]);
        } else {
            $this->setPageBreadcrumb([
                __('System')  => '',
                __('Users')   => App::core()->adminurl()->get('admin.users'),
                __('Actions') => '',
            ]);
        }

        $this
            ->setPageTitle(__('Users'))
            ->setPageHelp('core_users')
            ->setPageHead(
                App::core()->resource()->load('_users_actions.js') .
                // --BEHAVIOR-- adminUsersActionsHeaders
                App::core()->behavior()->call('adminUsersActionsHeaders')
            )
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        if (empty($this->user_action)) {
            return;
        }

        $hidden_fields = '';
        foreach ($this->users as $u) {
            $hidden_fields .= Form::hidden(['users[]'], $u);
        }

        if (isset($_POST['redir']) && !str_contains($_POST['redir'], '://')) {
            $hidden_fields .= Form::hidden(['redir'], Html::escapeURL($_POST['redir']));
        } else {
            $hidden_fields .=
            Form::hidden(['q'], Html::escapeHTML($_POST['q'] ?? '')) .
            Form::hidden(['sortby'], $_POST['sortby']        ?? '') .
            Form::hidden(['order'], $_POST['order']          ?? '') .
            Form::hidden(['page'], $_POST['page']            ?? '') .
            Form::hidden(['nb'], $_POST['nb']                ?? '');
        }

        echo '<p><a class="back" href="' . Html::escapeURL($this->user_redir) . '">' . __('Back to user profile') . '</a></p>';

        // --BEHAVIOR-- adminUsersActionsContent
        App::core()->behavior()->call('adminUsersActionsContent', $this->user_action, $hidden_fields);

        // Blog list where to set permissions
        if (!empty($this->users) && empty($this->blogs) && 'blogs' == $this->user_action) {
            $rs        = null;
            $nb_blog   = 0;
            $user_list = [];

            try {
                $rs      = App::core()->blogs()->getBlogs();
                $nb_blog = $rs->count();
            } catch (\Exception) {
            }

            foreach ($this->users as $u) {
                $user_list[] = '<a href="' . App::core()->adminurl()->get('admin.user', ['id' => $u]) . '">' . $u . '</a>';
            }

            echo '<p>' . sprintf(
                __('Choose one or more blogs to which you want to give permissions to users %s.'),
                implode(', ', $user_list)
            ) . '</p>';

            if (0 == $nb_blog) {
                echo '<p><strong>' . __('No blog') . '</strong></p>';
            } else {
                echo '<form action="' . App::core()->adminurl()->root() . '" method="post" id="form-blogs">' .
                '<div class="table-outer clear">' .
                '<table><tr>' .
                '<th class="nowrap" colspan="2">' . __('Blog ID') . '</th>' .
                '<th class="nowrap">' . __('Blog name') . '</th>' .
                '<th class="nowrap">' . __('URL') . '</th>' .
                '<th class="nowrap">' . __('Entries') . '</th>' .
                '<th class="nowrap">' . __('Status') . '</th>' .
                    '</tr>';

                while ($rs->fetch()) {
                    $img_status = 1 == $rs->fInt('blog_status') ? 'check-on' : (0 == $rs->fInt('blog_status') ? 'check-off' : 'check-wrn');
                    $txt_status = App::core()->blogs()->getBlogsStatusName(code: $rs->fInt('blog_status'), default: __('online'));
                    $img_status = sprintf('<img src="?df=images/%1$s.png" alt="%2$s" title="%2$s" />', $img_status, $txt_status);

                    echo '<tr class="line">' .
                    '<td class="nowrap">' .
                    Form::checkbox(
                        ['blogs[]'],
                        $rs->f('blog_id'),
                        [
                            'extra_html' => 'title="' . __('select') . ' ' . $rs->f('blog_id') . '"',
                        ]
                    ) .
                    '</td>' .
                    '<td class="nowrap">' . $rs->f('blog_id') . '</td>' .
                    '<td class="maximal">' . Html::escapeHTML($rs->f('blog_name')) . '</td>' .
                    '<td class="nowrap"><a class="outgoing" href="' . Html::escapeHTML($rs->f('blog_url')) . '">' . Html::escapeHTML($rs->f('blog_url')) .
                    ' <img src="?df=images/outgoing-link.svg" alt="" /></a></td>' .
                    '<td class="nowrap">' . App::core()->blogs()->countBlogPosts(id: $rs->f('blog_id')) . '</td>' .
                        '<td class="status">' . $img_status . '</td>' .
                        '</tr>';
                }

                echo '</table></div>' .
                '<p class="checkboxes-helpers"></p>' .
                '<p><input id="do-action" type="submit" value="' . __('Set permissions') . '" />' .
                $hidden_fields .
                App::core()->adminurl()->getHiddenFormFields('admin.user.actions', ['action' => 'perms'], true) . '</p>' .
                    '</form>';
            }

            // Permissions list for each selected blogs
        } elseif (!empty($this->blogs) && !empty($this->users) && 'perms' == $this->user_action) {
            $user_perm = $user_list = [];
            if (count($this->users) == 1) {
                $user_perm = App::core()->users()->getUserPermissions(id: $this->users[0]);
            }

            foreach ($this->users as $u) {
                $user_list[] = '<a href="' . App::core()->adminurl()->get('admin.user', ['id' => $u]) . '">' . $u . '</a>';
            }

            echo '<p>' . sprintf(
                __('You are about to change permissions on the following blogs for users %s.'),
                implode(', ', $user_list)
            ) . '</p>' .
            '<form id="permissions-form" action="' . App::core()->adminurl()->root() . '" method="post">';

            foreach ($this->blogs as $b) {
                echo '<h3>' . ('Blog:') . ' <a href="' . App::core()->adminurl()->get('admin.blog', ['id' => Html::escapeHTML($b)]) . '">' . Html::escapeHTML($b) . '</a>' .
                Form::hidden(['blogs[]'], $b) . '</h3>';
                $unknown_perms = $user_perm;
                foreach (App::core()->user()->getPermissionsTypes() as $perm_id => $perm) {
                    $checked = false;

                    if (count($this->users) == 1) {
                        $checked = isset($user_perm[$b]['p'][$perm_id]) && $user_perm[$b]['p'][$perm_id];
                    }
                    if (isset($unknown_perms[$b]['p'][$perm_id])) {
                        unset($unknown_perms[$b]['p'][$perm_id]);
                    }

                    echo '<p><label for="perm' . Html::escapeHTML($b) . Html::escapeHTML($perm_id) . '" class="classic">' .
                    Form::checkbox(
                        ['perm[' . Html::escapeHTML($b) . '][' . Html::escapeHTML($perm_id) . ']', 'perm' . Html::escapeHTML($b) . Html::escapeHTML($perm_id)],
                        1,
                        $checked
                    ) . ' ' .
                    __($perm) . '</label></p>';
                }
                if (isset($unknown_perms[$b])) {
                    foreach ($unknown_perms[$b]['p'] as $perm_id => $v) {
                        $checked = isset($user_perm[$b]['p'][$perm_id]) && $user_perm[$b]['p'][$perm_id];
                        echo '<p><label for="perm' . Html::escapeHTML($b) . Html::escapeHTML($perm_id) . '" class="classic">' .
                        Form::checkbox(
                            ['perm[' . Html::escapeHTML($b) . '][' . Html::escapeHTML($perm_id) . ']',
                                'perm' . Html::escapeHTML($b) . Html::escapeHTML($perm_id), ],
                            1,
                            $checked
                        ) . ' ' .
                        sprintf(__('[%s] (unreferenced permission)'), $perm_id) . '</label></p>';
                    }
                }
            }

            echo '<p class="checkboxes-helpers"></p>' .
            '<div class="fieldset">' .
            '<h3>' . __('Validate permissions') . '</h3>' .
            '<p><label for="your_pwd" class="required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Your password:') . '</label>' .
            Form::password(
                'your_pwd',
                20,
                255,
                [
                    'extra_html'   => 'required placeholder="' . __('Password') . '"',
                    'autocomplete' => 'current-password',
                ]
            ) . '</p>' .
            '<p><input type="submit" accesskey="s" value="' . __('Save') . '" />' .
            $hidden_fields .
            App::core()->adminurl()->getHiddenFormFields('admin.user.actions', ['action' => 'updateperm'], true) . '</p>' .
                '</div>' .
                '</form>';
        }
    }
}
