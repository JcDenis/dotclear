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

use Dotclear\Helper\Lexical;
use Dotclear\Plugin\Widgets\Common\WidgetsStack;
use Dotclear\Plugin\Widgets\Common\Widget;

class Widgets
{
    /** @var    array<string, Widget>   Widgets */
    private $__widgets = [];

    /**
     * Load Widgets from serialized content
     * 
     * @param   string|null     $s  serialized widgets
     * 
     * @return  Widgets|null
     */
    public function load(?string $s): ?Widgets
    {
        $o = @unserialize(base64_decode($s));

        return ($o instanceof self) ? $o : $this->loadArray($o, WidgetsStack::$__widgets);
    }

    /**
     * Store widgets in serialized content
     * 
     * @return  string
     */
    public function store(): string
    {
        $serialized = [];
        foreach ($this->__widgets as $pos => $w) {
            $serialized[] = ($w->serialize($pos));
        }

        return base64_encode(serialize($serialized));
    }

    /**
     * Create a Widget
     * 
     * @param   string  $id                 Widget id
     * @param   string  $name               Widget name
     * @param   mixed   $callback           Widget callback function
     * @param   mixed   $append_callback    Additonnal a callback
     * @param   string  $desc               Widget description
     * 
     * @return  Widget
     */
    public function create(string $id, string $name, mixed $callback, mixed $append_callback = null, string $desc = ''): Widget
    {
        $this->__widgets[$id]                  = new Widget($id, $name, $callback, $desc);
        $this->__widgets[$id]->append_callback = $append_callback;

        return $this->__widgets[$id];
    }

    /**
     * Call additionnal callback function
     * 
     * @param   Widget  $widget     The widget
     */
    public function append(Widget $widget): void
    {
        if (is_callable($widget->append_callback)) {
            call_user_func($widget->append_callback, $widget);
        }
            $this->__widgets[] = $widget;
    }

    /**
     * Check if there are widgets
     * 
     * @return  bool
     */
    public function isEmpty(): bool
    {
        return count($this->__widgets) == 0;
    }

    /**
     * Get widgets
     * 
     * @param   bool    $sorted     Sort widgets
     * 
     * @return  array   The widgets
     */
    public function elements(bool $sorted = false): array
    {
        if ($sorted) {
            uasort($this->__widgets, [$this, 'sort']);
        }

        return $this->__widgets;
    }

    /**
     * Get a widget
     * 
     * @param   string  $id     Widget id
     * 
     * @return Widget|null
     */
    public function get(string $id): ?Widget
    {
        return isset($this->__widgets[$id]) ? $this->__widgets[$id] : null;
    }

    /**
     * Magic wakeup method
     */
    public function __wakeup(): void
    {
        foreach ($this->__widgets as $i => $w) {
            if (!($w instanceof Widget)) {
                unset($this->__widgets[$i]);
            }
        }
    }

    /**
     * Load widgets (settings) form an array
     * 
     * @param   array       $A          widgets array with settings
     * @param   Widgets     $widgets    instanciated widgets
     * 
     * @return  Widgets
     */
    public function loadArray(array $A, Widgets $widgets): Widgets
    {
        uasort($A, [$this, 'arraySort']);

        $result = new Widgets();
        foreach ($A as $v) {
            if ($widgets->get($v['id']) != null) {
                $w = clone $widgets->get($v['id']);

                # Settings
                unset($v['id'], $v['order']);

                foreach ($v as $sid => $s) {
                    $w->set($sid, $s);
                }

                $result->append($w);
            }
        }

        return $result;
    }

    /**
     * Comparison of widgets
     * 
     * @param   array   $a
     * @param   array   $b
     * 
     * @return  int
     */
    private function arraySort(array $a, array $b): int
    {
        if ($a['order'] == $b['order']) {
            return 0;
        }

        return $a['order'] > $b['order'] ? 1 : -1;
    }


    /**
     * Lexical comparison of widgets
     * 
     * @param   Widget  $a
     * @param   Widget  $b
     * 
     * @return  int
     */
    private function sort(Widget $a, Widget $b): int
    {
        $c = Lexical::removeDiacritics(mb_strtolower($a->name()));
        $d = Lexical::removeDiacritics(mb_strtolower($b->name()));
        if ($c == $d) {
            return 0;
        }

        return ($c < $d) ? -1 : 1;
    }
}
