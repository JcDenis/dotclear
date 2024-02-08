<?php
/**
 * @package     Dotclear
 *
 * @copyright   Olivier Meunier & Association Dotclear
 * @copyright   GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\widgets;

use Dotclear\Core\Backend\Notices;
use Dotclear\Core\Backend\Page;
use Dotclear\App;
use Dotclear\Core\Process;
use Dotclear\Helper\Html\Html;
use Exception;
use form;
use stdClass;
use UnhandledMatchError;

/**
 * @brief   The module backend manage process.
 * @ingroup widgets
 */
class Manage extends Process
{
    public static function init(): bool
    {
        return self::status(My::checkContext(My::MANAGE));
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        // Init default widgets
        Widgets::init();

        // Loading navigation, extra widgets and custom widgets
        App::backend()->widgets_nav = null;
        if (is_array(My::settings()->widgets_nav)) {
            App::backend()->widgets_nav = WidgetsStack::load(My::settings()->widgets_nav);
        }
        App::backend()->widgets_extra = null;
        if (is_array(My::settings()->widgets_extra)) {
            App::backend()->widgets_extra = WidgetsStack::load(My::settings()->widgets_extra);
        }
        App::backend()->widgets_custom = null;
        if (is_array(My::settings()->widgets_custom)) {
            App::backend()->widgets_custom = WidgetsStack::load(My::settings()->widgets_custom);
        }

        App::backend()->append_combo = [
            '-'              => 0,
            __('navigation') => Widgets::WIDGETS_NAV,
            __('extra')      => Widgets::WIDGETS_EXTRA,
            __('custom')     => Widgets::WIDGETS_CUSTOM,
        ];

        # Adding widgets to sidebars
        if (!empty($_POST['append']) && is_array($_POST['addw'])) {
            # Filter selection
            $addw = [];
            foreach ($_POST['addw'] as $k => $v) {
                if (($v == Widgets::WIDGETS_EXTRA || $v == Widgets::WIDGETS_NAV || $v == Widgets::WIDGETS_CUSTOM) && Widgets::$widgets->{$k} !== null) {
                    $addw[$k] = $v;
                }
            }

            # Append 1 widget
            $wid = false;
            if (gettype($_POST['append']) == 'array' && count($_POST['append']) == 1) {
                $wid = array_keys($_POST['append']);
                $wid = $wid[0];
            }

            # Append widgets
            if (!empty($addw)) {
                if (!(App::backend()->widgets_nav instanceof WidgetsStack)) {
                    App::backend()->widgets_nav = new WidgetsStack();
                }
                if (!(App::backend()->widgets_extra instanceof WidgetsStack)) {
                    App::backend()->widgets_extra = new WidgetsStack();
                }
                if (!(App::backend()->widgets_custom instanceof WidgetsStack)) {
                    App::backend()->widgets_custom = new WidgetsStack();
                }

                foreach ($addw as $k => $v) {
                    if (!$wid || $wid == $k) {
                        try {
                            match ($v) {
                                Widgets::WIDGETS_NAV    => App::backend()->widgets_nav->append(Widgets::$widgets->{$k}),
                                Widgets::WIDGETS_EXTRA  => App::backend()->widgets_extra->append(Widgets::$widgets->{$k}),
                                Widgets::WIDGETS_CUSTOM => App::backend()->widgets_custom->append(Widgets::$widgets->{$k}),
                            };
                        } catch (UnhandledMatchError) {
                        }
                    }
                }

                try {
                    My::settings()->put('widgets_nav', App::backend()->widgets_nav->store(), App::blogWorkspace()::NS_ARRAY);
                    My::settings()->put('widgets_extra', App::backend()->widgets_extra->store(), App::blogWorkspace()::NS_ARRAY);
                    My::settings()->put('widgets_custom', App::backend()->widgets_custom->store(), App::blogWorkspace()::NS_ARRAY);
                    App::blog()->triggerBlog();
                    My::redirect();
                } catch (Exception $e) {
                    App::error()->add($e->getMessage());
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

                if (!isset($_POST['w'][Widgets::WIDGETS_NAV])) {
                    $_POST['w'][Widgets::WIDGETS_NAV] = [];
                }
                if (!isset($_POST['w'][Widgets::WIDGETS_EXTRA])) {
                    $_POST['w'][Widgets::WIDGETS_EXTRA] = [];
                }
                if (!isset($_POST['w'][Widgets::WIDGETS_CUSTOM])) {
                    $_POST['w'][Widgets::WIDGETS_CUSTOM] = [];
                }

                App::backend()->widgets_nav    = WidgetsStack::loadArray($_POST['w'][Widgets::WIDGETS_NAV], Widgets::$widgets);
                App::backend()->widgets_extra  = WidgetsStack::loadArray($_POST['w'][Widgets::WIDGETS_EXTRA], Widgets::$widgets);
                App::backend()->widgets_custom = WidgetsStack::loadArray($_POST['w'][Widgets::WIDGETS_CUSTOM], Widgets::$widgets);

                My::settings()->put('widgets_nav', App::backend()->widgets_nav->store(), App::blogWorkspace()::NS_ARRAY);
                My::settings()->put('widgets_extra', App::backend()->widgets_extra->store(), App::blogWorkspace()::NS_ARRAY);
                My::settings()->put('widgets_custom', App::backend()->widgets_custom->store(), App::blogWorkspace()::NS_ARRAY);

                App::blog()->triggerBlog();

                Notices::addSuccessNotice(__('Sidebars and their widgets have been saved.'));
                My::redirect();
            } catch (Exception $e) {
                App::error()->add($e->getMessage());
            }
        } elseif (!empty($_POST['wreset'])) {
            # Reset widgets list
            try {
                My::settings()->drop('widgets_nav');
                My::settings()->drop('widgets_extra');
                My::settings()->drop('widgets_custom');

                App::blog()->triggerBlog();

                Notices::addSuccessNotice(__('Sidebars have been resetting.'));
                My::redirect();
            } catch (Exception $e) {
                App::error()->add($e->getMessage());
            }
        }

        return true;
    }

    public static function render(): void
    {
        if (!self::status()) {
            return;
        }

        $widget_editor = App::auth()->getOption('editor');
        $rte_flag      = true;
        $rte_flags     = @App::auth()->prefs()->interface->rte_flags;
        if (is_array($rte_flags) && in_array('widgets_text', $rte_flags)) {
            $rte_flag = $rte_flags['widgets_text'];
        }

        $head = My::cssLoad('style') .
            Page::jsLoad('js/jquery/jquery-ui.custom.js') .
            Page::jsLoad('js/jquery/jquery.ui.touch-punch.js') .
            Page::jsJson('widgets', [
                'widget_noeditor' => ($rte_flag ? 0 : 1),
                'msg'             => ['confirm_widgets_reset' => __('Are you sure you want to reset sidebars?')],
            ]) .
            My::jsLoad('widgets');

        $user_dm_nodragdrop = App::auth()->prefs()->accessibility->nodragdrop;
        if (!$user_dm_nodragdrop) {
            $head .= My::jsLoad('dragdrop');
        }
        if ($rte_flag) {
            # --BEHAVIOR-- adminPostEditor -- string, string, string, array<int,string>, string
            $head .= App::behavior()->callBehavior(
                'adminPostEditor',
                $widget_editor['xhtml'],
                'widget',
                ['#sidebarsWidgets textarea:not(.noeditor)'],
                'xhtml'
            );
        }
        $head .= Page::jsConfirmClose('sidebarsWidgets');

        Page::openModule(My::name(), $head);

        echo
        Page::breadcrumb(
            [
                Html::escapeHTML(App::blog()->name()) => '',
                My::name()                            => '',
            ]
        ) .
        Notices::getNotices() .

        # All widgets
        '<form id="listWidgets" action="' . App::backend()->getPageURL() . '" method="post"  class="widgets">' .
        '<h3>' . __('Available widgets') . '</h3>' .
        '<p>' . __('Drag widgets from this list to one of the sidebars, for add.') . '</p>' .
        '<ul id="widgets-ref">';

        $j = 0;
        foreach (Widgets::$widgets->elements(true) as $w) {
            echo
            '<li>' . form::hidden(['w[void][0][id]'], Html::escapeHTML($w->id())) .
            '<p class="widget-name">' . form::number(['w[void][0][order]'], [
                'default'    => 0,
                'class'      => 'hide',
                'extra_html' => 'title="' . __('order') . '"',
            ]) .
            ' ' . $w->name() .
            ($w->desc() != '' ? ' <span class="form-note">' . __($w->desc()) . '</span>' : '') . '</p>' .
            '<p class="manual-move remove-if-drag"><label class="classic">' . __('Append to:') . '</label> ' .
            form::combo(['addw[' . $w->id() . ']'], App::backend()->append_combo) .
            '<input type="submit" name="append[' . $w->id() . ']" value="' . __('Add') . '"></p>' .
            '<div class="widgetSettings hidden-if-drag">' . $w->formSettings('w[void][0]', $j) . '</div>' .
            '</li>';
            $j++;
        }

        echo
        '</ul>' .
        '<p>' . App::nonce()->getFormNonce() . '</p>' .
        '<p class="remove-if-drag"><input type="submit" name="append" value="' . __('Add widgets to sidebars') . '"></p>' .
        '</form>' .

        '<form id="sidebarsWidgets" action="' . App::backend()->getPageURL() . '" method="post">' .

        // Nav sidebar
        '<div id="sidebarNav" class="widgets fieldset">' .
        self::sidebarWidgets('dndnav', __('Navigation sidebar'), App::backend()->widgets_nav, Widgets::WIDGETS_NAV, Widgets::$default_widgets[Widgets::WIDGETS_NAV], $j) .
        '</div>' .

        // Extra sidebar
        '<div id="sidebarExtra" class="widgets fieldset">' .
        self::sidebarWidgets('dndextra', __('Extra sidebar'), App::backend()->widgets_extra, Widgets::WIDGETS_EXTRA, Widgets::$default_widgets[Widgets::WIDGETS_EXTRA], $j) .
        '</div>' .

        // Custom sidebar
        '<div id="sidebarCustom" class="widgets fieldset">' .
        self::sidebarWidgets('dndcustom', __('Custom sidebar'), App::backend()->widgets_custom, Widgets::WIDGETS_CUSTOM, Widgets::$default_widgets[Widgets::WIDGETS_CUSTOM], $j) .
        '</div>' .

        '<p id="sidebarsControl">' .
        App::nonce()->getFormNonce() .
        '<input type="submit" name="wup" value="' . __('Update sidebars') . '"> ' .
        '<input type="button" value="' . __('Back') . '" class="go-back reset hidden-if-no-js"> ' .
        '<input type="submit" class="reset" name="wreset" value="' . __('Reset sidebars') . '">' .
        '</p>' .
        '</form>';

        $widget_elements          = new stdClass();
        $widget_elements->content = '<dl>';
        foreach (Widgets::$widgets->elements() as $w) {
            $widget_elements->content .= '<dt><strong>' . Html::escapeHTML($w->name()) . '</strong> (' .
            __('Widget ID:') . ' <strong>' . Html::escapeHTML($w->id()) . '</strong>)' .
                ($w->desc() != '' ? ' <span class="form-note">' . __($w->desc()) . '</span>' : '') . '</dt>' .
                '<dd>';

            $w_settings = $w->settings();
            if (!count($w_settings)) {
                $widget_elements->content .= '<p>' . __('No setting for this widget') . '</p>';
            } else {
                $widget_elements->content .= '<ul>';
                foreach ($w_settings as $n => $s) {
                    switch ($s['type']) {
                        case 'check':
                            $s_type = __('boolean') . ', ' . __('possible values:') . ' <code>0</code> ' . __('or') . ' <code>1</code>';

                            break;
                        case 'combo':
                            $s['options'] = array_map(fn ($v) => ($v == '' ? '&lt;' . __('empty string') . '&gt;' : $v), $s['options']);
                            $s_type       = __('listitem') . ', ' . __('possible values:') . ' <code>' . implode('</code>, <code>', $s['options']) . '</code>';

                            break;
                        case 'text':
                        case 'textarea':
                        default:
                            $s_type = __('string');

                            break;
                    }

                    $widget_elements->content .= '<li>' .
                    __('Setting name:') . ' <strong>' . Html::escapeHTML($n) . '</strong>' .
                        ' (' . $s_type . ')' .
                        '</li>';
                }
                $widget_elements->content .= '</ul>';
            }
            $widget_elements->content .= '</dd>';
        }
        $widget_elements->content .= '</dl></div>';

        Page::helpBlock(My::id(), $widget_elements);

        Page::closeModule();
    }

    /**
     * Return HTML code for a list of widgets
     *
     * @param      string           $id               The identifier
     * @param      string           $title            The title
     * @param      WidgetsStack     $widgets          The widgets
     * @param      string           $pr               The widget group id
     * @param      WidgetsStack     $default_widgets  The default widgets
     * @param      int              $j                Current widget counter
     *
     * @return     string
     */
    protected static function sidebarWidgets(string $id, string $title, ?WidgetsStack $widgets, string $pr, WidgetsStack $default_widgets, &$j)
    {
        $res = '<h3>' . $title . '</h3>';

        if (!($widgets instanceof WidgetsStack)) {
            $widgets = $default_widgets;
        }

        $res .= '<ul id="' . $id . '" class="connected">' .
        '<li class="empty-widgets" ' . (!$widgets->isEmpty() ? 'style="display: none;"' : '') . '>' .
        __('No widget as far.') .
        '</li>';

        $i = 0;
        foreach ($widgets->elements() as $w) {
            $upDisabled   = $i == 0 ? ' disabled" src="images/disabled_' : '" src="images/';
            $downDisabled = $i == count($widgets->elements()) - 1 ? ' disabled" src="images/disabled_' : '" src="images/';
            $altUp        = $i == 0 ? ' alt=""' : ' alt="' . __('Up the widget') . '"';
            $altDown      = $i == count($widgets->elements()) - 1 ? ' alt=""' : ' alt="' . __('Down the widget') . '"';

            $iname   = 'w[' . $pr . '][' . $i . ']';
            $offline = $w->isOffline() ? ' offline' : '';

            $res .= '<li>' . form::hidden([$iname . '[id]'], Html::escapeHTML($w->id())) .
            '<p class="widget-name' . $offline . '">' . form::number([$iname . '[order]'], [
                'default'    => $i,
                'class'      => 'hidden',
                'extra_html' => 'title="' . __('order') . '"',
            ]) .
            ' ' . $w->name() .
            ($w->desc() != '' ? ' <span class="form-note">' . __($w->desc()) . '</span>' : '') .
            '<span class="toolsWidget remove-if-drag">' .
            '<input type="image" class="upWidget' . $upDisabled . 'up.png" name="' . $iname . '[_up]" value="' . __('Up the widget') . '"' . $altUp . '> ' .
            '<input type="image" class="downWidget' . $downDisabled . 'down.png" name="' . $iname . '[_down]" value="' . __('Down the widget') . '"' . $altDown . '> ' . ' ' .
            '<input type="image" class="removeWidget" src="images/trash.svg" name="' . $iname . '[_rem]" value="' . __('Remove widget') . '" alt="' . __('Remove the widget') . '">' .
            '</span>' .
            '<br class="clear"></p>' .
            '<div class="widgetSettings hidden-if-drag">' . $w->formSettings($iname, $j) . '</div>' .
            '</li>';

            $i++;
            $j++;
        }

        $res .= '</ul>' .
        '<ul class="sortable-delete"' . ($i > 0 ? '' : ' style="display: none;"') . '>' .
        '<li class="sortable-delete-placeholder">' . __('Drag widgets here to remove.') . '</li>' .
        '</ul>';

        return $res;
    }
}
