<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Widgets\Common;

// Dotclear\Plugin\Widgets\Common\Widgets
use Dotclear\Helper\Lexical;

/**
 * Widgets handler.
 *
 * @ingroup  Plugin Widgets
 */
class Widgets
{
    /**
     * @var array<string,Widget> $__widgets
     *                           Widgets
     */
    private $__widgets = [];

    /**
     * Load Widgets from serialized content.
     *
     * @param null|string $s serialized widgets
     */
    public function load(?string $s): ?Widgets
    {
        $o = @unserialize(base64_decode($s));

        return ($o instanceof self) ? $o : $this->loadArray($o, WidgetsStack::$__widgets);
    }

    /**
     * Store widgets in serialized content.
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
     * Create a Widget.
     *
     * @param string $id              Widget id
     * @param string $name            Widget name
     * @param mixed  $callback        Widget callback function
     * @param mixed  $append_callback Additonnal a callback
     * @param string $desc            Widget description
     */
    public function create(string $id, string $name, mixed $callback, mixed $append_callback = null, string $desc = ''): Widget
    {
        $this->__widgets[$id]                  = new Widget($id, $name, $callback, $desc);
        $this->__widgets[$id]->append_callback = $append_callback;

        return $this->__widgets[$id];
    }

    /**
     * Call additionnal callback function.
     *
     * @param null|Widget $widget The widget
     */
    public function append(?Widget $widget): void
    {
        if (null !== $widget) {
            if (is_callable($widget->append_callback)) {
                call_user_func($widget->append_callback, $widget);
            }
            $this->__widgets[] = $widget;
        }
    }

    /**
     * Check if there are widgets.
     */
    public function isEmpty(): bool
    {
        return count($this->__widgets) == 0;
    }

    /**
     * Get widgets.
     *
     * @param bool $sorted Sort widgets
     *
     * @return array The widgets
     */
    public function elements(bool $sorted = false): array
    {
        if ($sorted) {
            uasort($this->__widgets, [$this, 'sort']);
        }

        return $this->__widgets;
    }

    /**
     * Get a widget.
     *
     * @param string $id Widget id
     */
    public function get(string $id): ?Widget
    {
        return $this->__widgets[$id] ?? null;
    }

    /**
     * Magic wakeup method.
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
     * Load widgets (settings) form an array.
     *
     * @param array   $A       widgets array with settings
     * @param Widgets $widgets instanciated widgets
     */
    public function loadArray(array $A, Widgets $widgets): Widgets
    {
        uasort($A, [$this, 'arraySort']);

        $result = new Widgets();
        foreach ($A as $v) {
            if ($widgets->get($v['id']) != null) {
                $w = clone $widgets->get($v['id']);

                // Settings
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
     * Comparison of widgets.
     */
    private function arraySort(array $a, array $b): int
    {
        if ($a['order'] == $b['order']) {
            return 0;
        }

        return $a['order'] > $b['order'] ? 1 : -1;
    }

    /**
     * Lexical comparison of widgets.
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
