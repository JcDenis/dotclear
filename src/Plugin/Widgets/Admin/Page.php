<?php
/**
 * @class Dotclear\Plugin\Widgets\Admin\Page
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

use stdClass;

use Dotclear\Exception;

use Dotclear\Module\AbstractPage;

use Dotclear\Plugin\Widgets\Lib\WidgetsStack;
use Dotclear\Plugin\Widgets\Lib\Widgets;

use Dotclear\Html\Form;
use Dotclear\Html\Html;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Page extends AbstractPage
{
    protected $namespaces = ['widgets'];
    protected $workspaces = ['accessibility'];

    private $widgets_nav = null;
    private $widgets_extra = null;
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
        if (dcCore()->blog->settings->widgets->widgets_nav) {
            $this->widgets_nav = $widgets->load(dcCore()->blog->settings->widgets->widgets_nav);
        }
        if (dcCore()->blog->settings->widgets->widgets_extra) {
            $this->widgets_extra = $widgets->load(dcCore()->blog->settings->widgets->widgets_extra);
        }
        if (dcCore()->blog->settings->widgets->widgets_custom) {
            $this->widgets_custom = $widgets->load(dcCore()->blog->settings->widgets->widgets_custom);
        }

        # Adding widgets to sidebars
        if (!empty($_POST['append']) && is_array($_POST['addw'])) {
            # Filter selection
            $addw = [];
            foreach ($_POST['addw'] as $k => $v) {
                if (($v == 'extra' || $v == 'nav' || $v == 'custom') && WidgetsStack::$__widgets->{$k} !== null) {
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
                                $this->widgets_nav->append(WidgetsStack::$__widgets->{$k});

                                break;
                            case 'extra':
                                $this->widgets_extra->append(WidgetsStack::$__widgets->{$k});

                                break;
                            case 'custom':
                                $this->widgets_custom->append(WidgetsStack::$__widgets->{$k});

                                break;
                        }
                    }
                }

                try {
                    dcCore()->blog->settings->widgets->put('widgets_nav', $this->widgets_nav->store());
                    dcCore()->blog->settings->widgets->put('widgets_extra', $this->widgets_extra->store());
                    dcCore()->blog->settings->widgets->put('widgets_custom', $this->widgets_custom->store());
                    dcCore()->blog->triggerBlog();
                    dcCore()->adminurl->redirect('admin.plugin.Widgets');
                } catch (Exception $e) {
                    dcCore()->error->add($e->getMessage());
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

                dcCore()->blog->settings->widgets->put('widgets_nav', $this->widgets_nav->store());
                dcCore()->blog->settings->widgets->put('widgets_extra', $this->widgets_extra->store());
                dcCore()->blog->settings->widgets->put('widgets_custom', $this->widgets_custom->store());
                dcCore()->blog->triggerBlog();

                dcCore()->notices->addSuccessNotice(__('Sidebars and their widgets have been saved.'));
                dcCore()->adminurl->redirect('admin.plugin.Widgets');
            } catch (Exception $e) {
                dcCore()->error->add($e->getMessage());
            }
        } elseif (!empty($_POST['wreset'])) {
            try {
                dcCore()->blog->settings->widgets->put('widgets_nav', '');
                dcCore()->blog->settings->widgets->put('widgets_extra', '');
                dcCore()->blog->settings->widgets->put('widgets_custom', '');
                dcCore()->blog->triggerBlog();

                dcCore()->notices->addSuccessNotice(__('Sidebars have been resetting.'));
                dcCore()->adminurl->redirect('admin.plugin.Widgets');
            } catch (Exception $e) {
                dcCore()->error->add($e->getMessage());
            }
        }

        # Page setup
        $this
            ->setPageTitle(__('Widgets'))
            ->setPageHead(self::widgetsHead())
            ->setPageHelp('widgets', self::widgetsHelp())
            ->setPageBreadcrumb([
                html::escapeHTML(dcCore()->blog->name) => '',
                __('Widgets')                             => ''
            ])
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        # All widgets
        echo
        '<form id="listWidgets" action="' . dcCore()->adminurl->get('admin.plugin.Widgets') . '" method="post"  class="widgets">' .
        '<h3>' . __('Available widgets') . '</h3>' .
        '<p>' . __('Drag widgets from this list to one of the sidebars, for add.') . '</p>' .
            '<ul id="widgets-ref">';

        $j = 0;
        foreach (WidgetsStack::$__widgets->elements(true) as $w) {
            echo
            '<li>' . form::hidden(['w[void][0][id]'], html::escapeHTML($w->id())) .
            '<p class="widget-name">' . form::number(['w[void][0][order]'], [
                'default'    => 0,
                'class'      => 'hide',
                'extra_html' => 'title="' . __('order') . '"'
            ]) .
            ' ' . $w->name() .
            ($w->desc() != '' ? ' <span class="form-note">' . __($w->desc()) . '</span>' : '') . '</p>' .
            '<p class="manual-move remove-if-drag"><label class="classic">' . __('Append to:') . '</label> ' .
            form::combo(['addw[' . $w->id() . ']'], self::widgetsAppendCombo()) .
            '<input type="submit" name="append[' . $w->id() . ']" value="' . __('Add') . '" /></p>' .
            '<div class="widgetSettings hidden-if-drag">' . $w->formSettings('w[void][0]', $j) . '</div>' .
                '</li>';
            $j++;
        }

        echo
        '</ul>' .
        '<p>' . dcCore()->formNonce() . '</p>' .
        '<p class="remove-if-drag"><input type="submit" name="append" value="' . __('Add widgets to sidebars') . '" /></p>' .
            '</form>';

        echo '<form id="sidebarsWidgets" action="' . dcCore()->adminurl->get('admin.plugin.Widgets') . '" method="post">';
        # Nav sidebar
        echo
        '<div id="sidebarNav" class="widgets fieldset">' .
        self::sidebarWidgets('dndnav', __('Navigation sidebar'), $this->widgets_nav, 'nav', WidgetsStack::$__default_widgets['nav'], $j);
        echo '</div>';

        # Extra sidebar
        echo
        '<div id="sidebarExtra" class="widgets fieldset">' .
        self::sidebarWidgets('dndextra', __('Extra sidebar'), $this->widgets_extra, 'extra', WidgetsStack::$__default_widgets['extra'], $j);
        echo '</div>';

        # Custom sidebar
        echo
        '<div id="sidebarCustom" class="widgets fieldset">' .
        self::sidebarWidgets('dndcustom', __('Custom sidebar'), $this->widgets_custom, 'custom', WidgetsStack::$__default_widgets['custom'], $j);
        echo '</div>';

        echo
        '<p id="sidebarsControl">' .
        dcCore()->formNonce() .
        '<input type="submit" name="wup" value="' . __('Update sidebars') . '" /> ' .
        '<input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" /> ' .
        '<input type="submit" class="reset" name="wreset" value="' . __('Reset sidebars') . '" />' .
        '</p>' .
        '</form>';
    }

    private function widgetsHead(): string
    {

        $widget_editor = dcCore()->auth->getOption('editor');
        $rte_flag      = true;
        $rte_flags     = @dcCore()->auth->user_prefs->interface->rte_flags;
        if (is_array($rte_flags) && isset($rte_flags['widgets_text'])) {
            $rte_flag = $rte_flags['widgets_text'];
        }
        $user_dm_nodragdrop = dcCore()->auth->user_prefs->accessibility->nodragdrop;

        return
        static::cssLoad('?mf=Plugin/Widgets/files/style.css') .
        static::jsLoad('js/jquery/jquery-ui.custom.js') .
        static::jsLoad('js/jquery/jquery.ui.touch-punch.js') .
        static::jsJson('widgets', [
            'widget_noeditor' => ($rte_flag ? 0 : 1),
            'msg'             => ['confirm_widgets_reset' => __('Are you sure you want to reset sidebars?')]
        ]) .
        static::jsLoad('?mf=Plugin/Widgets/files/js/widgets.js') .
        (!$user_dm_nodragdrop ? static::jsLoad('?mf=Plugin/Widgets/files/js/dragdrop.js') : '') .
        ($rte_flag ? (string) dcCore()->behaviors->call('adminPostEditor', $widget_editor['xhtml'], 'widget', ['#sidebarsWidgets textarea:not(.noeditor)'], 'xhtml') : '') .
        static::jsConfirmClose('sidebarsWidgets');
    }

    private function widgetsHelp(): stdClass
    {
        $widget_elements          = new stdClass;
        $widget_elements->content = '<dl>';
        foreach (WidgetsStack::$__widgets->elements() as $w) {
            $widget_elements->content .= '<dt><strong>' . html::escapeHTML($w->name()) . '</strong> (' .
            __('Widget ID:') . ' <strong>' . html::escapeHTML($w->id()) . '</strong>)' .
                ($w->desc() != '' ? ' <span class="form-note">' . __($w->desc()) . '</span>' : '') . '</dt>' .
                '<dd>';

            $w_settings = $w->settings();
            if (empty($w_settings)) {
                $widget_elements->content .= '<p>' . __('No setting for this widget') . '</p>';
            } else {
                $widget_elements->content .= '<ul>';
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

                    $widget_elements->content .= '<li>' .
                    __('Setting name:') . ' <strong>' . html::escapeHTML($n) . '</strong>' .
                        ' (' . $s_type . ')' .
                        '</li>';
                }
                $widget_elements->content .= '</ul>';
            }
            $widget_elements->content .= '</dd>';
        }
        $widget_elements->content .= '</dl></div>';

        return $widget_elements;
    }

    private static function sidebarWidgets($id, $title, $widgets, $pr, $default_widgets, &$j)
    {
        $res = '<h3>' . $title . '</h3>';

        if (!($widgets instanceof Widgets)) {
            $widgets = $default_widgets;
        }

        $res .= '<ul id="' . $id . '" class="connected">';

        $res .= '<li class="empty-widgets" ' . (!$widgets->isEmpty() ? 'style="display: none;"' : '') . '>' . __('No widget as far.') . '</li>';

        $i = 0;
        foreach ($widgets->elements() as $w) {
            $upDisabled   = $i == 0 ? ' disabled" src="images/disabled_' : '" src="images/';
            $downDisabled = $i == count($widgets->elements()) - 1 ? ' disabled" src="images/disabled_' : '" src="images/';
            $altUp        = $i == 0 ? ' alt=""' : ' alt="' . __('Up the widget') . '"';
            $altDown      = $i == count($widgets->elements()) - 1 ? ' alt=""' : ' alt="' . __('Down the widget') . '"';

            $iname = 'w[' . $pr . '][' . $i . ']';

            $res .= '<li>' . form::hidden([$iname . '[id]'], html::escapeHTML($w->id())) .
            '<p class="widget-name">' . form::number([$iname . '[order]'], [
                'default'    => $i,
                'class'      => 'hidden',
                'extra_html' => 'title="' . __('order') . '"'
            ]) .
            ' ' . $w->name() .
            ($w->desc() != '' ? ' <span class="form-note">' . __($w->desc()) . '</span>' : '') .
            '<span class="toolsWidget remove-if-drag">' .
            '<input type="image" class="upWidget' . $upDisabled . 'up.png" name="' . $iname . '[_up]" value="' . __('Up the widget') . '"' . $altUp . ' /> ' .
            '<input type="image" class="downWidget' . $downDisabled . 'down.png" name="' . $iname . '[_down]" value="' . __('Down the widget') . '"' . $altDown . ' /> ' . ' ' .
            '<input type="image" class="removeWidget" src="images/trash.png" name="' . $iname . '[_rem]" value="' . __('Remove widget') . '" alt="' . __('Remove the widget') . '" />' .
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

    private static function widgetsAppendCombo()
    {
        return [
            '-'              => 0,
            __('navigation') => 'nav',
            __('extra')      => 'extra',
            __('custom')     => 'custom'
        ];
    }

    private static function literalNullString($v)
    {
        if ($v == '') {
            return '&lt;' . __('empty string') . '&gt;';
        }

        return $v;
    }
}
