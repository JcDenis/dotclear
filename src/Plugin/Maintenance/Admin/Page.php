<?php
/**
 * @class Dotclear\Plugin\Maintenance\Admin\Page
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


use Dotclear\Module\AbstractPage;

use Dotclear\Plugin\Maintenance\Lib\Maintenance;

use Dotclear\Html\Form;
use Dotclear\Html\Html;
use Dotclear\Utils\Dt;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Page extends AbstractPage
{
    protected $namespaces = ['maintenance'];

    private $m_maintenance;
    private $m_tasks = null;
    private $m_task  = null;
    private $m_code  = null;
    private $m_tab   = '';

    protected function getPermissions(): string|null|false
    {
        return 'admin';
    }

    protected function getPagePrepend(): ?bool
    {
        $this->m_maintenance = new Maintenance();
        $this->m_tasks       = $this->m_maintenance->getTasks();
        $this->m_code        = empty($_POST['code']) ? null : (integer) $_POST['code'];
        $this->m_tab         = empty($_REQUEST['tab']) ? '' : $_REQUEST['tab'];

        # Get task object
        if (!empty($_REQUEST['task'])) {
            $this->m_task = $this->m_maintenance->getTask($_REQUEST['task']);

            if ($this->m_task === null) {
                dotclear()->error()->add('Unknown task ID');
            }

            $this->m_task->code($this->m_code);
        }

        # Execute task
        if ($this->m_task && !empty($_POST['task']) && $this->m_task->id() == $_POST['task']) {
            try {
                $this->m_code = $this->m_task->execute();
                if (false === $this->m_code) {
                    throw new \Exception($this->m_task->error());
                }
                if (true === $this->m_code) {
                    $this->m_maintenance->setLog($this->m_task->id());

                    dotclear()->notices->addSuccessNotice($this->m_task->success());
                    dotclear()->adminurl->redirect('admin.plugin.Maintenance', ['task' => $this->m_task->id(), 'tab' => $this->m_tab], '#' . $this->m_tab);
                }
            } catch (\Exception $e) {
                dotclear()->error()->add($e->getMessage());
            }
        }

        # Save settings
        if (!empty($_POST['save_settings'])) {
            try {
                dotclear()->blog->settings->maintenance->put(
                    'plugin_message',
                    !empty($_POST['settings_plugin_message']),
                    'boolean',
                    'Display alert message of late tasks on plugin page',
                    true,
                    true
                );

                foreach ($this->m_tasks as $t) {
                    if (!$t->id()) {
                        continue;
                    }

                    if (!empty($_POST['settings_recall_type']) && $_POST['settings_recall_type'] == 'all') {
                        $ts = $_POST['settings_recall_time'];
                    } else {
                        $ts = empty($_POST['settings_ts_' . $t->id()]) ? 0 : $_POST['settings_ts_' . $t->id()];
                    }
                    dotclear()->blog->settings->maintenance->put(
                        'ts_' . $t->id(),
                        abs((integer) $ts),
                        'integer',
                        sprintf('Recall time for task %s', $t->id()),
                        true,
                        $t->blog()
                    );
                }

                dotclear()->notices->addSuccessNotice(__('Maintenance plugin has been successfully configured.'));
                dotclear()->adminurl->redirect('admin.plugin.Maintenance', ['tab' => $this->m_tab], '#' . $this->m_tab);
            } catch (\Exception $e) {
                dotclear()->error()->add($e->getMessage());
            }
        }

        # Save system settings
        if (!empty($_POST['save_system'])) {
            try {
                // Default (global) settings
                dotclear()->blog->settings->system->put('csp_admin_on', !empty($_POST['system_csp_global']), null, null, true, true);
                dotclear()->blog->settings->system->put('csp_admin_report_only', !empty($_POST['system_csp_global_report_only']), null, null, true, true);
                // Current blog settings
                dotclear()->blog->settings->system->put('csp_admin_on', !empty($_POST['system_csp']));
                dotclear()->blog->settings->system->put('csp_admin_report_only', !empty($_POST['system_csp_report_only']));

                dotclear()->notices->addSuccessNotice(__('System settings have been saved.'));

                if (!empty($_POST['system_csp_reset'])) {
                    dotclear()->blog->settings->system->dropEvery('csp_admin_on');
                    dotclear()->blog->settings->system->dropEvery('csp_admin_report_only');
                    dotclear()->notices->addSuccessNotice(__('All blog\'s Content-Security-Policy settings have been reset to default.'));
                }

                dotclear()->adminurl->redirect('admin.plugin.Maintenance', ['tab' => $this->m_tab], '#' . $this->m_tab);
            } catch (\Exception $e) {
                dotclear()->error()->add($e->getMessage());
            }
        }

        # Page setup
        $this
            ->setPageTitle(__('Maintenance'))
            ->setPageHelp('maintenance', 'maintenancetasks')
            ->setPageBreadcrumb(
                [
                    __('Plugins')     => '',
                    __('Maintenance') => ''
                ]
            )
            ->setPageHead(
                static::jsPageTabs($this->m_tab) .
                static::jsLoad('?mf=Plugin/Maintenance/files/js/settings.js')
            )
        ;

        if ($this->m_task && $this->m_task->ajax()) {
            $this->setPageHead(
                static::jsJson('maintenance', ['wait' => __('Please wait...')]) .
                static::jsLoad('?mf=Plugin/Maaintenance/js/dc.maintenance.js')
            );
        }

        $this->setPageHead($this->m_maintenance->getHeaders());


        if ($this->m_task && ($res = $this->m_task->step()) !== null) {
            $this->setPageBreadcrumb(
                [
                    __('Plugins')                                                                                           => '',
                    '<a href="' . dotclear()->adminurl->get('admin.plugin.Maintenance') . '">' . __('Maintenance') . '</a>' => '',
                    Html::escapeHTML($this->m_task->name())                                                                 => ''
                ]
            );
        }

        return true;
    }

    protected function getPageContent(): void
    {
        # Combos
        $combo_ts = [
            __('Never')            => 0,
            __('Every week')       => 604800,
            __('Every two weeks')  => 1209600,
            __('Every month')      => 2592000,
            __('Every two months') => 5184000
        ];

        # Check if there is something to display according to user permissions
        if (empty($this->m_tasks)) {
            echo '<p class="warn">' . __('You have not sufficient permissions to view this page.') . '</p>';

            return;
        }

        if ($this->m_task && ($res = $this->m_task->step()) !== null) {

            # content
            if (substr($res, 0, 1) != '<') {
                $res = sprintf('<p class="step-msg">%s</p>', $res);
            }

            # Intermediate task (task required several steps)
            echo
            '<div class="step-box" id="' . $this->m_task->id() . '">' .
            '<p class="step-back">' .
            '<a class="back" href="' . dotclear()->adminurl->get('admin.plugin.Maintenance', ['tab' => $this->m_task->tab()]) . '#' . $this->m_task->tab() . '">' . __('Back') . '</a>' .
            '</p>' .
            '<h3>' . Html::escapeHTML($this->m_task->name()) . '</h3>' .
            '<form action="' . dotclear()->adminurl->get('admin.plugin.Maintenance') . '" method="post">' .
            $res .
            '<p class="step-submit">' .
            '<input type="submit" value="' . $this->m_task->task() . '" /> ' .
            Form::hidden(['task'], $this->m_task->id()) .
            Form::hidden(['code'], (int) $this->m_code) .
            Form::hidden(['handler'], 'admin.plugin.Maintenance') .
            dotclear()->formNonce() .
                '</p>' .
                '</form>' .
                '</div>';
        } else {
            # Simple task (with only a button to start it)
            foreach ($this->m_maintenance->getTabs() as $this->m_tab_obj) {
                $res_group = '';
                foreach ($this->m_maintenance->getGroups() as $group_obj) {
                    $res_task = '';
                    foreach ($this->m_tasks as $t) {
                        if (!$t->id()
                            || $t->group() != $group_obj->id()
                            || $t->tab() != $this->m_tab_obj->id()) {
                            continue;
                        }

                        $res_task .= '<p>' . Form::radio(['task', $t->id()], $t->id()) . ' ' .
                        '<label class="classic" for="' . $t->id() . '">' .
                        Html::escapeHTML($t->task()) . '</label>';

                        // Expired task alert message
                        $ts = $t->expired();
                        if (dotclear()->blog->settings->maintenance->plugin_message && $ts !== false) {
                            if ($ts === null) {
                                $res_task .= '<br /> <span class="warn">' .
                                __('This task has never been executed.') . ' ' .
                                __('You should execute it now.') . '</span>';
                            } else {
                                $res_task .= '<br /> <span class="warn">' . sprintf(
                                    __('Last execution of this task was on %s.'),
                                    Dt::str(dotclear()->blog->settings->system->date_format, $ts) . ' ' .
                                    Dt::str(dotclear()->blog->settings->system->time_format, $ts)
                                ) . ' ' .
                                __('You should execute it now.') . '</span>';
                            }
                        }

                        $res_task .= '</p>';
                    }

                    if (!empty($res_task)) {
                        $res_group .= '<div class="fieldset">' .
                        '<h4 id="' . $group_obj->id() . '">' . $group_obj->name() . '</h4>' .
                            $res_task .
                            '</div>';
                    }
                }

                if (!empty($res_group)) {
                    echo
                    '<div id="' . $this->m_tab_obj->id() . '" class="multi-part" title="' . $this->m_tab_obj->name() . '">' .
                    '<h3>' . $this->m_tab_obj->name() . '</h3>' .
                    // ($this->m_tab_obj->option('summary') ? '<p>'.$this->m_tab_obj->option('summary').'</p>' : '').
                    '<form action="' . dotclear()->adminurl->get('admin.plugin.Maintenance') . '" method="post">' .
                    $res_group .
                    '<p><input type="submit" value="' . __('Execute task') . '" /> ' .
                    ' <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />' .
                    Form::hidden(['tab'], $this->m_tab_obj->id()) .
                    Form::hidden(['handler'], 'admin.plugin.Maintenance') .
                    dotclear()->formNonce() . '</p>' .
                    '<p class="form-note info">' . __('This may take a very long time.') . '</p>' .
                        '</form>' .
                        '</div>';
                }
            }

            # Advanced tasks (that required a tab)
            foreach ($this->m_tasks as $t) {
                if (!$t->id() || $t->group() !== null) {
                    continue;
                }

                echo
                '<div id="' . $t->id() . '" class="multi-part" title="' . $t->name() . '">' .
                '<h3>' . $t->name() . '</h3>' .
                '<form action="' . dotclear()->adminurl->get('admin.plugin.Maintenance') . '" method="post">' .
                $t->content() .
                '<p><input type="submit" value="' . __('Execute task') . '" /> ' .
                ' <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />' .
                Form::hidden(['task'], $t->id()) .
                Form::hidden(['tab'], $t->id()) .
                Form::hidden(['handler'], 'admin.plugin.Maintenance') .
                dotclear()->formNonce() . '</p>' .
                    '</form>' .
                    '</div>';
            }

            # Settings
            echo
            '<div id="settings" class="multi-part" title="' . __('Alert settings') . '">' .
            '<h3>' . __('Alert settings') . '</h3>' .
            '<form action="' . dotclear()->adminurl->get('admin.plugin.Maintenance') . '" method="post">' .

            '<h4 class="pretty-title">' . __('Activation') . '</h4>' .
            '<p><label for="settings_plugin_message" class="classic">' .
            Form::checkbox('settings_plugin_message', 1, dotclear()->blog->settings->maintenance->plugin_message) .
            __('Display alert messages on late tasks') . '</label></p>' .

            '<p class="info">' . sprintf(
                __('You can place list of late tasks on your %s.'),
                '<a href="' . dotclear()->adminurl->get('admin.user.pref') . '#user-favorites">' . __('Dashboard') . '</a>'
            ) . '</p>' .

            '<h4 class="pretty-title vertical-separator">' . __('Frequency') . '</h4>' .

            '<p class="vertical-separator">' . Form::radio(['settings_recall_type', 'settings_recall_all'], 'all') . ' ' .
            '<label class="classic" for="settings_recall_all">' .
            '<strong>' . __('Use one recall time for all tasks') . '</strong></label></p>' .

            '<p class="field wide vertical-separator"><label for="settings_recall_time">' . __('Recall time for all tasks:') . '</label>' .
            Form::combo('settings_recall_time', $combo_ts, 'seperate', 'recall-for-all') .
            '</p>' .

            '<p class="vertical-separator">' . Form::radio(['settings_recall_type', 'settings_recall_separate'], 'separate', 1) . ' ' .
            '<label class="classic" for="settings_recall_separate">' .
            '<strong>' . __('Use one recall time per task') . '</strong></label></p>';

            foreach ($this->m_tasks as $t) {
                if (!$t->id()) {
                    continue;
                }
                echo
                '<div class="two-boxes">' .

                '<p class="field wide"><label for="settings_ts_' . $t->id() . '">' . $t->task() . '</label>' .
                Form::combo('settings_ts_' . $t->id(), $combo_ts, $t->ts(), 'recall-per-task') .
                    '</p>' .

                    '</div>';
            }

            echo
            '<p class="field wide"><input type="submit" value="' . __('Save') . '" /> ' .
            ' <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />' .
            Form::hidden(['tab'], 'settings') .
            Form::hidden(['save_settings'], 1) .
            Form::hidden(['handler'], 'admin.plugin.Maintenance') .
            dotclear()->formNonce() . '</p>' .
                '</form>' .
                '</div>';

            # System tab
            if (dotclear()->auth->isSuperAdmin()) {
                echo
                '<div id="system" class="multi-part" title="' . __('System') . '">' .
                '<h3>' . __('System settings') . '</h3>' .
                    '<form action="' . dotclear()->adminurl->get('admin.plugin.Maintenance') . '" method="post">';

                echo
                '<div class="fieldset two-cols clearfix">' .
                '<h4 class="pretty-title">' . __('Content-Security-Policy') . '</h4>' .

                '<div class="col">' .
                '<p><label for="system_csp" class="classic">' .
                Form::checkbox('system_csp', '1', dotclear()->blog->settings->system->csp_admin_on) .
                __('Enable Content-Security-Policy system') . '</label></p>' .
                '<p><label for="system_csp_report_only" class="classic">' .
                Form::checkbox('system_csp_report_only', '1', dotclear()->blog->settings->system->csp_admin_report_only) .
                __('Enable Content-Security-Policy report only') . '</label></p>' .
                '</div>' .

                '<div class="col">' .
                '<p><label for="system_csp_global" class="classic">' .
                Form::checkbox('system_csp_global', '1', dotclear()->blog->settings->system->getGlobal('csp_admin_on')) .
                __('Enable Content-Security-Policy system by default') . '</label></p>' .
                '<p><label for="system_csp_global_report_only" class="classic">' .
                Form::checkbox('system_csp_global_report_only', '1', dotclear()->blog->settings->system->getGlobal('csp_admin_report_only')) .
                __('Enable Content-Security-Policy report only by default') . '</label></p>' .
                '<p><label for="system_csp_reset" class="classic">' .
                Form::checkbox('system_csp_reset', '1', 0) .
                __('Also apply these settings to all blogs') . '</label></p>' .
                '</div>' .
                '</div>';

                echo
                '<p class="field wide"><input type="submit" value="' . __('Save') . '" /> ' .
                ' <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />' .
                Form::hidden(['tab'], 'system') .
                Form::hidden(['save_system'], 1) .
                Form::hidden(['handler'], 'admin.plugin.Maintenance') .
                dotclear()->formNonce() . '</p>' .
                    '</form>' .
                    '</div>';
            }
        }
    }
}
