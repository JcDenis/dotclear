<?php
/**
 * @class Dotclear\Admin\Page\Page\UserAction
 * @brief Dotclear admin user action page
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin\Page\Page;

use Dotclear\Admin\Page\Page;
use Dotclear\Exception\AdminException;
use Dotclear\Html\Form;
use Dotclear\Html\Html;
use Dotclear\Network\Http;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class UserAction extends Page
{
    private $user_action = '';
    private $user_redir  = '';
    private $users       = [];
    private $blogs       = [];

    protected function getPermissions(): string|null|false
    {
        return null;
    }

    protected function getPagePrepend(): ?bool
    {
        $this->users = [];
        if (!empty($_POST['users']) && is_array($_POST['users'])) {
            foreach ($_POST['users'] as $u) {
                if (dotclear()->users()->userExists($u)) {
                    $this->users[] = $u;
                }
            }
        }

        $this->blogs = [];
        if (!empty($_POST['blogs']) && is_array($_POST['blogs'])) {
            foreach ($_POST['blogs'] as $b) {
                if (dotclear()->blogs()->blogExists($b)) {
                    $this->blogs[] = $b;
                }
            }
        }

        if (!empty($_POST['action']) && !empty($_POST['users'])) {
            $this->user_action = $_POST['action'];

            if (isset($_POST['redir']) && strpos($_POST['redir'], '://') === false) {
                $this->redir = $_POST['redir'];
            } else {
                $this->redir = dotclear()->adminurl()->get('admin.users', [
                    'q'      => $_POST['q'] ?? '',
                    'sortby' => $_POST['sortby'] ?? '',
                    'order'  => $_POST['order'] ?? '',
                    'page'   => $_POST['page'] ?? '',
                    'nb'     => $_POST['nb'] ?? '',
                ], '&');
            }

            if (empty($this->users)) {
                dotclear()->error()->add(__('No blog or user given.'));
            }

            # --BEHAVIOR-- adminUsersActions
            dotclear()->behavior()->call('adminUsersActions', $this->users, $this->blogs, $this->user_action, $this->redir);

            # Delete users
            if ($this->user_action == 'deleteuser' && !empty($this->users)) {
                foreach ($this->users as $u) {
                    try {
                        if ($u == dotclear()->user()->userID()) {
                            throw new AdminException(__('You cannot delete yourself.'));
                        }

                        # --BEHAVIOR-- adminBeforeUserDelete
                        dotclear()->behavior()->call('adminBeforeUserDelete', $u);

                        dotclear()->users()->delUser($u);
                    } catch (\Exception $e) {
                        dotclear()->error()->add($e->getMessage());
                    }
                }
                if (!dotclear()->error()->flag()) {
                    dotclear()->notice()->addSuccessNotice(__('User has been successfully deleted.'));
                    Http::redirect($this->redir);
                }
            }

            # Update users perms
            if ($this->user_action == 'updateperm' && !empty($this->users) && !empty($this->blogs)) {
                try {
                    if (empty($_POST['your_pwd']) || !dotclear()->user()->checkPassword($_POST['your_pwd'])) {
                        throw new AdminException(__('Password verification failed'));
                    }

                    foreach ($this->users as $u) {
                        foreach ($this->blogs as $b) {
                            $set_perms = [];

                            if (!empty($_POST['perm'][$b])) {
                                foreach ($_POST['perm'][$b] as $perm_id => $v) {
                                    if ($v) {
                                        $set_perms[$perm_id] = true;
                                    }
                                }
                            }

                            dotclear()->users()->setUserBlogPermissions($u, $b, $set_perms, true);
                        }
                    }
                } catch (\Exception $e) {
                    dotclear()->error()->add($e->getMessage());
                }
                if (!dotclear()->error()->flag()) {
                    dotclear()->notice()->addSuccessNotice(__('User has been successfully updated.'));
                    Http::redirect($this->redir);
                }
            }
        }

        if (!empty($this->users) && empty($this->blogs) && $this->user_action == 'blogs') {
            $this->setPageBreadcrumb([
                __('System')      => '',
                __('Users')       => dotclear()->adminurl()->get('admin.users'),
                __('Permissions') => ''
            ]);
        } else {
            $this->setPageBreadcrumb([
                __('System')  => '',
                __('Users')   => dotclear()->adminurl()->get('admin.users'),
                __('Actions') => ''
            ]);
        }

        $this
            ->setPageTitle(__('Users'))
            ->setPageHelp('core_users')
            ->setPageHead(
                dotclear()->filer()->load('_users_actions.js') .
                # --BEHAVIOR-- adminUsersActionsHeaders
                dotclear()->behavior()->call('adminUsersActionsHeaders')
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

        if (isset($_POST['redir']) && strpos($_POST['redir'], '://') === false) {
            $hidden_fields .= Form::hidden(['redir'], Html::escapeURL($_POST['redir']));
        } else {
            $hidden_fields .=
            Form::hidden(['q'], Html::escapeHTML($_POST['q'] ?? '')) .
            Form::hidden(['sortby'], $_POST['sortby'] ?? '') .
            Form::hidden(['order'], $_POST['order'] ?? '') .
            Form::hidden(['page'], $_POST['page'] ?? '') .
            Form::hidden(['nb'], $_POST['nb'] ?? '');
        }

        echo '<p><a class="back" href="' . Html::escapeURL($this->redir) . '">' . __('Back to user profile') . '</a></p>';    // @phpstan-ignore-line

        # --BEHAVIOR-- adminUsersActionsContent
        dotclear()->behavior()->call('adminUsersActionsContent', $this->user_action, $hidden_fields);

        # Blog list where to set permissions
        if (!empty($this->users) && empty($this->blogs) && $this->user_action == 'blogs') {
            $rs        = null;
            $nb_blog   = 0;
            $user_list = [];

            try {
                $rs      = dotclear()->blogs()->getBlogs();
                $nb_blog = $rs->count();
            } catch (\Exception $e) {
            }

            foreach ($this->users as $u) {
                $user_list[] = '<a href="' . dotclear()->adminurl()->get('admin.user', ['id' => $u]) . '">' . $u . '</a>';
            }

            echo
            '<p>' . sprintf(
                __('Choose one or more blogs to which you want to give permissions to users %s.'),
                implode(', ', $user_list)
            ) . '</p>';

            if ($nb_blog == 0) {
                echo '<p><strong>' . __('No blog') . '</strong></p>';
            } else {
                echo
                '<form action="' . dotclear()->adminurl()->root() . '" method="post" id="form-blogs">' .
                '<div class="table-outer clear">' .
                '<table><tr>' .
                '<th class="nowrap" colspan="2">' . __('Blog ID') . '</th>' .
                '<th class="nowrap">' . __('Blog name') . '</th>' .
                '<th class="nowrap">' . __('URL') . '</th>' .
                '<th class="nowrap">' . __('Entries') . '</th>' .
                '<th class="nowrap">' . __('Status') . '</th>' .
                    '</tr>';

                while ($rs->fetch()) {
                    $img_status = $rs->blog_status == 1 ? 'check-on' : ($rs->blog_status == 0 ? 'check-off' : 'check-wrn');
                    $txt_status = dotclear()->blogs()->getBlogStatus((int) $rs->blog_status);
                    $img_status = sprintf('<img src="?df=images/%1$s.png" alt="%2$s" title="%2$s" />', $img_status, $txt_status);

                    echo
                    '<tr class="line">' .
                    '<td class="nowrap">' .
                    Form::checkbox(['blogs[]'], $rs->blog_id,
                        [
                            'extra_html' => 'title="' . __('select') . ' ' . $rs->blog_id . '"'
                        ]) .
                    '</td>' .
                    '<td class="nowrap">' . $rs->blog_id . '</td>' .
                    '<td class="maximal">' . Html::escapeHTML($rs->blog_name) . '</td>' .
                    '<td class="nowrap"><a class="outgoing" href="' . Html::escapeHTML($rs->blog_url) . '">' . Html::escapeHTML($rs->blog_url) .
                    ' <img src="?df=images/outgoing-link.svg" alt="" /></a></td>' .
                    '<td class="nowrap">' . dotclear()->blogs()->countBlogPosts($rs->blog_id) . '</td>' .
                        '<td class="status">' . $img_status . '</td>' .
                        '</tr>';
                }

                echo
                '</table></div>' .
                '<p class="checkboxes-helpers"></p>' .
                '<p><input id="do-action" type="submit" value="' . __('Set permissions') . '" />' .
                $hidden_fields .
                dotclear()->adminurl()->get('admin.user.actions', ['action' => 'perms'], true) . '</p>' .
                    '</form>';
            }

        # Permissions list for each selected blogs
        } elseif (!empty($this->blogs) && !empty($this->users) && $this->user_action == 'perms') {
            $user_perm = [];
            if (count($this->users) == 1) {
                $user_perm = dotclear()->users()->getUserPermissions($this->users[0]);
            }

            foreach ($this->users as $u) {
                $user_list[] = '<a href="' . dotclear()->adminurl()->get('admin.user', ['id' => $u]) . '">' . $u . '</a>';
            }

            echo
            '<p>' . sprintf(
                __('You are about to change permissions on the following blogs for users %s.'),
                implode(', ', $user_list)
            ) . '</p>' .
            '<form id="permissions-form" action="' . dotclear()->adminurl()->root() . '" method="post">';

            foreach ($this->blogs as $b) {
                echo '<h3>' . ('Blog:') . ' <a href="' . dotclear()->adminurl()->get('admin.blog', ['id' => Html::escapeHTML($b)]) . '">' . Html::escapeHTML($b) . '</a>' .
                Form::hidden(['blogs[]'], $b) . '</h3>';
                $unknown_perms = $user_perm;
                foreach (dotclear()->user()->getPermissionsTypes() as $perm_id => $perm) {
                    $checked = false;

                    if (count($this->users) == 1) {
                        $checked = isset($user_perm[$b]['p'][$perm_id]) && $user_perm[$b]['p'][$perm_id];
                    }
                    if (isset($unknown_perms[$b]['p'][$perm_id])) {
                        unset($unknown_perms[$b]['p'][$perm_id]);
                    }

                    echo
                    '<p><label for="perm' . Html::escapeHTML($b) . Html::escapeHTML($perm_id) . '" class="classic">' .
                    Form::checkbox(['perm[' . Html::escapeHTML($b) . '][' . Html::escapeHTML($perm_id) . ']', 'perm' . Html::escapeHTML($b) . Html::escapeHTML($perm_id)],
                        1, $checked) . ' ' .
                    __($perm) . '</label></p>';
                }
                if (isset($unknown_perms[$b])) {
                    foreach ($unknown_perms[$b]['p'] as $perm_id => $v) {
                        $checked = isset($user_perm[$b]['p'][$perm_id]) && $user_perm[$b]['p'][$perm_id];
                        echo
                        '<p><label for="perm' . Html::escapeHTML($b) . Html::escapeHTML($perm_id) . '" class="classic">' .
                        Form::checkbox(
                            ['perm[' . Html::escapeHTML($b) . '][' . Html::escapeHTML($perm_id) . ']',
                                'perm' . Html::escapeHTML($b) . Html::escapeHTML($perm_id)],
                            1, $checked) . ' ' .
                        sprintf(__('[%s] (unreferenced permission)'), $perm_id) . '</label></p>';
                    }
                }
            }

            echo
            '<p class="checkboxes-helpers"></p>' .
            '<div class="fieldset">' .
            '<h3>' . __('Validate permissions') . '</h3>' .
            '<p><label for="your_pwd" class="required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Your password:') . '</label>' .
            Form::password('your_pwd', 20, 255,
                [
                    'extra_html'   => 'required placeholder="' . __('Password') . '"',
                    'autocomplete' => 'current-password'
                ]
            ) . '</p>' .
            '<p><input type="submit" accesskey="s" value="' . __('Save') . '" />' .
            $hidden_fields .
            dotclear()->adminurl()->get('admin.user.actions', ['action' => 'updateperm'], true) .  '</p>' .
                '</div>' .
                '</form>';
        }
    }
}
