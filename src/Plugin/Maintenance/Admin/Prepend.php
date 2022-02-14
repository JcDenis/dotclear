<?php
/**
 * @class Dotclear\Plugin\Maintenance\Admin\Prepend
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginMaintenance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Maintenance\Admin;

use ArrayObject;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependAdmin;

use Dotclear\Admin\Favorites;
use Dotclear\Plugin\Maintenance\Lib\Maintenance;
use Dotclear\Html\Form;
use Dotclear\Utils\Dt;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Prepend extends AbstractPrepend
{
    use TraitPrependAdmin;

    public static function loadModule(): void
    {
        # Menu
        static::addStandardMenu('Plugins');

        # Workspace
        dotclear()->auth()->user_prefs->addWorkspace('maintenance');
        dotclear()->blog()->settings->addNamespace('maintenance');

        # Rest service
        dotclear()->rest()->addFunction('dcMaintenanceStep', ['Dotclear\\Plugin\\Maintenance\\Lib\\MaintenanceRest', 'step']);

        # Admin behaviors
        dotclear()->behavior()->add('dcMaintenanceInit', [__CLASS__, 'behaviorDcMaintenanceInit']);
        dotclear()->behavior()->add('adminDashboardFavorites', [__CLASS__, 'behaviorAdminDashboardFavorites']);
        dotclear()->behavior()->add('adminDashboardContents', [__CLASS__, 'behaviorAdminDashboardItems']);
        dotclear()->behavior()->add('adminDashboardOptionsForm', [__CLASS__, 'behaviorAdminDashboardOptionsForm']);
        dotclear()->behavior()->add('adminAfterDashboardOptionsUpdate', [__CLASS__, 'behaviorAdminAfterDashboardOptionsUpdate']);
        dotclear()->behavior()->add('adminPageHelpBlock', [__CLASS__, 'behaviorAdminPageHelpBlock']);
    }

    /**
     * Register default tasks.
     *
     * @param   Maintenance     $maintenance    Maintenance instance
     */
    public static function behaviorDcMaintenanceInit(Maintenance $maintenance): void
    {
        $ns = 'Dotclear\\Plugin\\Maintenance\\Lib\\Task\\';

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
     * Favorites
     *
     * @param   Favorites   $favs   Favorites instance
     */
    public static function behaviorAdminDashboardFavorites(Favorites $favs): void
    {
        $favs->register('maintenance', [
            'title'        => __('Maintenance'),
            'url'          => dotclear()->adminurl->get('admin.plugin.Maintenance'),
            'small-icon'   => ['?mf=Plugin/Maintenance/icon.svg', '?mf=Plugin/Maintenance/icon-dark.svg'],
            'large-icon'   => ['?mf=Plugin/Maintenance/icon.svg', '?mf=Plugin/Maintenance/icon-dark.svg'],
            'permissions'  => 'admin',
            'active_cb'    => [__CLASS__, 'behaviorAdminDashboardFavoritesActive'],
            'dashboard_cb' => [__CLASS__, 'behaviorAdminDashboardFavoritesCallback']
        ]);
    }

    /**
     * Is maintenance plugin active
     *
     * @return  bool    True if maintenance plugin is active else false
     */
    public static function behaviorAdminDashboardFavoritesActive(): bool
    {
        return dotclear()->adminurl->called() == 'admin.plugin.Maintenance';
    }

    /**
     * Favorites hack.
     *
     * This updates maintenance fav icon text
     * if there are tasks required maintenance.
     *
     * @param   ArrayObject     $fav    The fav
     */
    public static function behaviorAdminDashboardFavoritesCallback(ArrayObject $fav): void
    {
        # Check user option
        if (!dotclear()->auth()->user_prefs->maintenance->dashboard_icon) {
            return;
        }

        # Check expired tasks
        $maintenance = new Maintenance();
        $count       = 0;
        foreach ($maintenance->getTasks() as $t) {
            if ($t->expired() !== false) {
                $count++;
            }
        }

        if (!$count) {
            return;
        }

        $fav['title'] .= '<br />' . sprintf(__('One task to execute', '%s tasks to execute', $count), $count);
        $fav['large-icon'] = ['?mf=Plugin/Maintenance/icon-update.svg', '?mf=Plugin/Maintenance/icon-dark-update.svg'];
    }

    /**
     * Dashboard items stack.
     *
     * @param   ArrayObject     $items  The items
     */
    public static function behaviorAdminDashboardItems(ArrayObject $items): void
    {
        if (!dotclear()->auth()->user_prefs->maintenance->dashboard_item) {
            return;
        }

        $maintenance = new Maintenance();

        $lines = [];
        foreach ($maintenance->getTasks() as $t) {
            $ts = $t->expired();
            if ($ts === false) {
                continue;
            }

            $lines[] = '<li title="' . ($ts === null ?
                __('This task has never been executed.')
                :
                sprintf(__('Last execution of this task was on %s.'),
                    dt::dt2str(dotclear()->blog()->settings->system->date_format, (string) $ts) . ' ' .
                    dt::dt2str(dotclear()->blog()->settings->system->time_format, (string) $ts)
                )
            ) . '">' . $t->task() . '</li>';
        }

        if (empty($lines)) {
            return;
        }

        $items[] = new ArrayObject([
            '<div id="maintenance-expired" class="box small">' .
            '<h3>' . dotclear()->menu->getIconTheme(['?mf=Plugin/Maintenance/icon.svg', '?mf=Plugin/Maintenance/icon-dark.svg'], true, '', '', 'icon-small') . __('Maintenance') . '</h3>' .
            '<p class="warning no-margin">' . sprintf(__('There is a task to execute.', 'There are %s tasks to execute.', count($lines)), count($lines)) . '</p>' .
            '<ul>' . implode('', $lines) . '</ul>' .
            '<p><a href="' . dotclear()->adminurl->get('admin.plugin.Maintenance') . '">' . __('Manage tasks') . '</a></p>' .
            '</div>'
        ]);
    }

    /**
     * User preferences form.
     *
     * This add options for superadmin user
     * to show or not expired taks.
     */
    public static function behaviorAdminDashboardOptionsForm(): void
    {
        echo
        '<div class="fieldset">' .
        '<h4>' . __('Maintenance') . '</h4>' .

        '<p><label for="maintenance_dashboard_icon" class="classic">' .
        Form::checkbox('maintenance_dashboard_icon', 1, dotclear()->auth()->user_prefs->maintenance->dashboard_icon) .
        __('Display overdue tasks counter on maintenance dashboard icon') . '</label></p>' .

        '<p><label for="maintenance_dashboard_item" class="classic">' .
        Form::checkbox('maintenance_dashboard_item', 1, dotclear()->auth()->user_prefs->maintenance->dashboard_item) .
        __('Display overdue tasks list on dashboard items') . '</label></p>' .

            '</div>';
    }

    /**
     * User preferences update.
     *
     * @param   string  $user_id    The user identifier
     */
    public static function behaviorAdminAfterDashboardOptionsUpdate(string $user_id = null): void
    {
        if (is_null($user_id)) {
            return;
        }

        dotclear()->auth()->user_prefs->maintenance->put('dashboard_icon', !empty($_POST['maintenance_dashboard_icon']), 'boolean');
        dotclear()->auth()->user_prefs->maintenance->put('dashboard_item', !empty($_POST['maintenance_dashboard_item']), 'boolean');
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
     * @param   ArrayObject     $blocks     The blocks
     */
    public static function behaviorAdminPageHelpBlock(ArrayObject $blocks)
    {
        $found = false;
        foreach ($blocks as $block) {
            if ($block == 'maintenancetasks') {
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
                    if (($desc = $t->description()) != '') {
                        $res_task .= '<dt>' . $t->task() . '</dt>' .
                            '<dd>' . $desc . '</dd>';
                    }
                }
                if (!empty($res_task)) {
                    $desc = $group_obj->description ?: $group_obj->summary;

                    $res_group .= '<h5>' . $group_obj->name() . '</h5>' .
                        ($desc ? '<p>' . $desc . '</p>' : '') .
                        '<dl>' . $res_task . '</dl>';
                }
            }
            if (!empty($res_group)) {
                $desc = $tab_obj->description ?: $tab_obj->summary;

                $res_tab .= '<h4>' . $tab_obj->name() . '</h4>' .
                    ($desc ? '<p>' . $desc . '</p>' : '') .
                    $res_group;
            }
        }
        if (!empty($res_tab)) {
            $res          = new ArrayObject();
            $res->content = $res_tab;   // @phpstan-ignore-line
            $blocks[]     = $res;
        }
    }

    //! move this to page
    public static function behaviorPluginsToolsHeaders($module): string
    {
        return $module == 'Maintenance' ? 'mf=Plugin/Maintenance/js/settings.js' : '';
    }
}
