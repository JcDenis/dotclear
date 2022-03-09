<?php
/**
 * @class Dotclear\Process\Admin\Handler\Users
 * @brief Dotclear admin users list page
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
use Dotclear\Process\Admin\Action\Action;
use Dotclear\Process\Admin\Action\Action\UserAction;
use Dotclear\Process\Admin\Inventory\Inventory;
use Dotclear\Process\Admin\Inventory\Inventory\UserInventory;
use Dotclear\Process\Admin\Filter\Filter;
use Dotclear\Process\Admin\Filter\Filter\UserFilter;
use Dotclear\Html\Form;
use Dotclear\Html\Html;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Users extends Page
{
    protected function getPermissions(): string|null|false
    {
        return null;
    }

    protected function getFilterInstance(): ?Filter
    {
        return new UserFilter();
    }

    protected function GetInventoryInstance(): ?Inventory
    {
        $params = $this->filter->params();

        # lexical sort
        $sortby_lex = [
            // key in sorty_combo (see above) => field in SQL request
            'user_id'          => 'U.user_id',
            'user_name'        => 'user_name',
            'user_firstname'   => 'user_firstname',
            'user_displayname' => 'user_displayname'];

        # --BEHAVIOR-- adminUsersSortbyLexCombo
        dotclear()->behavior()->call('adminUsersSortbyLexCombo', [& $sortby_lex]);

        $params['order'] = (array_key_exists($this->filter->sortby, $sortby_lex) ?
            dotclear()->con()->lexFields($sortby_lex[$this->filter->sortby]) :
            $this->filter->sortby) . ' ' . $this->filter->order;

        $params = new ArrayObject($params);

        # --BEHAVIOR-- adminGetUsers
        dotclear()->behavior()->call('adminGetUsers', $params);

        $rs       = dotclear()->users()->getUsers($params);
        $counter  = dotclear()->users()->getUsers($params, true);
        $rsStatic = $rs->toStatic();
        if ($this->filter->sortby != 'nb_post') {
            // Sort user list using lexical order if necessary
            $rsStatic->extend('Dotclear\\Core\\RsExt\\RsExtUser');
            $rsStatic = $rsStatic->toExtStatic();
            $rsStatic->lexicalSort($this->filter->sortby, $this->filter->order);
        }

        return new UserInventory($rsStatic, (int) $counter->f(0));
    }

    protected function getPagePrepend(): ?bool
    {
        $this
            ->setPageTitle(__('Users'))
            ->setPageHelp('core_users')
            ->setPageHead(dotclear()->resource()->load('_users.js') . $this->filter->js())
            ->setPageBreadcrumb([
                __('System') => '',
                __('Users')  => ''
            ])
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        if (dotclear()->error()->flag()) {
            return;
        }

        if (!empty($_GET['del'])) {
            dotclear()->notice()->message(__('User has been successfully removed.'));
        }
        if (!empty($_GET['upd'])) {
            dotclear()->notice()->message(__('The permissions have been successfully updated.'));
        }

        $combo_action = [
            __('Set permissions') => 'blogs',
            __('Delete')          => 'deleteuser'
        ];

        # --BEHAVIOR-- adminUsersActionsCombo
        dotclear()->behavior()->call('adminUsersActionsCombo', [& $combo_action]);

        echo '<p class="top-add"><strong><a class="button add" href="' . dotclear()->adminurl()->get('admin.user') . '">' . __('New user') . '</a></strong></p>';

        $this->filter->display('admin.users');

        # Show users
        $this->inventory->display(
            $this->filter->page,
            $this->filter->nb,
            '<form action="' . dotclear()->adminurl()->root() . '" method="post" id="form-users">' .

            '%s' .

            '<div class="two-cols">' .
            '<p class="col checkboxes-helpers"></p>' .

            '<p class="col right"><label for="action" class="classic">' .
            __('Selected users action:') . ' ' .
            Form::combo('action', $combo_action) .
            '</label> ' .
            '<input id="do-action" type="submit" value="' . __('ok') . '" />' .
            '</p>' .
            '</div>' .
            dotclear()->adminurl()->getHiddenFormFields('admin.user.actions', $this->filter->values(true), true) .
            '</form>',
            $this->filter->show()
        );
    }
}
