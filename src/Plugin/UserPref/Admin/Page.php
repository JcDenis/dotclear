<?php
/**
 * @class Dotclear\Plugin\UserPref\Admin\Page
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginUserPref
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\UserPref\Admin;

use Dotclear\Exception;

use Dotclear\Module\AbstractPage;

use Dotclear\Html\Form;
use Dotclear\Html\Html;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Page extends AbstractPage
{
    protected function getPermissions(): string|null|false
    {
        # Super admin
        return null;
    }

    protected function getPagePrepend(): ?bool
    {
        # Local navigation
        if (!empty($_POST['gp_nav'])) {
            dcCore()->adminurl->redirect('admin.plugin.UserPref', [], $_POST['gp_nav']);
            exit;
        }
        if (!empty($_POST['lp_nav'])) {
            dcCore()->adminurl->redirect('admin.plugin.UserPref', [], $_POST['lp_nav']);
            exit;
        }

        # Local prefs update
        if (!empty($_POST['s']) && is_array($_POST['s'])) {
            try {
                foreach ($_POST['s'] as $ws => $s) {
                    dcCore()->auth->user_prefs->addWorkspace($ws);
                    foreach ($s as $k => $v) {
                        if ($_POST['s_type'][$ws][$k] == 'array') {
                            $v = json_decode($v, true);
                        }
                        dcCore()->auth->user_prefs->$ws->put($k, $v);
                    }
                }

                dcCore()->notices->addSuccessNotice(__('Preferences successfully updated'));
                dcCore()->adminurl->redirect('admin.plugin.UserPref');
            } catch (Exception $e) {
                dcCore()->error($e->getMessage());
            }
        }

        # Global prefs update
        if (!empty($_POST['gs']) && is_array($_POST['gs'])) {
            try {
                foreach ($_POST['gs'] as $ws => $s) {
                    dcCore()->auth->user_prefs->addWorkspace($ws);
                    foreach ($s as $k => $v) {
                        if ($_POST['gs_type'][$ws][$k] == 'array') {
                            $v = json_decode($v, true);
                        }
                        dcCore()->auth->user_prefs->$ws->put($k, $v, null, null, true, true);
                    }
                }

                dcCore()->notices->addSuccessNotice(__('Preferences successfully updated'));
                dcCore()->adminurl->redirect('admin.plugin.UserPref', ['part' => 'global']);
            } catch (Exception $e) {
                dcCore()->error($e->getMessage());
            }
        }

        # Page setup
        $this
            ->setPageTitle(__('user:preferences'))
            ->setPageHelp('UserPref')
            ->setPageHead(
                static::jsPageTabs(!empty($_GET['part']) && $_GET['part'] == 'global' ? 'global' : 'local') .
                static::jsLoad('?mf=Plugin/UserPref/js/index.js')
            )
            ->setPageBreadcrumb([
                __('System')                                  => '',
                Html::escapeHTML(dcCore()->auth->userID()) => '',
                __('user:preferences')                        => ''
            ])
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        echo
        '<div id="local" class="multi-part" title="' . __('User preferences') . '">' .
        '<h3 class="out-of-screen-if-js">' . __('User preferences') . '</h3>';

        $prefs = [];
        foreach (dcCore()->auth->user_prefs->dumpWorkspaces() as $ws => $workspace) {
            foreach ($workspace->dumpPrefs() as $k => $v) {
                $prefs[$ws][$k] = $v;
            }
        }
        ksort($prefs);

        if (count($prefs) > 0) {
            $ws_combo = [];
            foreach ($prefs as $ws => $s) {
                $ws_combo[$ws] = '#l_' . $ws;
            }
            $this->prefMenu($ws_combo, false);
        }

        $this->prefTable($prefs, false);

        echo '</div>' .

        '<div id="global" class="multi-part" title="' . __('Global preferences') . '">' .
        '<h3 class="out-of-screen-if-js">' . __('Global preferences') . '</h3>';

        $prefs = [];
        foreach (dcCore()->auth->user_prefs->dumpWorkspaces() as $ws => $workspace) {
            foreach ($workspace->dumpGlobalPrefs() as $k => $v) {
                $prefs[$ws][$k] = $v;
            }
        }
        ksort($prefs);

        if (count($prefs) > 0) {
            $ws_combo = [];
            foreach ($prefs as $ws => $s) {
                $ws_combo[$ws] = '#g_' . $ws;
            }
            $this->prefMenu($ws_combo, true);
        }

        $this->prefTable($prefs, true);

        echo '</div>';
    }

    private function prefMenu(array $combo, bool $global): void
    {
        echo
        '<form action="' . dcCore()->adminurl->get('admin.plugin.UserPref') . '" method="post">' .
        '<p class="anchor-nav">' .
        '<label for="' . ($global ? 'g' : 'l') .'p_nav" class="classic">' . __('Goto:') . '</label> ' .
        Form::combo(($global ? 'g' : 'l') .'p_nav', $combo, ['class' => 'navigation']) .
        ' <input type="submit" value="' . __('Ok') . '" id="' . ($global ? 'g' : 'l') .'p_submit" />' .
        dcCore()->formNonce() . '</p></form>';
    }

    private function prefTable(array $prefs, bool $global): void
    {
        $table_header = '<div class="table-outer"><table class="prefs" id="%s"><caption class="as_h3">%s</caption>' .
        '<thead>' .
        '<tr>' . "\n" .
        '  <th class="nowrap">' . __('Setting ID') . '</th>' . "\n" .
        '  <th>' . __('Value') . '</th>' . "\n" .
        '  <th>' . __('Type') . '</th>' . "\n" .
        '  <th>' . __('Description') . '</th>' . "\n" .
            '</tr>' . "\n" .
            '</thead>' . "\n" .
            '<tbody>';
        $table_footer = '</tbody></table></div>';

        echo '<form action="' . dcCore()->adminurl->get('admin.plugin.UserPref') . '" method="post">';

        foreach ($prefs as $ws => $s) {
            ksort($s);
            echo sprintf($table_header, ($global ? 'g_' : 'l_') . $ws, $ws);
            foreach ($s as $k => $v) {
                echo self::prefLine($k, $v, $ws, ($global ? 'gs' : 's'), ($global ? false : !$v['global']));
            }
            echo $table_footer;
        }

        echo
        '<p><input type="submit" value="' . __('Save') . '" />' .
        '<input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />' .
        dcCore()->formNonce() . '</p>' .
        '</form>';
    }

    private static function prefLine($id, $s, $ws, $field_name, $strong_label)
    {
        if ($s['type'] == 'boolean') {
            $field = Form::combo(
                [$field_name . '[' . $ws . '][' . $id . ']', $field_name . '_' . $ws . '_' . $id],
                [__('yes') => 1, __('no') => 0],
                $s['value'] ? 1 : 0);
        } else {
            if ($s['type'] == 'array') {
                $field = Form::field([$field_name . '[' . $ws . '][' . $id . ']', $field_name . '_' . $ws . '_' . $id], 40, null,
                    Html::escapeHTML(json_encode($s['value'])));
            } else {
                $field = Form::field([$field_name . '[' . $ws . '][' . $id . ']', $field_name . '_' . $ws . '_' . $id], 40, null,
                    Html::escapeHTML((string) $s['value']));
            }
        }
        $type = Form::hidden([$field_name . '_type' . '[' . $ws . '][' . $id . ']', $field_name . '_' . $ws . '_' . $id . '_type'],
            Html::escapeHTML($s['type']));

        $slabel = $strong_label ? '<strong>%s</strong>' : '%s';

        return
        '<tr class="line">' .
        '<td scope="row"><label for="' . $field_name . '_' . $ws . '_' . $id . '">' . sprintf($slabel, Html::escapeHTML($id)) . '</label></td>' .
        '<td>' . $field . '</td>' .
        '<td>' . $s['type'] . $type . '</td>' .
        '<td>' . Html::escapeHTML($s['label']) . '</td>' .
            '</tr>';

    }
}
