<?php
/**
 * @class Dotclear\Admin\Page\Users
 * @brief Dotclear admin users list page
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin\Page;

use Dotclear\Exception;
use Dotclear\Exception\AdminException;

use Dotclear\Core\Core;

use Dotclear\Admin\Page;
use Dotclear\Admin\Action\UserAction;
use Dotclear\Admin\Catalog\UserCatalog;
use Dotclear\Admin\Filter\UserFilter;

use Dotclear\Html\Form;
use Dotclear\Html\Html;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Users extends Page
{
    public function __construct(Core $core)
    {
        parent::__construct($core);

        $this->checkSuper();

        /* Actions
        -------------------------------------------------------- */
        $combo_action = [
            __('Set permissions') => 'blogs',
            __('Delete')          => 'deleteuser'
        ];

        # --BEHAVIOR-- adminUsersActionsCombo
        $this->core->behaviors->call('adminUsersActionsCombo', [& $combo_action]);

        /* Filters
        -------------------------------------------------------- */
        $user_filter = new UserFilter($this->core);

        # get list params
        $params = $user_filter->params();

        # lexical sort
        $sortby_lex = [
            // key in sorty_combo (see above) => field in SQL request
            'user_id'          => 'U.user_id',
            'user_name'        => 'user_name',
            'user_firstname'   => 'user_firstname',
            'user_displayname' => 'user_displayname'];

        # --BEHAVIOR-- adminUsersSortbyLexCombo
        $this->core->behaviors->call('adminUsersSortbyLexCombo', [& $sortby_lex]);

        $params['order'] = (array_key_exists($user_filter->sortby, $sortby_lex) ?
            $this->core->con->lexFields($sortby_lex[$user_filter->sortby]) :
            $user_filter->sortby) . ' ' . $user_filter->order;

        /* List
        -------------------------------------------------------- */
        $user_list = null;

        try {
            # --BEHAVIOR-- adminGetUsers
            $params = new \ArrayObject($params);
            $this->core->behaviors->call('adminGetUsers', $params);

            $rs       = $this->core->getUsers($params);
            $counter  = $this->core->getUsers($params, true);
            $rsStatic = $rs->toStatic();
            if ($user_filter->sortby != 'nb_post') {
                // Sort user list using lexical order if necessary
                $rsStatic->extend('Dotclear\\Core\\RsExt\\RsExtUser');
                $rsStatic = $rsStatic->toExtStatic();
                $rsStatic->lexicalSort($user_filter->sortby, $user_filter->order);
            }
            $user_list = new UserCatalog($this->core, $rsStatic, $counter->f(0));
        } catch (Exception $e) {
            $this->core->error->add($e->getMessage());
        }

        /* DISPLAY
        -------------------------------------------------------- */

        $this->open(__('Users'),
            static::jsLoad('js/_users.js') . $user_filter->js(),
            $this->breadcrumb(
                [
                    __('System') => '',
                    __('Users')  => ''
                ])
        );

        if (!$this->core->error->flag()) {
            if (!empty($_GET['del'])) {
                static::message(__('User has been successfully removed.'));
            }
            if (!empty($_GET['upd'])) {
                static::message(__('The permissions have been successfully updated.'));
            }

            echo '<p class="top-add"><strong><a class="button add" href="' . $this->core->adminurl->get('admin.user') . '">' . __('New user') . '</a></strong></p>';

            $user_filter->display('admin.users');

            # Show users
            $user_list->display(
                $user_filter->page,
                $user_filter->nb,
                '<form action="' . $this->core->adminurl->get('admin.users') . '" method="post" id="form-users">' .

                '%s' .

                '<div class="two-cols">' .
                '<p class="col checkboxes-helpers"></p>' .

                '<p class="col right"><label for="action" class="classic">' .
                __('Selected users action:') . ' ' .
                Form::combo('action', $combo_action) .
                '</label> ' .
                '<input id="do-action" type="submit" value="' . __('ok') . '" />' .
                $this->core->formNonce() .
                '</p>' .
                '</div>' .
                $this->core->adminurl->getHiddenFormFields('admin.user.actions', $user_filter->values(true)) .
                '</form>',
                $user_filter->show()
            );
        }
        $this->helpBlock('core_users');
        $this->close();
    }
}
