<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Handler;

// Dotclear\Process\Admin\Handler\Users
use Dotclear\App;
use Dotclear\Core\RsExt\RsExtUser;
use Dotclear\Database\Param;
use Dotclear\Process\Admin\Page\AbstractPage;
use Dotclear\Process\Admin\Inventory\Inventory\UserInventory;
use Dotclear\Process\Admin\Filter\Filter\UserFilter;
use Dotclear\Helper\Html\Form;

/**
 * Admin users list page.
 *
 * @ingroup  Admin User Handler
 */
class Users extends AbstractPage
{
    protected function getPermissions(): string|bool
    {
        return '';
    }

    protected function getFilterInstance(): ?UserFilter
    {
        return new UserFilter();
    }

    protected function getInventoryInstance(): ?UserInventory
    {
        $param = $this->filter->params();

        // lexical sort
        $sortby_lex = [
            // key in sorty_combo (see above) => field in SQL request
            'user_id'          => 'U.user_id',
            'user_name'        => 'user_name',
            'user_firstname'   => 'user_firstname',
            'user_displayname' => 'user_displayname', ];

        // --BEHAVIOR-- adminUsersSortbyLexCombo
        App::core()->behavior()->call('adminUsersSortbyLexCombo', [&$sortby_lex]);

        $param->set('order', (
            array_key_exists($this->filter->get('sortby'), $sortby_lex) ?
            App::core()->con()->lexFields($sortby_lex[$this->filter->get('sortby')]) :
            $this->filter->get('sortby')
        ) . ' ' . $this->filter->get('order'));

        // --BEHAVIOR-- adminGetUsers, Param
        App::core()->behavior()->call('adminGetUsers', $param);

        $rs       = App::core()->users()->getUsers(param: $param);
        $count    = App::core()->users()->countUsers(param: $param);
        $rsStatic = $rs->toStatic();
        if ('nb_post' != $this->filter->get('sortby')) {
            // Sort user list using lexical order if necessary
            $rsStatic->extend(new RsExtUser());
            // $rsStatic = $rsStatic->toExtStatic();
            $rsStatic->lexicalSort($this->filter->get('sortby'), $this->filter->get('order'));
        }

        return new UserInventory($rsStatic, $count);
    }

    protected function getPagePrepend(): ?bool
    {
        $this
            ->setPageTitle(__('Users'))
            ->setPageHelp('core_users')
            ->setPageHead(App::core()->resource()->load('_users.js') . $this->filter->js())
            ->setPageBreadcrumb([
                __('System') => '',
                __('Users')  => '',
            ])
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        if (App::core()->error()->flag()) {
            return;
        }

        if (!empty($_GET['del'])) {
            App::core()->notice()->message(__('User has been successfully removed.'));
        }
        if (!empty($_GET['upd'])) {
            App::core()->notice()->message(__('The permissions have been successfully updated.'));
        }

        $combo_action = [
            __('Set permissions') => 'blogs',
            __('Delete')          => 'deleteuser',
        ];

        // --BEHAVIOR-- adminUsersActionsCombo
        App::core()->behavior()->call('adminUsersActionsCombo', [&$combo_action]);

        echo '<p class="top-add"><strong><a class="button add" href="' . App::core()->adminurl()->get('admin.user') . '">' . __('New user') . '</a></strong></p>';

        $this->filter->display('admin.users');

        // Show users
        $this->inventory->display(
            $this->filter->get('page'),
            $this->filter->get('nb'),
            '<form action="' . App::core()->adminurl()->root() . '" method="post" id="form-users">' .

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
            App::core()->adminurl()->getHiddenFormFields('admin.user.actions', $this->filter->values(true), true) .
            '</form>',
            $this->filter->show()
        );
    }
}
