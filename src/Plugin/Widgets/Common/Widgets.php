<?php
/**
 * @class Dotclear\Plugin\Widgets\Common\Widgets
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

use Dotclear\Plugin\Widgets\Common\WidgetsStack;
use Dotclear\Plugin\Widgets\Common\WidgetsExt;
use Dotclear\Plugin\Widgets\Common\Widget;

use Dotclear\Core\Utils;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class Widgets
{
    private $__widgets = [];

    public function load($s)
    {
        $o = @unserialize(base64_decode($s));

        if ($o instanceof self) {
            return $o;
        }

        return $this->loadArray($o, WidgetsStack::$__widgets);
    }

    public function store()
    {
        $serialized = [];
        foreach ($this->__widgets as $pos => $w) {
            $serialized[] = ($w->serialize($pos));
        }

        return base64_encode(serialize($serialized));
    }

    public function create($id, $name, $callback, $append_callback = null, $desc = '')
    {
        $this->__widgets[$id]                  = new WidgetExt($id, $name, $callback, $desc);
        $this->__widgets[$id]->append_callback = $append_callback;

        return $this->__widgets[$id];
    }

    public function append($widget)
    {
        if ($widget instanceof Widget) {
            if (is_callable($widget->append_callback)) {
                call_user_func($widget->append_callback, $widget);
            }
            $this->__widgets[] = $widget;
        }
    }

    public function isEmpty()
    {
        return count($this->__widgets) == 0;
    }

    public function elements($sorted = false)
    {
        if ($sorted) {
            uasort($this->__widgets, ['self', 'sort']);
        }

        return $this->__widgets;
    }

    public function __get($id)
    {
        if (!isset($this->__widgets[$id])) {
            return;
        }

        return $this->__widgets[$id];
    }

    public function __wakeup()
    {
        foreach ($this->__widgets as $i => $w) {
            if (!($w instanceof Widget)) {
                unset($this->__widgets[$i]);
            }
        }
    }

    public function loadArray($A, $widgets)
    {
        if (!($widgets instanceof self)) {
            return false;
        }

        uasort($A, ['self', 'arraySort']);

        $result = new Widgets();
        foreach ($A as $v) {
            if ($widgets->{$v['id']} != null) {
                $w = clone $widgets->{$v['id']};

                # Settings
                unset($v['id'], $v['order']);

                foreach ($v as $sid => $s) {
                    $w->{$sid} = $s;
                }

                $result->append($w);
            }
        }

        return $result;
    }

    private static function arraySort($a, $b)
    {
        if ($a['order'] == $b['order']) {
            return 0;
        }

        return $a['order'] > $b['order'] ? 1 : -1;
    }

    private static function sort($a, $b)
    {
        $c = Utils::removeDiacritics(mb_strtolower($a->name()));
        $d = Utils::removeDiacritics(mb_strtolower($b->name()));
        if ($c == $d) {
            return 0;
        }

        return ($c < $d) ? -1 : 1;
    }
}
