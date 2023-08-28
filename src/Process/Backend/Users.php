<?php
/**
 * @since 2.27 Before as admin/users.php
 *
 * @package Dotclear
 * @subpackage Backend
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Backend;

use ArrayObject;
use dcCore;
use Dotclear\Core\Backend\Filter\FilterUsers;
use Dotclear\Core\Backend\Listing\ListingUsers;
use Dotclear\Core\Backend\Notices;
use Dotclear\Core\Backend\Page;
use Dotclear\Core\Core;
use Dotclear\Core\Process;
use Dotclear\Helper\Html\Form\Div;
use Dotclear\Helper\Html\Form\Form;
use Dotclear\Helper\Html\Form\Label;
use Dotclear\Helper\Html\Form\Para;
use Dotclear\Helper\Html\Form\Select;
use Dotclear\Helper\Html\Form\Submit;
use Dotclear\Helper\Html\Form\Text;
use Exception;

class Users extends Process
{
    public static function init(): bool
    {
        Page::checkSuper();

        // Actions
        $combo_action = [
            __('Set permissions') => 'blogs',
            __('Delete')          => 'deleteuser',
        ];

        # --BEHAVIOR-- adminUsersActionsCombo -- array<int,array<string,string>>
        Core::behavior()->callBehavior('adminUsersActionsCombo', [& $combo_action]);

        dcCore::app()->admin->combo_action = $combo_action;

        // Filters
        dcCore::app()->admin->user_filter = new FilterUsers();

        // get list params
        $params = dcCore::app()->admin->user_filter->params();

        // lexical sort
        $sortby_lex = [
            // key in sorty_combo (see above) => field in SQL request
            'user_id'          => 'U.user_id',
            'user_name'        => 'user_name',
            'user_firstname'   => 'user_firstname',
            'user_displayname' => 'user_displayname', ];

        # --BEHAVIOR-- adminUsersSortbyLexCombo -- array<int,array<string,string>>
        Core::behavior()->callBehavior('adminUsersSortbyLexCombo', [& $sortby_lex]);

        $params['order'] = (array_key_exists(dcCore::app()->admin->user_filter->sortby, $sortby_lex) ?
            dcCore::app()->con->lexFields($sortby_lex[dcCore::app()->admin->user_filter->sortby]) :
            dcCore::app()->admin->user_filter->sortby) . ' ' . dcCore::app()->admin->user_filter->order;

        // List
        dcCore::app()->admin->user_list = null;

        try {
            # --BEHAVIOR-- adminGetUsers
            $params = new ArrayObject($params);
            # --BEHAVIOR-- adminGetUsers -- ArrayObject
            Core::behavior()->callBehavior('adminGetUsers', $params);

            $rs       = dcCore::app()->getUsers($params);
            $counter  = dcCore::app()->getUsers($params, true);
            $rsStatic = $rs->toStatic();
            if (dcCore::app()->admin->user_filter->sortby != 'nb_post') {
                // Sort user list using lexical order if necessary
                $rsStatic->extend('rsExtUser');
                $rsStatic = $rsStatic->toExtStatic();
                $rsStatic->lexicalSort(dcCore::app()->admin->user_filter->sortby, dcCore::app()->admin->user_filter->order);
            }
            dcCore::app()->admin->user_list = new ListingUsers($rsStatic, $counter->f(0));
        } catch (Exception $e) {
            dcCore::app()->error->add($e->getMessage());
        }

        return self::status(true);
    }

    public static function render(): void
    {
        Page::open(
            __('Users'),
            Page::jsLoad('js/_users.js') . dcCore::app()->admin->user_filter->js(dcCore::app()->admin->url->get('admin.users')),
            Page::breadcrumb(
                [
                    __('System') => '',
                    __('Users')  => '',
                ]
            )
        );

        if (!dcCore::app()->error->flag()) {
            if (!empty($_GET['del'])) {
                Notices::message(__('User has been successfully removed.'));
            }
            if (!empty($_GET['upd'])) {
                Notices::message(__('The permissions have been successfully updated.'));
            }

            echo '<p class="top-add"><a class="button add" href="' . dcCore::app()->admin->url->get('admin.user') . '">' . __('New user') . '</a></p>';

            dcCore::app()->admin->user_filter->display('admin.users');

            // Show users
            dcCore::app()->admin->user_list->display(
                dcCore::app()->admin->user_filter->page,
                dcCore::app()->admin->user_filter->nb,
                (new Form('form-users'))
                    ->action(dcCore::app()->admin->url->get('admin.user.actions'))
                    ->method('post')
                    ->fields([
                        new Text('', '%s'),
                        (new Div())
                            ->class('two-cols')
                             ->items([
                                 (new Para())->class(['col checkboxes-helpers']),
                                 (new Para())->class(['col right'])->items([
                                     (new Select('action'))
                                         ->class('online')
                                         ->title(__('Actions'))
                                         ->label(
                                             (new Label(
                                                 __('Selected users action:'),
                                                 Label::OUTSIDE_LABEL_BEFORE
                                             ))
                                             ->class('classic')
                                         )
                                         ->items(dcCore::app()->admin->combo_action),
                                     Core::nonce()->formNonce(),
                                     (new Submit('do-action'))
                                         ->value(__('ok')),
                                     ...dcCore::app()->admin->url->hiddenFormFields('admin.user.actions', dcCore::app()->admin->user_filter->values(true)),
                                 ]),
                             ]),
                    ])
                    ->render(),
                dcCore::app()->admin->user_filter->show()
            );
        }
        Page::helpBlock('core_users');
        Page::close();
    }
}
