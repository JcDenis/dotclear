<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Maintenance\Admin;

// Dotclear\Plugin\Maintenance\Admin\MaintenanceBehavior
use ArrayObject;
use Dotclear\App;
use Dotclear\Helper\Clock;
use Dotclear\Helper\GPC\GPC;
use Dotclear\Helper\Html\Form;
use Dotclear\Plugin\Maintenance\Admin\Lib\Maintenance;
use Dotclear\Process\Admin\Favorite\Favorite;

/**
 * Admin behaviors for plugin Maintenance.
 *
 * @ingroup  Plugin Maintenance Behavior
 */
class MaintenanceBehavior
{
    public function __construct()
    {
        App::core()->behavior()->add('dcMaintenanceInit', [$this, 'behaviorDcMaintenanceInit']);
        App::core()->behavior()->add('adminDashboardFavorites', [$this, 'behaviorAdminDashboardFavorites']);
        App::core()->behavior()->add('adminDashboardContents', [$this, 'behaviorAdminDashboardItems']);
        App::core()->behavior()->add('adminDashboardOptionsForm', [$this, 'behaviorAdminDashboardOptionsForm']);
        App::core()->behavior()->add('adminAfterDashboardOptionsUpdate', [$this, 'behaviorAdminAfterDashboardOptionsUpdate']);
        App::core()->behavior()->add('adminPageHelpBlock', [$this, 'behaviorAdminPageHelpBlock']);
    }

    /**
     * Register default tasks.
     *
     * @param Maintenance $maintenance Maintenance instance
     */
    public function behaviorDcMaintenanceInit(Maintenance $maintenance): void
    {
        $ns = __NAMESPACE__ . '\\Lib\\Task\\';

        $maintenance
            ->addTab('maintenance', __('Servicing'), ['summary' => __('Tools to maintain the performance of your blogs.')])
            ->addTab('backup', __('Backup'), ['summary' => __('Tools to back up your content.')])
            ->addTab('dev', __('Development'), ['summary' => __('Tools to assist in development of plugins, themes and core.')])

            ->addGroup('optimize', __('Optimize'))
            ->addGroup('index', __('Count and index'))
            ->addGroup('purge', __('Purge'))
            ->addGroup('other', __('Other'))
            ->addGroup('zipblog', __('Current blog'))
            ->addGroup('zipfull', __('All blogs'))

            ->addGroup('l10n', __('Translations'), ['summary' => __('Maintain translations')])

            ->addTask($ns . 'MaintenanceTaskCache')
            ->addTask($ns . 'MaintenanceTaskRepo')
            ->addTask($ns . 'MaintenanceTaskCSP')
            ->addTask($ns . 'MaintenanceTaskIndexposts')
            ->addTask($ns . 'MaintenanceTaskIndexcomments')
            ->addTask($ns . 'MaintenanceTaskCountcomments')
            ->addTask($ns . 'MaintenanceTaskSynchpostsmeta')
            ->addTask($ns . 'MaintenanceTaskLogs')
            ->addTask($ns . 'MaintenanceTaskVacuum')
            ->addTask($ns . 'MaintenanceTaskZipmedia')
            ->addTask($ns . 'MaintenanceTaskZiptheme')
        ;
    }

    /**
     * Favorites.
     *
     * @param Favorite $favs Favorite instance
     */
    public function behaviorAdminDashboardFavorites(Favorite $favs): void
    {
        $favs->register('maintenance', [
            'title'        => __('Maintenance'),
            'url'          => App::core()->adminurl()->get('admin.plugin.Maintenance'),
            'icons'        => ['Plugin/Maintenance/icon.svg', 'Plugin/Maintenance/icon-dark.svg'],
            'permissions'  => 'admin',
            'active_cb'    => App::core()->adminurl()->is('admin.plugin.Maintenance'),
            'dashboard_cb' => [$this, 'behaviorAdminDashboardFavoritesCallback'],
        ]);
    }

    /**
     * Favorites hack.
     *
     * This updates maintenance fav icon text
     * if there are tasks required maintenance.
     *
     * @param ArrayObject $fav The fav
     */
    public function behaviorAdminDashboardFavoritesCallback(ArrayObject $fav): void
    {
        // Check user option
        if (!App::core()->user()->preference()->get('maintenance')->get('dashboard_icon')) {
            return;
        }

        // Check expired tasks
        $maintenance = new Maintenance();
        $count       = 0;
        foreach ($maintenance->getTasks() as $t) {
            if (false !== $t->expired()) {
                ++$count;
            }
        }

        if (!$count) {
            return;
        }

        $fav['title'] .= '<br />' . sprintf(__('One task to execute', '%s tasks to execute', $count), $count);
        $fav['icons'] = ['Plugin/Maintenance/icon-update.svg', 'Plugin/Maintenance/icon-dark-update.svg'];
    }

    /**
     * Dashboard items stack.
     *
     * @param ArrayObject $items The items
     */
    public function behaviorAdminDashboardItems(ArrayObject $items): void
    {
        if (!App::core()->user()->preference()->get('maintenance')->get('dashboard_item')) {
            return;
        }

        $maintenance = new Maintenance();

        $lines = [];
        foreach ($maintenance->getTasks() as $t) {
            $ts = $t->expired();
            if (false === $ts) {
                continue;
            }

            $lines[] = '<li title="' . (
                null === $ts ?
                __('This task has never been executed.')
                :
                sprintf(
                    __('Last execution of this task was on %s.'),
                    Clock::str(
                        format: App::core()->blog()->settings()->getGroup('system')->getSetting('date_format') . ' ' . App::core()->blog()->settings()->getGroup('system')->getSetting('time_format'),
                        date: $ts,
                        to: App::core()->timezone()
                    )
                )
            ) . '">' . $t->task() . '</li>';
        }

        if (empty($lines)) {
            return;
        }

        $items[] = new ArrayObject([
            '<div id="maintenance-expired" class="box small">' .
            '<h3>' . App::core()->summary()->getIconTheme(['Plugin/Maintenance/icon.svg', 'Plugin/Maintenance/icon-dark.svg'], true, '', '', 'icon-small') . __('Maintenance') . '</h3>' .
            '<p class="warning no-margin">' . sprintf(__('There is a task to execute.', 'There are %s tasks to execute.', count($lines)), count($lines)) . '</p>' .
            '<ul>' . implode('', $lines) . '</ul>' .
            '<p><a href="' . App::core()->adminurl()->get('admin.plugin.Maintenance') . '">' . __('Manage tasks') . '</a></p>' .
            '</div>',
        ]);
    }

    /**
     * User preferences form.
     *
     * This add options for superadmin user
     * to show or not expired taks.
     */
    public function behaviorAdminDashboardOptionsForm(): void
    {
        echo '<div class="fieldset">' .
        '<h4>' . __('Maintenance') . '</h4>' .

        '<p><label for="maintenance_dashboard_icon" class="classic">' .
        Form::checkbox('maintenance_dashboard_icon', 1, App::core()->user()->preference()->get('maintenance')->get('dashboard_icon')) .
        __('Display overdue tasks counter on maintenance dashboard icon') . '</label></p>' .

        '<p><label for="maintenance_dashboard_item" class="classic">' .
        Form::checkbox('maintenance_dashboard_item', 1, App::core()->user()->preference()->get('maintenance')->get('dashboard_item')) .
        __('Display overdue tasks list on dashboard items') . '</label></p>' .

            '</div>';
    }

    /**
     * User preferences update.
     *
     * @param string $user_id The user identifier
     */
    public function behaviorAdminAfterDashboardOptionsUpdate(string $user_id = null): void
    {
        if (is_null($user_id)) {
            return;
        }

        App::core()->user()->preference()->get('maintenance')->put('dashboard_icon', !GPC::post()->empty('maintenance_dashboard_icon'), 'boolean');
        App::core()->user()->preference()->get('maintenance')->put('dashboard_item', !GPC::post()->empty('maintenance_dashboard_item'), 'boolean');
    }

    /**
     * Build a well sorted help for tasks.
     *
     * This method is not so good if used with lot of tranlsations
     * as it grows memory usage and translations files size,
     * it is better to use help ressource files
     * but keep it for exemple of how to use behavior adminPageHelpBlock.
     * Cheers, JC
     *
     * @param ArrayObject $blocks The blocks
     */
    public function behaviorAdminPageHelpBlock(ArrayObject $blocks): void
    {
        $found = false;
        foreach ($blocks as $block) {
            if ('maintenancetasks' == $block) {
                $found = true;

                break;
            }
        }
        if (!$found) {
            return;
        }

        $maintenance = new Maintenance();

        $res_tab = '';
        foreach ($maintenance->getTabs() as $tab_obj) {
            $res_group = '';
            foreach ($maintenance->getGroups() as $group_obj) {
                $res_task = '';
                foreach ($maintenance->getTasks() as $t) {
                    if ($t->group() != $group_obj->id()
                        || $t->tab() != $tab_obj->id()) {
                        continue;
                    }
                    if ('' != ($desc = $t->description())) {
                        $res_task .= '<dt>' . $t->task() . '</dt>' .
                            '<dd>' . $desc . '</dd>';
                    }
                }
                if (!empty($res_task)) {
                    $desc = $group_obj->option('description') ?: $group_obj->option('summary');

                    $res_group .= '<h5>' . $group_obj->name() . '</h5>' .
                        ($desc ? '<p>' . $desc . '</p>' : '') .
                        '<dl>' . $res_task . '</dl>';
                }
            }
            if (!empty($res_group)) {
                $desc = $tab_obj->option('description') ?: $tab_obj->option('summary');

                $res_tab .= '<h4>' . $tab_obj->name() . '</h4>' .
                    ($desc ? '<p>' . $desc . '</p>' : '') .
                    $res_group;
            }
        }
        if (!empty($res_tab)) {
            $res = new ArrayObject();
            $res->offsetSet('content', $res_tab);
            $blocks[] = $res;
        }
    }
}
