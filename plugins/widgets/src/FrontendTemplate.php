<?php
/**
 * @package     Dotclear
 *
 * @copyright   Olivier Meunier & Association Dotclear
 * @copyright   GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\widgets;

use ArrayObject;
use SimpleXMLElement;

/**
 * @brief   The module frontend template.
 * @ingroup widgets
 */
class FrontendTemplate
{
    /**
     * tpl:Widgets [attributes] : Displays widgets (tpl value).
     *
     * attributes:
     *
     *      - type                Type of widgets (nav, extra, custom), default to all
     *      - disable             Comma separated list of widget to disable, default to empty
     *
     * @param   ArrayObject<string, mixed>     $attr   The attributes
     *
     * @return  string
     */
    public static function tplWidgets($attr)
    {
        $type = $attr['type'] ?? '';

        # widgets to disable
        $disable = isset($attr['disable']) ? trim((string) $attr['disable']) : '';

        if ($type === '') {
            $res = self::class . '::widgetsHandler(' . Widgets::class . "::WIDGETS_NAV,'" . addslashes($disable) . "');" . "\n" .
            '   ' . self::class . '::widgetsHandler(' . Widgets::class . "::WIDGETS_EXTRA,'" . addslashes($disable) . "');" . "\n" .
            '   ' . self::class . '::widgetsHandler(' . Widgets::class . "::WIDGETS_CUSTOM,'" . addslashes($disable) . "');" . "\n";
        } else {
            if (!in_array($type, [Widgets::WIDGETS_NAV, Widgets::WIDGETS_EXTRA, Widgets::WIDGETS_CUSTOM])) {
                $type = Widgets::WIDGETS_NAV;
            }
            $res = self::class . "::widgetsHandler('" . addslashes($type) . "','" . addslashes($disable) . "');";
        }

        return '<?php ' . $res . ' ?>';
    }

    public static function widgetsHandler(string $type, string $disable = ''): void
    {
        $wtype   = 'widgets_' . $type;
        $widgets = My::settings()->get($wtype);

        if (!$widgets) {
            // If widgets value is empty, get defaults
            $widgets = self::Widgets($type);
        } else {
            // Otherwise, load widgets
            $widgets = WidgetsStack::load($widgets);
        }

        if ($widgets->isEmpty()) {
            // Widgets are empty, don't show anything
            return;
        }

        $disable = preg_split('/\s*,\s*/', $disable, -1, PREG_SPLIT_NO_EMPTY);
        if ($disable !== false) {
            $disable = array_flip($disable);

            foreach ($widgets->elements() as $k => $w) {
                if (isset($disable[$w->id()])) {
                    continue;
                }
                echo $w->call($k);
            }
        }
    }

    /**
     * tpl:IfWidgets : Include content only if test succeed (tpl block).
     *
     * attributes:
     *
     *      - type                Type of widgets (nav, extra, custom), default to all
     *      - disable             Comma separated list of widget to disable, default to empty
     *
     * @param   ArrayObject<string, mixed>      $attr       The attributes
     * @param   string                          $content    The content
     *
     * @return  string
     */
    public static function tplIfWidgets(ArrayObject $attr, string $content): string
    {
        $type = $attr['type'] ?? '';

        # widgets to disable
        $disable = isset($attr['disable']) ? trim((string) $attr['disable']) : '';

        if ($type == '') {
            $res = self::class . '::ifWidgetsHandler(' . Widgets::class . "::WIDGETS_NAV,'" . addslashes($disable) . "') &&" . "\n" .
            '   ' . self::class . '::ifWidgetsHandler(' . Widgets::class . "::WIDGETS_EXTRA,'" . addslashes($disable) . "') &&" . "\n" .
            '   ' . self::class . '::ifWidgetsHandler(' . Widgets::class . "::WIDGETS_CUSTOM,'" . addslashes($disable) . "')" . "\n";
        } else {
            if (!in_array($type, [Widgets::WIDGETS_NAV, Widgets::WIDGETS_EXTRA, Widgets::WIDGETS_CUSTOM])) {
                $type = Widgets::WIDGETS_NAV;
            }
            $res = self::class . "::ifWidgetsHandler('" . addslashes($type) . "','" . addslashes($disable) . "')";
        }

        return '<?php if(' . $res . ') : ?>' . $content . '<?php endif; ?>';
    }

    /**
     * tplIfWidgets helper.
     *
     * @param   string  $type   The type
     *
     * @return  bool
     */
    public static function ifWidgetsHandler(string $type): bool
    {
        $wtype   = 'widgets_' . $type;
        $widgets = My::settings()->get($wtype);

        if (!$widgets) {
            // If widgets value is empty, get defaults
            $widgets = self::Widgets($type);
        } else {
            // Otherwise, load widgets
            $widgets = WidgetsStack::load($widgets);
        }

        return !$widgets->isEmpty();
    }

    /**
     * Get default widgets list helper.
     *
     * @param   string  $type    The type
     *
     * @return  WidgetsStack
     */
    private static function Widgets(string $type): WidgetsStack
    {
        return Widgets::$default_widgets[$type] ?? new WidgetsStack();
    }

    /**
     * tpl:Widget [attributes] : Includes a widget (tpl block).
     *
     * attributes:
     *
     *      - id      widget ID
     *
     * @param   ArrayObject<string, mixed>      $attr       The attributes
     * @param   string                          $content    The content (widget optional settings)
     *
     * @return  string
     */
    public static function tplWidget(ArrayObject $attr, string $content): string
    {
        if (!isset($attr['id']) || !(Widgets::$widgets->{$attr['id']} instanceof WidgetsElement)) {
            return '';
        }

        # We change tpl:lang syntax, we need it
        $content = (string) preg_replace('/\{\{tpl:lang\s+(.*?)\}\}/msu', '{tpl:lang $1}', $content);

        # We remove every {{tpl:
        $content = (string) preg_replace('/\{\{tpl:.*?\}\}/msu', '', $content);

        return
        '<?php ' . self::class . "::widgetHandler('" . addslashes($attr['id']) . "','" . str_replace("'", "\\'", $content) . "'); ?>";
    }

    /**
     * Render widget.
     *
     * @param   string  $id     The widget identifier
     * @param   string  $xml    The xml (widget optional settings)
     */
    public static function widgetHandler(string $id, $xml): void
    {
        if (!(Widgets::$widgets->{$id} instanceof WidgetsElement)) {
            return;
        }

        $xml = '<?xml version="1.0" encoding="utf-8" ?><widget>' . $xml . '</widget>';
        $xml = @simplexml_load_string($xml);
        if (!($xml instanceof SimpleXMLElement)) {
            echo 'Invalid widget XML fragment';

            return;
        }

        $widget = clone Widgets::$widgets->{$id};

        foreach ($xml->setting as $e) {
            if (empty($e['name'])) {
                continue;
            }

            $setting = (string) $e['name'];
            if ($e->count() > 0) {
                $text = preg_replace('#^<setting[^>]*>(.*)</setting>$#msu', '\1', (string) $e->asXML());
            } else {
                $text = $e;
            }
            $widget->{$setting} = preg_replace_callback('/\{tpl:lang (.*?)\}/msu', fn ($m) => __($m[1]), (string) $text);
        }

        echo $widget->call(0);
    }
}
