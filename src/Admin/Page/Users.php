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

use ArrayObject;


use Dotclear\Admin\Page;
use Dotclear\Admin\Action;
use Dotclear\Admin\Filter;
use Dotclear\Admin\Catalog;

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
    protected function getPermissions(): string|null|false
    {
        return null;
    }

    protected function getFilterInstance(): ?Filter
    {
        return new UserFilter();
    }

    protected function getCatalogInstance(): ?Catalog
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
        dotclear()->behaviors->call('adminUsersSortbyLexCombo', [& $sortby_lex]);

        $params['order'] = (array_key_exists($this->filter->sortby, $sortby_lex) ?
            dotclear()->con->lexFields($sortby_lex[$this->filter->sortby]) :
            $this->filter->sortby) . ' ' . $this->filter->order;

        $params = new ArrayObject($params);

        # --BEHAVIOR-- adminGetUsers
        dotclear()->behaviors->call('adminGetUsers', $params);

        $rs       = dotclear()->getUsers($params);
        $counter  = dotclear()->getUsers($params, true);
        $rsStatic = $rs->toStatic();
        if ($this->filter->sortby != 'nb_post') {
            // Sort user list using lexical order if necessary
            $rsStatic->extend('Dotclear\\Core\\RsExt\\RsExtUser');
            $rsStatic = $rsStatic->toExtStatic();
            $rsStatic->lexicalSort($this->filter->sortby, $this->filter->order);
        }

        return new UserCatalog($rsStatic, $counter->f(0));
    }

    protected function getPagePrepend(): ?bool
    {
        $this
            ->setPageTitle(__('Users'))
            ->setPageHelp('core_users')
            ->setPageHead(static::jsLoad('js/_users.js') . $this->filter->js())
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
            dotclear()->notices->message(__('User has been successfully removed.'));
        }
        if (!empty($_GET['upd'])) {
            dotclear()->notices->message(__('The permissions have been successfully updated.'));
        }

        $combo_action = [
            __('Set permissions') => 'blogs',
            __('Delete')          => 'deleteuser'
        ];

        # --BEHAVIOR-- adminUsersActionsCombo
        dotclear()->behaviors->call('adminUsersActionsCombo', [& $combo_action]);

        echo '<p class="top-add"><strong><a class="button add" href="' . dotclear()->adminurl->get('admin.user') . '">' . __('New user') . '</a></strong></p>';

        $this->filter->display('admin.users');

        # Show users
        $this->catalog->display(
            $this->filter->page,
            $this->filter->nb,
            '<form action="' . dotclear()->adminurl->get('admin.users') . '" method="post" id="form-users">' .

            '%s' .

            '<div class="two-cols">' .
            '<p class="col checkboxes-helpers"></p>' .

            '<p class="col right"><label for="action" class="classic">' .
            __('Selected users action:') . ' ' .
            Form::combo('action', $combo_action) .
            '</label> ' .
            '<input id="do-action" type="submit" value="' . __('ok') . '" />' .
            dotclear()->formNonce() .
            '</p>' .
            '</div>' .
            dotclear()->adminurl->getHiddenFormFields('admin.user.actions', $this->filter->values(true)) .
            '</form>',
            $this->filter->show()
        );
    }
}
