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
use Dotclear\App;
use Dotclear\Helper\Clock;
use Dotclear\Helper\GPC\GPC;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Mapper\Strings;
use Dotclear\Plugin\Maintenance\Admin\Lib\Maintenance;
use Dotclear\Process\Admin\Favorite\Favorite;
use Dotclear\Process\Admin\Favorite\FavoriteItem;
use Dotclear\Process\Admin\Favorite\DashboardIcon;
use Dotclear\Process\Admin\Help\HelpBlocks;

/**
 * Admin behaviors for plugin Maintenance.
 *
 * @ingroup  Plugin Maintenance Behavior
 */
class MaintenanceBehavior
{
    public function __construct()
    {
        App::core()->behavior('dcMaintenanceInit')->add([$this, 'behaviorDcMaintenanceInit']);
        App::core()->behavior('adminAfterSetDefaultFavoriteItems')->add([$this, 'adminAfterSetDefaultFavoriteItems']);
        App::core()->behavior('adminBeforeAddDashboardContents')->add([$this, 'behaviorAdminBeforeAddDashboardItems']);
        App::core()->behavior('adminDashboardOptionsForm')->add([$this, 'behaviorAdminDashboardOptionsForm']);
        App::core()->behavior('adminAfterDashboardOptionsUpdate')->add([$this, 'behaviorAdminAfterDashboardOptionsUpdate']);
        App::core()->behavior('adminBeforeGetPageHelpBlocks')->add([$this, 'behaviorAdminPageHelpBlock']);
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
     * @param Favorite $favorite Favorite instance
     */
    public function adminAfterSetDefaultFavoriteItems(Favorite $favorite): void
    {
        $favorite->addItem(new FavoriteItem(
            id: 'maintenance',
            title: __('Maintenance'),
            url: App::core()->adminurl()->get('admin.plugin.Maintenance'),
            icons: ['Plugin/Maintenance/icon.svg', 'Plugin/Maintenance/icon-dark.svg'],
            permission: 'admin',
            dashboard: [$this, 'behaviorAdminDashboardFavoritesCallback'],
        ));
    }

    /**
     * Favorites hack.
     *
     * This updates maintenance fav icon text
     * if there are tasks required maintenance.
     *
     * @param DashboardIcon $icon The dashboard favorite icon
     */
    public function behaviorAdminDashboardFavoritesCallback(DashboardIcon $icon): void
    {
        // Check user option
        if (!App::core()->user()->preferences()->getGroup('maintenance')->getPreference('dashboard_icon')) {
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

        $icon->appendTitle('<br />' . sprintf(__('One task to execute', '%s tasks to execute', $count), $count));
        $icon->replaceIcons(['Plugin/Maintenance/icon-update.svg', 'Plugin/Maintenance/icon-dark-update.svg']);
    }

    /**
     * Dashboard items stack.
     *
     * @param Strings $items The items
     */
    public function behaviorAdminBeforeAddDashboardItems(Strings $items): void
    {
        if (!App::core()->user()->preferences()->getGroup('maintenance')->getPreference('dashboard_item')) {
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
                        to: App::core()->getTimezone()
                    )
                )
            ) . '">' . $t->task() . '</li>';
        }

        if (empty($lines)) {
            return;
        }

        $items->add(
            '<div id="maintenance-expired" class="box small">' .
            '<h3>' . App::core()->menu()->getIconTheme(['Plugin/Maintenance/icon.svg', 'Plugin/Maintenance/icon-dark.svg'], true, '', '', 'icon-small') . __('Maintenance') . '</h3>' .
            '<p class="warning no-margin">' . sprintf(__('There is a task to execute.', 'There are %s tasks to execute.', count($lines)), count($lines)) . '</p>' .
            '<ul>' . implode('', $lines) . '</ul>' .
            '<p><a href="' . App::core()->adminurl()->get('admin.plugin.Maintenance') . '">' . __('Manage tasks') . '</a></p>' .
            '</div>',
        );
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
        Form::checkbox('maintenance_dashboard_icon', 1, App::core()->user()->preferences()->getGroup('maintenance')->getPreference('dashboard_icon')) .
        __('Display overdue tasks counter on maintenance dashboard icon') . '</label></p>' .

        '<p><label for="maintenance_dashboard_item" class="classic">' .
        Form::checkbox('maintenance_dashboard_item', 1, App::core()->user()->preferences()->getGroup('maintenance')->getPreference('dashboard_item')) .
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

        App::core()->user()->preferences()->getGroup('maintenance')->putPreference('dashboard_icon', !GPC::post()->empty('maintenance_dashboard_icon'), 'boolean');
        App::core()->user()->preferences()->getGroup('maintenance')->putPreference('dashboard_item', !GPC::post()->empty('maintenance_dashboard_item'), 'boolean');
    }

    /**
     * Build a well sorted help for tasks.
     *
     * This method is not so good if used with lot of tranlsations
     * as it grows memory usage and translations files size,
     * it is better to use help ressource files
     * but keep it for exemple of how to use behavior adminBeforeGetPageHelpBlocks.
     * Cheers, JC
     *
     * @param HelpBlocks $blocks The blocks
     */
    public function behaviorAdminPageHelpBlock(HelpBlocks $blocks): void
    {
        if (!$blocks->hasResource('maintenancetasks')) {
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
            $blocks->addContent($res_tab);
        }
    }
}
