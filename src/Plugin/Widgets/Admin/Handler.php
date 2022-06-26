<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Widgets\Admin;

// Dotclear\Plugin\Widgets\Admin\Handler
use Dotclear\App;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\GPC\GPC;
use Dotclear\Helper\Html\Html;
use Dotclear\Plugin\Widgets\Common\Widgets;
use Dotclear\Plugin\Widgets\Common\WidgetsStack;
use Dotclear\Process\Admin\Page\AbstractPage;
use Exception;

/**
 * Widgets Admin page.
 *
 * @ingroup  Plugin Widgets
 */
class Handler extends AbstractPage
{
    /**
     * @var Widgets $widgets_nav
     *              Navigation widgets
     */
    private $widgets_nav;

    /**
     * @var Widgets $widgets_extra
     *              Extra widgets
     */
    private $widgets_extra;

    /**
     * @var Widgets $widgets_custom
     *              Custom widgets
     */
    private $widgets_custom;

    protected function getPermissions(): string|bool
    {
        return '';
    }

    protected function getPagePrepend(): ?bool
    {
        $widgets  = new Widgets();
        $settings = App::core()->blog()->settings()->getGroup('widgets');
        // Loading navigation, extra widgets and custom widgets
        if ($settings->getSetting('widgets_nav')) {
            $this->widgets_nav = $widgets->load($settings->getSetting('widgets_nav'));
        }
        if ($settings->getSetting('widgets_extra')) {
            $this->widgets_extra = $widgets->load($settings->getSetting('widgets_extra'));
        }
        if ($settings->getSetting('widgets_custom')) {
            $this->widgets_custom = $widgets->load($settings->getSetting('widgets_custom'));
        }

        // Adding widgets to sidebars
        if (!GPC::post()->empty('append')) {
            // Filter selection
            $addw = [];
            foreach (GPC::post()->array('addw') as $k => $v) {
                if (in_array($v, ['extra', 'nav', 'custom']) && null !== WidgetsStack::$__widgets->get($k)) {
                    $addw[$k] = $v;
                }
            }

            // Append 1 widget
            $wid = false;
            if ('array' == gettype(GPC::post()->get('append')) && 1 == count(GPC::post()->array('append'))) {
                $wid = array_keys(GPC::post()->array('append'));
                $wid = $wid[0];
            }

            // Append widgets
            if (!empty($addw)) {
                if (!($this->widgets_nav instanceof Widgets)) {
                    $this->widgets_nav = new Widgets();
                }
                if (!($this->widgets_extra instanceof Widgets)) {
                    $this->widgets_extra = new Widgets();
                }
                if (!($this->widgets_custom instanceof Widgets)) {
                    $this->widgets_custom = new Widgets();
                }

                foreach ($addw as $k => $v) {
                    if (!$wid || $wid == $k) {
                        switch ($v) {
                            case 'nav':
                                $this->widgets_nav->append(WidgetsStack::$__widgets->get($k));

                                break;

                            case 'extra':
                                $this->widgets_extra->append(WidgetsStack::$__widgets->get($k));

                                break;

                            case 'custom':
                                $this->widgets_custom->append(WidgetsStack::$__widgets->get($k));

                                break;
                        }
                    }
                }

                try {
                    $settings->putSetting('widgets_nav', $this->widgets_nav->store());
                    $settings->putSetting('widgets_extra', $this->widgets_extra->store());
                    $settings->putSetting('widgets_custom', $this->widgets_custom->store());
                    App::core()->blog()->triggerBlog();
                    App::core()->adminurl()->redirect('admin.plugin.Widgets');
                } catch (Exception $e) {
                    App::core()->error()->add($e->getMessage());
                }
            }
        }

        $w = GPC::post()->array('w');

        // Removing ?
        $removing = false;
        foreach ($w as $nsid => $nsw) {
            foreach ($nsw as $i => $v) {
                if (!empty($v['_rem'])) {
                    $removing = true;

                    break 2;
                }
            }
        }

        // Move ?
        $move = false;
        foreach ($w as $nsid => $nsw) {
            foreach ($nsw as $i => $v) {
                if (!empty($v['_down'])) {
                    $oldorder = $w[$nsid][$i]['order'];
                    $neworder = $oldorder + 1;
                    if (isset($w[$nsid][$neworder])) {
                        $w[$nsid][$i]['order']                 = $neworder;
                        $w[$nsid][$neworder]['order']          = $oldorder;
                        $move                                  = true;
                    }
                }
                if (!empty($v['_up'])) {
                    $oldorder = $w[$nsid][$i]['order'];
                    $neworder = $oldorder - 1;
                    if (isset($w[$nsid][$neworder])) {
                        $w[$nsid][$i]['order']                 = $neworder;
                        $w[$nsid][$neworder]['order']          = $oldorder;
                        $move                                  = true;
                    }
                }
            }
        }

        // Update sidebars
        if (!GPC::post()->empty('wup') || $removing || $move) {
            try {
                // Removing mark as _rem widgets
                foreach ($w as $nsid => $nsw) {
                    foreach ($nsw as $i => $v) {
                        if (!empty($v['_rem'])) {
                            unset($w[$nsid][$i]);

                            continue;
                        }
                    }
                }

                if (!isset($w['nav'])) {
                    $w['nav'] = [];
                }
                if (!isset($w['extra'])) {
                    $w['extra'] = [];
                }
                if (!isset($w['custom'])) {
                    $w['custom'] = [];
                }

                $this->widgets_nav    = $widgets->loadArray($w['nav'], WidgetsStack::$__widgets);
                $this->widgets_extra  = $widgets->loadArray($w['extra'], WidgetsStack::$__widgets);
                $this->widgets_custom = $widgets->loadArray($w['custom'], WidgetsStack::$__widgets);

                $settings->putSetting('widgets_nav', $this->widgets_nav->store());
                $settings->putSetting('widgets_extra', $this->widgets_extra->store());
                $settings->putSetting('widgets_custom', $this->widgets_custom->store());
                App::core()->blog()->triggerBlog();

                App::core()->notice()->addSuccessNotice(__('Sidebars and their widgets have been saved.'));
                App::core()->adminurl()->redirect('admin.plugin.Widgets');
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
            }
        } elseif (!GPC::post()->empty('wreset')) {
            try {
                $settings->putSetting('widgets_nav', '');
                $settings->putSetting('widgets_extra', '');
                $settings->putSetting('widgets_custom', '');
                App::core()->blog()->triggerBlog();

                App::core()->notice()->addSuccessNotice(__('Sidebars have been resetting.'));
                App::core()->adminurl()->redirect('admin.plugin.Widgets');
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
            }
        }

        // Page setup
        $this
            ->setPageTitle(__('Widgets'))
            ->setPageHead(self::widgetsHead())
            ->setPageHelp('widgets')
            ->setPageHelpContent(self::widgetsHelp())
            ->setPageBreadcrumb([
                Html::escapeHTML(App::core()->blog()->name) => '',
                __('Widgets')                               => '',
            ])
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        // All widgets
        echo '<form id="listWidgets" action="' . App::core()->adminurl()->root() . '" method="post"  class="widgets">' .
        '<h3>' . __('Available widgets') . '</h3>' .
        '<p>' . __('Drag widgets from this list to one of the sidebars, for add.') . '</p>' .
            '<ul id="widgets-ref">';

        $j = 0;
        foreach (WidgetsStack::$__widgets->elements(true) as $w) {
            echo '<li>' . Form::hidden(['w[void][0][id]'], Html::escapeHTML($w->id())) .
            '<p class="widget-name">' . Form::number(['w[void][0][order]'], [
                'default'    => 0,
                'class'      => 'hide',
                'extra_html' => 'title="' . __('order') . '"',
            ]) .
            ' ' . $w->name() .
            ($w->desc() != '' ? ' <span class="form-note">' . __($w->desc()) . '</span>' : '') . '</p>' .
            '<p class="manual-move remove-if-drag"><label class="classic">' . __('Append to:') . '</label> ' .
            Form::combo(['addw[' . $w->id() . ']'], $this->widgetsAppendCombo()) .
            '<input type="submit" name="append[' . $w->id() . ']" value="' . __('Add') . '" /></p>' .
            '<div class="widgetSettings hidden-if-drag">' . $w->formSettings('w[void][0]', $j) . '</div>' .
                '</li>';
            ++$j;
        }

        echo '</ul>' .
        '<p class="remove-if-drag"><input type="submit" name="append" value="' . __('Add widgets to sidebars') . '" />' .
        App::core()->adminurl()->getHiddenFormFields('admin.plugin.Widgets', [], true) . '</p>' .
            '</form>';

        echo '<form id="sidebarsWidgets" action="' . App::core()->adminurl()->root() . '" method="post">';
        // Nav sidebar
        echo '<div id="sidebarNav" class="widgets fieldset">' .
        $this->sidebarWidgets('dndnav', __('Navigation sidebar'), $this->widgets_nav, 'nav', WidgetsStack::$__default_widgets['nav'], $j);
        echo '</div>';

        // Extra sidebar
        echo '<div id="sidebarExtra" class="widgets fieldset">' .
        $this->sidebarWidgets('dndextra', __('Extra sidebar'), $this->widgets_extra, 'extra', WidgetsStack::$__default_widgets['extra'], $j);
        echo '</div>';

        // Custom sidebar
        echo '<div id="sidebarCustom" class="widgets fieldset">' .
        $this->sidebarWidgets('dndcustom', __('Custom sidebar'), $this->widgets_custom, 'custom', WidgetsStack::$__default_widgets['custom'], $j);
        echo '</div>';

        echo '<p id="sidebarsControl">' .
        '<input type="submit" name="wup" value="' . __('Update sidebars') . '" /> ' .
        '<input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" /> ' .
        '<input type="submit" class="reset" name="wreset" value="' . __('Reset sidebars') . '" />' .
        App::core()->adminurl()->getHiddenFormFields('admin.plugin.Widgets', [], true) .
        '</p>' .
        '</form>';
    }

    private function widgetsHead(): string
    {
        $widget_editor = App::core()->user()->getOption('editor');
        $rte_flag      = true;
        $rte_flags     = App::core()->user()->preference()->get('interface')->get('rte_flags');
        if (is_array($rte_flags) && isset($rte_flags['widgets_text'])) {
            $rte_flag = $rte_flags['widgets_text'];
        }
        $user_dm_nodragdrop = App::core()->user()->preference()->get('accessibility')->get('nodragdrop');

        return
        App::core()->resource()->load('style.css', 'Plugin', 'Widgets') .
        App::core()->resource()->load('jquery/jquery-ui.custom.js') .
        App::core()->resource()->Load('jquery/jquery.ui.touch-punch.js') .
        App::core()->resource()->json('widgets', [
            'widget_noeditor' => ($rte_flag ? '0' : '1'),
            'msg'             => ['confirm_widgets_reset' => __('Are you sure you want to reset sidebars?')],
        ]) .
        App::core()->resource()->load('widgets.js', 'Plugin', 'Widgets') .
        (!$user_dm_nodragdrop ? App::core()->resource()->load('dragdrop.js', 'Plugin', 'Widgets') : '') .
        ($rte_flag ? (string) App::core()->behavior('adminPostEditor')->call($widget_editor['xhtml'], 'widget', ['#sidebarsWidgets textarea:not(.noeditor)'], 'xhtml') : '') .
        App::core()->resource()->confirmClose('sidebarsWidgets');
    }

    private function widgetsHelp(): string
    {
        $help = '<dl>';
        foreach (WidgetsStack::$__widgets->elements() as $w) {
            $help .= '<dt><strong>' . Html::escapeHTML($w->name()) . '</strong> (' .
            __('Widget ID:') . ' <strong>' . Html::escapeHTML($w->id()) . '</strong>)' .
                ($w->desc() != '' ? ' <span class="form-note">' . __($w->desc()) . '</span>' : '') . '</dt>' .
                '<dd>';

            $w_settings = $w->settings();
            if (empty($w_settings)) {
                $help .= '<p>' . __('No setting for this widget') . '</p>';
            } else {
                $help .= '<ul>';
                foreach ($w->settings() as $n => $s) {
                    switch ($s['type']) {
                        case 'check':
                            $s_type = __('boolean') . ', ' . __('possible values:') . ' <code>0</code> ' . __('or') . ' <code>1</code>';

                            break;

                        case 'combo':
                            $s['options'] = array_map([$this, 'literalNullString'], $s['options']);
                            $s_type       = __('listitem') . ', ' . __('possible values:') . ' <code>' . implode('</code>, <code>', $s['options']) . '</code>';

                            break;

                        case 'text':
                        case 'textarea':
                        default:
                            $s_type = __('string');

                            break;
                    }

                    $help .= '<li>' .
                    __('Setting name:') . ' <strong>' . Html::escapeHTML($n) . '</strong>' .
                        ' (' . $s_type . ')' .
                        '</li>';
                }
                $help .= '</ul>';
            }
            $help .= '</dd>';
        }
        $help .= '</dl></div>';

        return $help;
    }

    private function sidebarWidgets(string $id, string $title, ?Widgets $widgets, string $pr, Widgets $default_widgets, int &$j): string
    {
        $res = '<h3>' . $title . '</h3>';

        if (!($widgets instanceof Widgets)) {
            $widgets = $default_widgets;
        }

        $res .= '<ul id="' . $id . '" class="connected">';

        $res .= '<li class="empty-widgets" ' . (!$widgets->isEmpty() ? 'style="display: none;"' : '') . '>' . __('No widget as far.') . '</li>';

        $i = 0;
        foreach ($widgets->elements() as $w) {
            $upDisabled   = 0                               == $i ? ' disabled" src="?df=images/disabled_' : '" src="?df=images/';
            $downDisabled = count($widgets->elements()) - 1 == $i ? ' disabled" src="?df=images/disabled_' : '" src="?df=images/';
            $altUp        = 0                               == $i ? ' alt=""' : ' alt="' . __('Up the widget') . '"';
            $altDown      = count($widgets->elements()) - 1 == $i ? ' alt=""' : ' alt="' . __('Down the widget') . '"';

            $iname   = 'w[' . $pr . '][' . $i . ']';
            $offline = $w->isOffline() ? ' offline' : '';

            $res .= '<li>' . Form::hidden([$iname . '[id]'], Html::escapeHTML($w->id())) .
            '<p class="widget-name' . $offline . '">' . Form::number([$iname . '[order]'], [
                'default'    => $i,
                'class'      => 'hidden',
                'extra_html' => 'title="' . __('order') . '"',
            ]) .
            ' ' . $w->name() .
            ($w->desc() != '' ? ' <span class="form-note">' . __($w->desc()) . '</span>' : '') .
            '<span class="toolsWidget remove-if-drag">' .
            '<input type="image" class="upWidget' . $upDisabled . 'up.png" name="' . $iname . '[_up]" value="' . __('Up the widget') . '"' . $altUp . ' /> ' .
            '<input type="image" class="downWidget' . $downDisabled . 'down.png" name="' . $iname . '[_down]" value="' . __('Down the widget') . '"' . $altDown . ' /> ' . ' ' .
            '<input type="image" class="removeWidget" src="?df=images/trash.png" name="' . $iname . '[_rem]" value="' . __('Remove widget') . '" alt="' . __('Remove the widget') . '" />' .
            '</span>' .
            '<br class="clear"/></p>' .
            '<div class="widgetSettings hidden-if-drag">' . $w->formSettings($iname, $j) . '</div>' .
                '</li>';

            ++$i;
            ++$j;
        }

        $res .= '</ul>';

        $res .= '<ul class="sortable-delete"' . (0 < $i ? '' : ' style="display: none;"') . '><li class="sortable-delete-placeholder">' .
        __('Drag widgets here to remove.') . '</li></ul>';

        return $res;
    }

    private function widgetsAppendCombo(): array
    {
        return [
            '-'              => 0,
            __('navigation') => 'nav',
            __('extra')      => 'extra',
            __('custom')     => 'custom',
        ];
    }

    private function literalNullString(mixed $v): mixed
    {
        return '' == $v ? '&lt;' . __('empty string') . '&gt;' : $v;
    }
}
