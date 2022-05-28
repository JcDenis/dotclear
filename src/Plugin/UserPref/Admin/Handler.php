<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\UserPref\Admin;

// Dotclear\Plugin\UserPref\Admin\Handler
use Dotclear\App;
use Dotclear\Helper\GPC\GPC;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Process\Admin\Page\AbstractPage;
use Exception;

/**
 * UserPref Admin page.
 *
 * @ingroup  Plugin UserPref
 */
class Handler extends AbstractPage
{
    protected function getPermissions(): string|bool
    {
        return '';
    }

    protected function getPagePrepend(): ?bool
    {
        // Local navigation
        if (!GPC::post()->empty('gp_nav')) {
            App::core()->adminurl()->redirect('admin.plugin.UserPref', [], GPC::post()->string('gp_nav'));

            exit;
        }
        if (!GPC::post()->empty('lp_nav')) {
            App::core()->adminurl()->redirect('admin.plugin.UserPref', [], GPC::post()->string('lp_nav'));

            exit;
        }

        // Local prefs update
        if (!GPC::post()->empty('s')) {
            try {
                foreach (GPC::post()->array('s') as $ws => $s) {
                    foreach ($s as $k => $v) {
                        if ('array' == GPC::post()->array('s_type')[$ws][$k]) {
                            $v = json_decode($v, true);
                        }
                        App::core()->user()->preference()->get($ws)->put($k, $v);
                    }
                }

                App::core()->notice()->addSuccessNotice(__('Preferences successfully updated'));
                App::core()->adminurl()->redirect('admin.plugin.UserPref');
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
            }
        }

        // Global prefs update
        if (!GPC::post()->empty('gs')) {
            try {
                foreach (GPC::post()->array('gs') as $ws => $s) {
                    foreach ($s as $k => $v) {
                        if ('array' == GPC::post()->array('gs_type')[$ws][$k]) {
                            $v = json_decode($v, true);
                        }
                        App::core()->user()->preference()->get($ws)->put($k, $v, null, null, true, true);
                    }
                }

                App::core()->notice()->addSuccessNotice(__('Preferences successfully updated'));
                App::core()->adminurl()->redirect('admin.plugin.UserPref', ['part' => 'global']);
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
            }
        }

        // Page setup
        $this
            ->setPageTitle(__('user:preferences'))
            ->setPageHelp('UserPref')
            ->setPageHead(
                App::core()->resource()->pageTabs('global' == GPC::get()->string('part') ? 'global' : 'local') .
                App::core()->resource()->load('index.js', 'Plugin', 'UserPref')
            )
            ->setPageBreadcrumb([
                __('System')                                    => '',
                Html::escapeHTML(App::core()->user()->userID()) => '',
                __('user:preferences')                          => '',
            ])
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        echo '<div id="local" class="multi-part" title="' . __('User preferences') . '">' .
        '<h3 class="out-of-screen-if-js">' . __('User preferences') . '</h3>';

        $prefs = [];
        foreach (App::core()->user()->preference()->dump() as $ws => $workspace) {
            foreach ($workspace->dumpPrefs() as $k => $v) {
                $prefs[$ws][$k] = $v;
            }
        }
        ksort($prefs);

        if (0 < count($prefs)) {
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
        foreach (App::core()->user()->preference()->dump() as $ws => $workspace) {
            foreach ($workspace->dumpGlobalPrefs() as $k => $v) {
                $prefs[$ws][$k] = $v;
            }
        }
        ksort($prefs);

        if (0 < count($prefs)) {
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
        echo '<form action="' . App::core()->adminurl()->root() . '" method="post" class="anchor-nav-sticky">' .
        '<p class="anchor-nav">' .
        '<label for="' . ($global ? 'g' : 'l') . 'p_nav" class="classic">' . __('Goto:') . '</label> ' .
        Form::combo(($global ? 'g' : 'l') . 'p_nav', $combo, ['class' => 'navigation']) .
        ' <input type="submit" value="' . __('Ok') . '" id="' . ($global ? 'g' : 'l') . 'p_submit" />' .
        App::core()->adminurl()->getHiddenFormFields('admin.plugin.UserPref', [], true) . '</p></form>';
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

        echo '<form action="' . App::core()->adminurl()->root() . '" method="post">';

        foreach ($prefs as $ws => $s) {
            ksort($s);
            echo sprintf($table_header, ($global ? 'g_' : 'l_') . $ws, $ws);
            foreach ($s as $k => $v) {
                echo $this->prefLine($k, $v, $ws, ($global ? 'gs' : 's'), ($global ? false : !$v['global']));
            }
            echo $table_footer;
        }

        echo '<p><input type="submit" value="' . __('Save') . '" />' .
        '<input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />' .
        App::core()->adminurl()->getHiddenFormFields('admin.plugin.UserPref', [], true) . '</p>' .
        '</form>';
    }

    private function prefLine(string $id, array $s, string $ws, string $field_name, bool $strong_label): string
    {
        $field = match ($s['type']) {
            'boolean' => Form::combo(
                [$field_name . '[' . $ws . '][' . $id . ']', $field_name . '_' . $ws . '_' . $id],
                [__('yes') => 1, __('no') => 0],
                $s['value'] ? 1 : 0
            ),
            'array' => Form::field(
                [$field_name . '[' . $ws . '][' . $id . ']', $field_name . '_' . $ws . '_' . $id],
                40,
                null,
                Html::escapeHTML(json_encode($s['value']))
            ),
            'integer' => Form::number(
                [$field_name . '[' . $ws . '][' . $id . ']', $field_name . '_' . $ws . '_' . $id],
                null,
                null,
                Html::escapeHTML((string) $s['value'])
            ),
            default => Form::field(
                [$field_name . '[' . $ws . '][' . $id . ']', $field_name . '_' . $ws . '_' . $id],
                40,
                null,
                Html::escapeHTML($s['value'])
            ),
        };
        $type = Form::hidden(
            [$field_name . '_type' . '[' . $ws . '][' . $id . ']', $field_name . '_' . $ws . '_' . $id . '_type'],
            Html::escapeHTML($s['type'])
        );

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
