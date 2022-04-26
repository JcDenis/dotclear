<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\AboutConfig\Admin;

// Dotclear\Plugin\AboutConfig\Admin\Handler
use Dotclear\App;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Module\AbstractPage;
use Exception;

/**
 * Admin plugin page.
 *
 * @ingroup  Plugin AboutConfig
 */
class Handler extends AbstractPage
{
    protected function getPermissions(): string|null|false
    {
        // Super admin
        return null;
    }

    protected function getPagePrepend(): ?bool
    {
        // Local navigation
        if (!empty($_POST['gs_nav'])) {
            App::core()->adminurl()->redirect('admin.plugin.AboutConfig', [], $_POST['gs_nav']);

            exit;
        }
        if (!empty($_POST['ls_nav'])) {
            App::core()->adminurl()->redirect('admin.plugin.AboutConfig', [], $_POST['ls_nav']);

            exit;
        }

        // Local settings update
        if (!empty($_POST['s']) && is_array($_POST['s'])) {
            try {
                foreach ($_POST['s'] as $ns => $s) {
                    foreach ($s as $k => $v) {
                        if ('array' == $_POST['s_type'][$ns][$k]) {
                            $v = json_decode($v, true);
                        }
                        App::core()->blog()->settings()->get($ns)->put($k, $v);
                    }
                    App::core()->blog()->triggerBlog();
                }

                App::core()->notice()->addSuccessNotice(__('Configuration successfully updated'));
                App::core()->adminurl()->redirect('admin.plugin.AboutConfig');
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
            }
        }

        // Global settings update
        if (!empty($_POST['gs']) && is_array($_POST['gs'])) {
            try {
                foreach ($_POST['gs'] as $ns => $s) {
                    foreach ($s as $k => $v) {
                        if ('array' == $_POST['gs_type'][$ns][$k]) {
                            $v = json_decode($v, true);
                        }
                        App::core()->blog()->settings()->get($ns)->put($k, $v, null, null, true, true);
                    }
                    App::core()->blog()->triggerBlog();
                }

                App::core()->notice()->addSuccessNotice(__('Configuration successfully updated'));
                App::core()->adminurl()->redirect('admin.plugin.AboutConfig', ['part' => 'global']);
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
            }
        }

        // Page setup
        $this
            ->setPageTitle(__('about:config'))
            ->setPageHelp('aboutConfig')
            ->setPageHead(
                App::core()->resource()->pageTabs(!empty($_GET['part']) && 'global' == $_GET['part'] ? 'global' : 'local') .
                App::core()->resource()->load('index.js', 'Plugin', 'AboutConfig')
            )
            ->setPageBreadcrumb([
                __('System')                                => '',
                Html::escapeHTML(App::core()->blog()->name) => '',
                __('about:config')                          => '',
            ])
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        echo '<div id="local" class="multi-part" title="' . sprintf(__('Settings for %s'), Html::escapeHTML(App::core()->blog()->name)) . '">' .
        '<h3 class="out-of-screen-if-js">' . sprintf(__('Settings for %s'), Html::escapeHTML(App::core()->blog()->name)) . '</h3>';

        $settings = [];
        foreach (App::core()->blog()->settings()->dump() as $ns => $namespace) {
            foreach ($namespace->dumpSettings() as $k => $v) {
                $settings[$ns][$k] = $v;
            }
        }
        ksort($settings);

        if (count($settings) > 0) {
            $ns_combo = [];
            foreach ($settings as $ns => $s) {
                $ns_combo[$ns] = '#l_' . $ns;
            }
            $this->settingMenu($ns_combo, false);
        }

        $this->settingTable($settings, false);

        echo '</div>' .

        '<div id="global" class="multi-part" title="' . __('Global settings') . '">' .
        '<h3 class="out-of-screen-if-js">' . __('Global settings') . '</h3>';

        $settings = [];
        foreach (App::core()->blog()->settings()->dump() as $ns => $namespace) {
            foreach ($namespace->dumpGlobalSettings() as $k => $v) {
                $settings[$ns][$k] = $v;
            }
        }
        ksort($settings);

        if (count($settings) > 0) {
            $ns_combo = [];
            foreach ($settings as $ns => $s) {
                $ns_combo[$ns] = '#g_' . $ns;
            }
            $this->settingMenu($ns_combo, true);
        }

        $this->settingTable($settings, true);

        echo '</div>';
    }

    private function settingMenu(array $combo, bool $global): void
    {
        echo '<form action="' . App::core()->adminurl()->root() . '" method="post" class="anchor-nav-sticky">' .
        '<p class="anchor-nav">' .
        '<label for="' . ($global ? 'g' : 'l') . 's_nav" class="classic">' . __('Goto:') . '</label> ' .
        form::combo(($global ? 'g' : 'l') . 's_nav', $combo, ['class' => 'navigation']) .
        ' <input type="submit" value="' . __('Ok') . '" id="' . ($global ? 'g' : 'l') . 's_submit" />' .
        App::core()->adminurl()->getHiddenFormFields('admin.plugin.AboutConfig', [], true) .
        '</p></form>';
    }

    private function settingTable(array $prefs, bool $global): void
    {
        $table_header = '<div class="table-outer"><table class="settings" id="%s"><caption class="as_h3">%s</caption>' .
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
                echo self::settingLine($k, $v, $ws, ($global ? 'gs' : 's'), ($global ? false : !$v['global']));
            }
            echo $table_footer;
        }

        echo '<p><input type="submit" value="' . __('Save') . '" />' .
        '<input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />' .
        App::core()->adminurl()->getHiddenFormFields('admin.plugin.AboutConfig', [], true) . '</p>' .
        '</form>';
    }

    private function settingLine($id, $s, $ns, $field_name, $strong_label)
    {
        switch ($s['type']) {
            case 'boolean':
                $field = Form::combo(
                    [$field_name . '[' . $ns . '][' . $id . ']', $field_name . '_' . $ns . '_' . $id],
                    [__('yes') => 1, __('no') => 0],
                    (int) $s['value']
                );

                break;

            case 'array':
                $field = Form::field(
                    [$field_name . '[' . $ns . '][' . $id . ']', $field_name . '_' . $ns . '_' . $id],
                    40,
                    null,
                    Html::escapeHTML(json_encode($s['value']))
                );

                break;

            case 'integer':
                $field = Form::number(
                    [$field_name . '[' . $ns . '][' . $id . ']', $field_name . '_' . $ns . '_' . $id],
                    null,
                    null,
                    Html::escapeHTML((string) $s['value'])
                );

                break;

            default:
                $field = Form::field(
                    [$field_name . '[' . $ns . '][' . $id . ']', $field_name . '_' . $ns . '_' . $id],
                    40,
                    null,
                    Html::escapeHTML($s['value'])
                );

                break;
        }

        $type = Form::hidden(
            [$field_name . '_type' . '[' . $ns . '][' . $id . ']', $field_name . '_' . $ns . '_' . $id . '_type'],
            Html::escapeHTML($s['type'])
        );

        $slabel = $strong_label ? '<strong>%s</strong>' : '%s';

        return
        '<tr class="line">' .
        '<td scope="row"><label for="' . $field_name . '_' . $ns . '_' . $id . '">' . sprintf($slabel, Html::escapeHTML($id)) . '</label></td>' .
        '<td>' . $field . '</td>' .
        '<td>' . $s['type'] . $type . '</td>' .
        '<td>' . Html::escapeHTML($s['label']) . '</td>' .
            '</tr>';
    }
}
