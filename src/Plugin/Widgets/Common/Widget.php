<?php
/**
 * @class Dotclear\Plugin\Widgets\Common\Widget
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginWidgets
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Widgets\Common;

use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Html\Form;

class Widget
{
    public $append_callback  = null;
    protected $settings      = [];

    public function serialize($order)
    {
        $values = [];
        foreach ($this->settings as $k => $v) {
            $values[$k] = $v['value'];
        }

        $values['id']    = $this->id;
        $values['order'] = $order;

        return $values;
    }

    public function __construct(private string $id, private string $name, private mixed $callback, private string $desc = '')
    {
    }

    public function id()
    {
        return $this->id;
    }

    public function name()
    {
        return $this->name;
    }

    public function desc()
    {
        return $this->desc;
    }

    public function getCallback()
    {
        return $this->callback;
    }

    public function call($i = 0)
    {
        if (is_callable($this->callback)) {
            return call_user_func($this->callback, $this, $i);
        }

        return '<p>Callback not found for widget ' . $this->id . '</p>';
    }

    /* Widget rendering tool
    --------------------------------------------------- */
    public function renderDiv($content_only, $class, $attr, $content)
    {
        if ($content_only) {
            return $content;
        }
        $ret = '<div class="widget' . ($class ? ' ' . Html::escapeHTML($class) : '') . '"' . ($attr ? ' ' . $attr : '') . '>' . "\n";
        $ret .= $content . "\n";
        $ret .= '</div>' . "\n";

        return $ret;
    }

    public function renderTitle($title)
    {
        if (!$title) {
            return '';
        }

        $theme = dotclear()->themes()->getModule((string) dotclear()->blog()->settings()->get('system')->get('theme'));
        if (!$theme) {
            return;
        }

        $wtscheme = $theme->options('widgettitleformat');
        if (empty($wtscheme)) {
            $tplset = $theme->templateset();
            if (empty($tplset) || $tplset == dotclear()->config()->get('template_default')) {
                // Use H2 for mustek based themes
                $wtscheme = '<h2>%s</h2>';
            } else {
                // Use H3 for dotty based themes
                $wtscheme = '<h3>%s</h3>';
            }
        }
        $ret = sprintf($wtscheme, $title);

        return $ret;
    }

    public function renderSubtitle($title, $render = true)
    {
        if (!$title && $render) {
            return '';
        }

        $theme = dotclear()->themes()->getModule((string) dotclear()->blog()->settings()->get('system')->get('theme'));
        if (!$theme) {
            return;
        }

        $wtscheme = $theme->options('widgetsubtitleformat');
        if (empty($wtscheme)) {
            $tplset = $theme->templateset();
            if (empty($tplset) || $tplset == dotclear()->config()->get('template_default')) {
                // Use H2 for mustek based themes
                $wtscheme = '<h3>%s</h3>';
            } else {
                // Use H3 for dotty based themes
                $wtscheme = '<h4>%s</h4>';
            }
        }
        if (!$render) {
            return $wtscheme;
        }

        $ret = sprintf($wtscheme, $title);

        return $ret;
    }

    /* Widget settings
    --------------------------------------------------- */
    public function get(string $n): mixed
    {
        return isset($this->settings[$n]) ? $this->settings[$n]['value'] : null;
    }

    public function __get(string $n): mixed
    {
        return $this->get($n);
    }

    public function set($n, $v)
    {
        if (isset($this->settings[$n])) {
            $this->settings[$n]['value'] = $v;
        }
    }

    public function __set($n, $v)
    {
        $this->set($n, $v);
    }

    public function setting($name, $title, $value, $type = 'text')
    {
        $types = [
            // type (string) => list of items may be provided (bool)
            'text'     => false,
            'textarea' => false,
            'check'    => false,
            'radio'    => true,
            'combo'    => true,
            'color'    => false,
            'email'    => false,
            'number'   => false
        ];

        if (!array_key_exists($type, $types)) {
            return false;
        }

        $index = 4; // 1st optional argument (after type)

        if ($types[$type] && func_num_args() > $index) {
            $options = func_get_arg($index);
            if (!is_array($options)) {
                return false;
            }
            $index++;
        }

        // If any, the last argument should be an array (key → value) of opts
        if (func_num_args() > $index) {
            $opts = func_get_arg($index);
        }

        $this->settings[$name] = [
            'title' => $title,
            'type'  => $type,
            'value' => $value
        ];

        if (isset($options)) {
            $this->settings[$name]['options'] = $options;
        }
        if (isset($opts)) {
            $this->settings[$name]['opts'] = $opts;
        }

        return $this;
    }

    public function settings()
    {
        return $this->settings;
    }

    public function formSettings($pr = '', &$i = 0)
    {
        $res = '';
        foreach ($this->settings as $id => $s) {
            $res .= $this->formSetting($id, $s, $pr, $i);
            $i++;
        }

        return $res;
    }

    public function formSetting($id, $s, $pr = '', &$i = 0)
    {
        $res   = '';
        $wfid  = 'wf-' . $i;
        $iname = $pr ? $pr . '[' . $id . ']' : $id;
        $class = (isset($s['opts']) && isset($s['opts']['class']) ? ' ' . $s['opts']['class'] : '');
        switch ($s['type']) {
            case 'text':
                $res .= '<p><label for="' . $wfid . '">' . $s['title'] . '</label> ' .
                form::field([$iname, $wfid], 20, 255, [
                    'default'    => Html::escapeHTML((string) $s['value']),
                    'class'      => 'maximal' . $class,
                    'extra_html' => 'lang="' . dotclear()->user()->getInfo('user_lang') . '" spellcheck="true"'
                ]) .
                '</p>';

                break;
            case 'textarea':
                $res .= '<p><label for="' . $wfid . '">' . $s['title'] . '</label> ' .
                form::textarea([$iname, $wfid], 30, 8, [
                    'default'    => Html::escapeHTML($s['value']),
                    'class'      => 'maximal' . $class,
                    'extra_html' => 'lang="' . dotclear()->user()->getInfo('user_lang') . '" spellcheck="true"'
                ]) .
                '</p>';

                break;
            case 'check':
                $res .= '<p>' . form::hidden([$iname], '0') .
                '<label class="classic" for="' . $wfid . '">' .
                form::checkbox([$iname, $wfid], '1', $s['value'], $class) . ' ' . $s['title'] .
                '</label></p>';

                break;
            case 'radio':
                $res .= '<p>' . ($s['title'] ? '<label class="classic">' . $s['title'] . '</label><br/>' : '');
                if (!empty($s['options'])) {
                    foreach ($s['options'] as $k => $v) {
                        $res .= $k > 0 ? '<br/>' : '';
                        $res .= '<label class="classic" for="' . $wfid . '-' . $k . '">' .
                        form::radio([$iname, $wfid . '-' . $k], $v[1], $s['value'] == $v[1], $class) . ' ' . $v[0] .
                            '</label>';
                    }
                }
                $res .= '</p>';

                break;
            case 'combo':
                $res .= '<p><label for="' . $wfid . '">' . $s['title'] . '</label> ' .
                form::combo([$iname, $wfid], $s['options'], $s['value'], $class) .
                '</p>';

                break;
            case 'color':
                $res .= '<p><label for="' . $wfid . '">' . $s['title'] . '</label> ' .
                form::color([$iname, $wfid], [
                    'default' => $s['value']
                ]) .
                '</p>';

                break;
            case 'email':
                $res .= '<p><label for="' . $wfid . '">' . $s['title'] . '</label> ' .
                form::email([$iname, $wfid], [
                    'default'      => Html::escapeHTML($s['value']),
                    'autocomplete' => 'email'
                ]) .
                '</p>';

                break;
            case 'number':
                $res .= '<p><label for="' . $wfid . '">' . $s['title'] . '</label> ' .
                form::number([$iname, $wfid], [
                    'default' => $s['value']
                ]) .
                '</p>';

                break;
        }

        return $res;
    }
}
