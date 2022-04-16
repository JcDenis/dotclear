<?php
/**
 * @class Dotclear\Plugin\Widgets\Admin\Handler
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginWidgets
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Widgets\Admin;

use ArrayObject;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Module\AbstractPage;
use Dotclear\Plugin\Widgets\Common\WidgetsStack;
use Dotclear\Plugin\Widgets\Common\Widgets;


class Handler extends AbstractPage
{
    /** @var    Widgets     $widgets_nav    Navigation widgets */
    private $widgets_nav = null;

    /** @var    Widgets     $widgets_extra  Extra widgets */
    private $widgets_extra = null;

    /** @var    Widgets     $widgets_custom     Custom widgets */
    private $widgets_custom = null;

    protected function getPermissions(): string|null|false
    {
        # Super admin
        return null;
    }

    protected function getPagePrepend(): ?bool
    {
        $widgets = new Widgets();
        # Loading navigation, extra widgets and custom widgets
        if (dotclear()->blog()->settings()->get('widgets')->get('widgets_nav')) {
            $this->widgets_nav = $widgets->load(dotclear()->blog()->settings()->get('widgets')->get('widgets_nav'));
        }
        if (dotclear()->blog()->settings()->get('widgets')->get('widgets_extra')) {
            $this->widgets_extra = $widgets->load(dotclear()->blog()->settings()->get('widgets')->get('widgets_extra'));
        }
        if (dotclear()->blog()->settings()->get('widgets')->get('widgets_custom')) {
            $this->widgets_custom = $widgets->load(dotclear()->blog()->settings()->get('widgets')->get('widgets_custom'));
        }

        # Adding widgets to sidebars
        if (!empty($_POST['append']) && is_array($_POST['addw'])) {
            # Filter selection
            $addw = [];
            foreach ($_POST['addw'] as $k => $v) {
                if (in_array($v, ['extra', 'nav', 'custom']) && null !== WidgetsStack::$__widgets->get($k)) {
                    $addw[$k] = $v;
                }
            }

            # Append 1 widget
            $wid = false;
            if ('array' == gettype($_POST['append']) && 1 == count($_POST['append'])) {
                $wid = array_keys($_POST['append']);
                $wid = $wid[0];
            }

            # Append widgets
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
                    dotclear()->blog()->settings()->get('widgets')->put('widgets_nav', $this->widgets_nav->store());
                    dotclear()->blog()->settings()->get('widgets')->put('widgets_extra', $this->widgets_extra->store());
                    dotclear()->blog()->settings()->get('widgets')->put('widgets_custom', $this->widgets_custom->store());
                    dotclear()->blog()->triggerBlog();
                    dotclear()->adminurl()->redirect('admin.plugin.Widgets');
                } catch (\Exception $e) {
                    dotclear()->error()->add($e->getMessage());
                }
            }
        }

        # Removing ?
        $removing = false;
        if (isset($_POST['w']) && is_array($_POST['w'])) {
            foreach ($_POST['w'] as $nsid => $nsw) {
                foreach ($nsw as $i => $v) {
                    if (!empty($v['_rem'])) {
                        $removing = true;

                        break 2;
                    }
                }
            }
        }

        # Move ?
        $move = false;
        if (isset($_POST['w']) && is_array($_POST['w'])) {
            foreach ($_POST['w'] as $nsid => $nsw) {
                foreach ($nsw as $i => $v) {
                    if (!empty($v['_down'])) {
                        $oldorder = $_POST['w'][$nsid][$i]['order'];
                        $neworder = $oldorder + 1;
                        if (isset($_POST['w'][$nsid][$neworder])) {
                            $_POST['w'][$nsid][$i]['order']        = $neworder;
                            $_POST['w'][$nsid][$neworder]['order'] = $oldorder;
                            $move                                  = true;
                        }
                    }
                    if (!empty($v['_up'])) {
                        $oldorder = $_POST['w'][$nsid][$i]['order'];
                        $neworder = $oldorder - 1;
                        if (isset($_POST['w'][$nsid][$neworder])) {
                            $_POST['w'][$nsid][$i]['order']        = $neworder;
                            $_POST['w'][$nsid][$neworder]['order'] = $oldorder;
                            $move                                  = true;
                        }
                    }
                }
            }
        }

        # Update sidebars
        if (!empty($_POST['wup']) || $removing || $move) {
            if (!isset($_POST['w']) || !is_array($_POST['w'])) {
                $_POST['w'] = [];
            }

            try {
                # Removing mark as _rem widgets
                foreach ($_POST['w'] as $nsid => $nsw) {
                    foreach ($nsw as $i => $v) {
                        if (!empty($v['_rem'])) {
                            unset($_POST['w'][$nsid][$i]);

                            continue;
                        }
                    }
                }

                if (!isset($_POST['w']['nav'])) {
                    $_POST['w']['nav'] = [];
                }
                if (!isset($_POST['w']['extra'])) {
                    $_POST['w']['extra'] = [];
                }
                if (!isset($_POST['w']['custom'])) {
                    $_POST['w']['custom'] = [];
                }

                $this->widgets_nav    = $widgets->loadArray($_POST['w']['nav'], WidgetsStack::$__widgets);
                $this->widgets_extra  = $widgets->loadArray($_POST['w']['extra'], WidgetsStack::$__widgets);
                $this->widgets_custom = $widgets->loadArray($_POST['w']['custom'], WidgetsStack::$__widgets);

                dotclear()->blog()->settings()->get('widgets')->put('widgets_nav', $this->widgets_nav->store());
                dotclear()->blog()->settings()->get('widgets')->put('widgets_extra', $this->widgets_extra->store());
                dotclear()->blog()->settings()->get('widgets')->put('widgets_custom', $this->widgets_custom->store());
                dotclear()->blog()->triggerBlog();

                dotclear()->notice()->addSuccessNotice(__('Sidebars and their widgets have been saved.'));
                dotclear()->adminurl()->redirect('admin.plugin.Widgets');
            } catch (\Exception $e) {
                dotclear()->error()->add($e->getMessage());
            }
        } elseif (!empty($_POST['wreset'])) {
            try {
                dotclear()->blog()->settings()->get('widgets')->put('widgets_nav', '');
                dotclear()->blog()->settings()->get('widgets')->put('widgets_extra', '');
                dotclear()->blog()->settings()->get('widgets')->put('widgets_custom', '');
                dotclear()->blog()->triggerBlog();

                dotclear()->notice()->addSuccessNotice(__('Sidebars have been resetting.'));
                dotclear()->adminurl()->redirect('admin.plugin.Widgets');
            } catch (\Exception $e) {
                dotclear()->error()->add($e->getMessage());
            }
        }

        # Page setup
        $this
            ->setPageTitle(__('Widgets'))
            ->setPageHead(self::widgetsHead())
            ->setPageHelp('widgets', self::widgetsHelp())
            ->setPageBreadcrumb([
                Html::escapeHTML(dotclear()->blog()->name) => '',
                __('Widgets')                             => ''
            ])
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        # All widgets
        echo
        '<form id="listWidgets" action="' . dotclear()->adminurl()->root() . '" method="post"  class="widgets">' .
        '<h3>' . __('Available widgets') . '</h3>' .
        '<p>' . __('Drag widgets from this list to one of the sidebars, for add.') . '</p>' .
            '<ul id="widgets-ref">';

        $j = 0;
        foreach (WidgetsStack::$__widgets->elements(true) as $w) {
            echo
            '<li>' . Form::hidden(['w[void][0][id]'], Html::escapeHTML($w->id())) .
            '<p class="widget-name">' . Form::number(['w[void][0][order]'], [
                'default'    => 0,
                'class'      => 'hide',
                'extra_html' => 'title="' . __('order') . '"'
            ]) .
            ' ' . $w->name() .
            ($w->desc() != '' ? ' <span class="form-note">' . __($w->desc()) . '</span>' : '') . '</p>' .
            '<p class="manual-move remove-if-drag"><label class="classic">' . __('Append to:') . '</label> ' .
            Form::combo(['addw[' . $w->id() . ']'], $this->widgetsAppendCombo()) .
            '<input type="submit" name="append[' . $w->id() . ']" value="' . __('Add') . '" /></p>' .
            '<div class="widgetSettings hidden-if-drag">' . $w->formSettings('w[void][0]', $j) . '</div>' .
                '</li>';
            $j++;
        }

        echo
        '</ul>' .
        '<p class="remove-if-drag"><input type="submit" name="append" value="' . __('Add widgets to sidebars') . '" />' .
        dotclear()->adminurl()->getHiddenFormFields('admin.plugin.Widgets', [], true) . '</p>' .
            '</form>';

        echo '<form id="sidebarsWidgets" action="' . dotclear()->adminurl()->root() . '" method="post">';
        # Nav sidebar
        echo
        '<div id="sidebarNav" class="widgets fieldset">' .
        $this->sidebarWidgets('dndnav', __('Navigation sidebar'), $this->widgets_nav, 'nav', WidgetsStack::$__default_widgets['nav'], $j);
        echo '</div>';

        # Extra sidebar
        echo
        '<div id="sidebarExtra" class="widgets fieldset">' .
        $this->sidebarWidgets('dndextra', __('Extra sidebar'), $this->widgets_extra, 'extra', WidgetsStack::$__default_widgets['extra'], $j);
        echo '</div>';

        # Custom sidebar
        echo
        '<div id="sidebarCustom" class="widgets fieldset">' .
        $this->sidebarWidgets('dndcustom', __('Custom sidebar'), $this->widgets_custom, 'custom', WidgetsStack::$__default_widgets['custom'], $j);
        echo '</div>';

        echo
        '<p id="sidebarsControl">' .
        '<input type="submit" name="wup" value="' . __('Update sidebars') . '" /> ' .
        '<input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" /> ' .
        '<input type="submit" class="reset" name="wreset" value="' . __('Reset sidebars') . '" />' .
        dotclear()->adminurl()->getHiddenFormFields('admin.plugin.Widgets', [], true) .
        '</p>' .
        '</form>';
    }

    private function widgetsHead(): string
    {

        $widget_editor = dotclear()->user()->getOption('editor');
        $rte_flag      = true;
        $rte_flags     = @dotclear()->user()->preference()->get('interface')->get('rte_flags');
        if (is_array($rte_flags) && isset($rte_flags['widgets_text'])) {
            $rte_flag = $rte_flags['widgets_text'];
        }
        $user_dm_nodragdrop = dotclear()->user()->preference()->get('accessibility')->get('nodragdrop');

        return
        dotclear()->resource()->load('style.css', 'Plugin', 'Widgets') .
        dotclear()->resource()->load('jquery/jquery-ui.custom.js') .
        dotclear()->resource()->Load('jquery/jquery.ui.touch-punch.js') .
        dotclear()->resource()->json('widgets', [
            'widget_noeditor' => ($rte_flag ? 0 : 1),
            'msg'             => ['confirm_widgets_reset' => __('Are you sure you want to reset sidebars?')]
        ]) .
        dotclear()->resource()->load('widgets.js', 'Plugin', 'Widgets') .
        (!$user_dm_nodragdrop ? dotclear()->resource()->load('dragdrop.js', 'Plugin', 'Widgets') : '') .
        ($rte_flag ? (string) dotclear()->behavior()->call('adminPostEditor', $widget_editor['xhtml'], 'widget', ['#sidebarsWidgets textarea:not(.noeditor)'], 'xhtml') : '') .
        dotclear()->resource()->confirmClose('sidebarsWidgets');
    }

    private function widgetsHelp(): ArrayObject
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

        return new ArrayObject(['content' => $help]);
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
            $upDisabled   = $i == 0 ? ' disabled" src="?df=images/disabled_' : '" src="?df=images/';
            $downDisabled = $i == count($widgets->elements()) - 1 ? ' disabled" src="?df=images/disabled_' : '" src="?df=images/';
            $altUp        = $i == 0 ? ' alt=""' : ' alt="' . __('Up the widget') . '"';
            $altDown      = $i == count($widgets->elements()) - 1 ? ' alt=""' : ' alt="' . __('Down the widget') . '"';

            $iname   = 'w[' . $pr . '][' . $i . ']';
            $offline = $w->isOffline() ? ' offline' : '';

            $res .= '<li>' . Form::hidden([$iname . '[id]'], Html::escapeHTML($w->id())) .
            '<p class="widget-name' . $offline . '">' . Form::number([$iname . '[order]'], [
                'default'    => $i,
                'class'      => 'hidden',
                'extra_html' => 'title="' . __('order') . '"'
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

            $i++;
            $j++;
        }

        $res .= '</ul>';

        $res .= '<ul class="sortable-delete"' . ($i > 0 ? '' : ' style="display: none;"') . '><li class="sortable-delete-placeholder">' .
        __('Drag widgets here to remove.') . '</li></ul>';

        return $res;
    }

    private function widgetsAppendCombo(): array
    {
        return [
            '-'              => 0,
            __('navigation') => 'nav',
            __('extra')      => 'extra',
            __('custom')     => 'custom'
        ];
    }

    private function literalNullString(mixed $v): mixed
    {
        if ('' == $v) {
            return '&lt;' . __('empty string') . '&gt;';
        }

        return $v;
    }
}
