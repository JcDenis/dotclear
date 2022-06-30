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
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\GPC\GPC;
use Dotclear\Process\Admin\Filter\Filter\UserFilters;
use Dotclear\Process\Admin\Inventory\Inventory\UserInventory;
use Dotclear\Process\Admin\Page\AbstractPage;

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

    protected function getFilterInstance(): ?UserFilters
    {
        return new UserFilters();
    }

    protected function getInventoryInstance(): ?UserInventory
    {
        $param = $this->filter->getParams();

        // lexical sort
        $sortby_lex = [
            // key in sorty_combo (see above) => field in SQL request
            'user_id'          => 'U.user_id',
            'user_name'        => 'user_name',
            'user_firstname'   => 'user_firstname',
            'user_displayname' => 'user_displayname', ];

        // --BEHAVIOR-- adminUsersSortbyLexCombo
        App::core()->behavior('adminUsersSortbyLexCombo')->call([&$sortby_lex]);

        $param->set('order', (
            array_key_exists($this->filter->getValue(id: 'sortby'), $sortby_lex) ?
            App::core()->con()->lexFields($sortby_lex[$this->filter->getValue(id: 'sortby')]) :
            $this->filter->getValue(id: 'sortby')
        ) . ' ' . $this->filter->getValue(id: 'order'));

        // --BEHAVIOR-- adminGetUsers, Param
        App::core()->behavior('adminGetUsers')->call($param);

        $rs       = App::core()->users()->getUsers(param: $param);
        $count    = App::core()->users()->countUsers(param: $param);
        $rsStatic = $rs->toStatic();
        if ('nb_post' != $this->filter->getValue(id: 'sortby')) {
            // Sort user list using lexical order if necessary
            $rsStatic->extend(new RsExtUser());
            // $rsStatic = $rsStatic->call('toExtStatic');
            $rsStatic->lexicalSort($this->filter->getValue(id: 'sortby'), $this->filter->getValue(id: 'order'));
        }

        return new UserInventory($rsStatic, $count);
    }

    protected function getPagePrepend(): ?bool
    {
        $this
            ->setPageTitle(__('Users'))
            ->setPageHelp('core_users')
            ->setPageHead(App::core()->resource()->load('_users.js') . $this->filter?->getFoldableJSCode())
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

        if (!GPC::get()->empty('del')) {
            App::core()->notice()->message(__('User has been successfully removed.'));
        } elseif (!GPC::get()->empty('upd')) {
            App::core()->notice()->message(__('The permissions have been successfully updated.'));
        }

        $combo_action = [
            __('Set permissions') => 'blogs',
            __('Delete')          => 'deleteuser',
        ];

        // --BEHAVIOR-- adminUsersActionsCombo
        App::core()->behavior('adminUsersActionsCombo')->call([&$combo_action]);

        echo '<p class="top-add"><strong><a class="button add" href="' . App::core()->adminurl()->get('admin.user') . '">' . __('New user') . '</a></strong></p>';

        $this->filter->displayHTMLForm(adminurl: 'admin.users');

        // Show users
        $this->inventory->display(
            $this->filter->getValue(id: 'page'),
            $this->filter->getValue(id: 'nb'),
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
            App::core()->adminurl()->getHiddenFormFields('admin.user.actions', $this->filter->getEscapeValues(), true) .
            '</form>',
            $this->filter->isUnfolded()
        );
    }
}
