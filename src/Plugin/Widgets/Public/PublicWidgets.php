<?php
/**
 * @class Dotclear\Plugin\Widgets\Public\Prepend
 * @brief Dotclear Plugin class
 *
 * @package Dotclear
 * @subpackage PluginWidgets
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Widgets\Public;

use SimpleXMLElement;

use Dotclear\Plugin\Widgets\Lib\Widget;
use Dotclear\Plugin\Widgets\Lib\Widgets;
use Dotclear\Plugin\Widgets\Lib\WidgetsStack;

use Dotclear\Core\StaticCore;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class PublicWidgets
{
    use StaticCore;

    private static function wns()
    {
        return __NAMESPACE__ . "\\PublicWidgets::";
    }

    public static function tplWidgets($attr)
    {
        $type = $attr['type'] ?? '';

        # widgets to disable
        $disable = isset($attr['disable']) ? trim($attr['disable']) : '';

        if ($type == '') {
            $res = self::wns() . "widgetsHandler('nav','" . addslashes($disable) . "');" . "\n" .
            "   " . self::wns() . "widgetsHandler('extra','" . addslashes($disable) . "');" . "\n" .
            "   " . self::wns() . "widgetsHandler('custom','" . addslashes($disable) . "');" . "\n";
        } else {
            if (!in_array($type, ['nav', 'extra', 'custom'])) {
                $type = 'nav';
            }
            $res = self::wns() . "widgetsHandler('" . addslashes($type) . "','" . addslashes($disable) . "');";
        }

        return '<?php ' . $res . ' ?>';
    }

    public static function widgetsHandler($type, $disable = '')
    {
        $core = static::getCore();

        $wtype = 'widgets_' . $type;
        $core->blog->settings->addNameSpace('widgets');
        $widgets = $core->blog->settings->widgets->{$wtype};

        if (!$widgets) {
            // If widgets value is empty, get defaults
            $widgets = self::defaultWidgets($type);
        } else {
            // Otherwise, load widgets
            $widgets = Widgets::load($widgets);
        }

        if ($widgets->isEmpty()) {
            // Widgets are empty, don't show anything
            return;
        }

        $disable = preg_split('/\s*,\s*/', $disable, -1, PREG_SPLIT_NO_EMPTY);
        $disable = array_flip($disable);

        foreach ($widgets->elements() as $k => $w) {
            if (isset($disable[$w->id()])) {
                continue;
            }
            echo $w->call($k);
        }
    }

    public static function tplIfWidgets($attr, $content)
    {
        $type = $attr['type'] ?? '';

        # widgets to disable
        $disable = isset($attr['disable']) ? trim($attr['disable']) : '';

        if ($type == '') {
            $res = self::wns() . "ifWidgetsHandler('nav','" . addslashes($disable) . "') &&" . "\n" .
            "   " . self::wns() . "ifWidgetsHandler('extra','" . addslashes($disable) . "') &&" . "\n" .
            "   " . self::wns() . "ifWidgetsHandler('custom','" . addslashes($disable) . "')" . "\n";
        } else {
            if (!in_array($type, ['nav', 'extra', 'custom'])) {
                $type = 'nav';
            }
            $res = self::wns() . "ifWidgetsHandler('" . addslashes($type) . "','" . addslashes($disable) . "')";
        }

        return '<?php if(' . $res . ') : ?>' . $content . '<?php endif; ?>';
    }

    public static function ifWidgetsHandler($type, $disable = '')
    {
        $core = static::getCore();

        $wtype = 'widgets_' . $type;
        $core->blog->settings->addNameSpace('widgets');
        $widgets = $core->blog->settings->widgets->{$wtype};

        if (!$widgets) {
            // If widgets value is empty, get defaults
            $widgets = self::defaultWidgets($type);
        } else {
            // Otherwise, load widgets
            $widgets = Widgets::load($widgets);
        }

        return (!$widgets->isEmpty());
    }

    private static function defaultWidgets($type)
    {
        $widgets = new Widgets();
        $w       = new Widgets();

        if (isset(WidgetsStack::$__default_widgets[$type])) {
            $w = WidgetsStack::$__default_widgets[$type];
        }

        return $w;
    }

    public static function tplWidget($attr, $content)
    {
        if (!isset($attr['id']) || !(WidgetsStack::$__widgets->{$attr['id']} instanceof Widget)) {
            return;
        }

        # We change tpl:lang syntax, we need it
        $content = preg_replace('/\{\{tpl:lang\s+(.*?)\}\}/msu', '{tpl:lang $1}', $content);

        # We remove every {{tpl:
        $content = preg_replace('/\{\{tpl:.*?\}\}/msu', '', $content);

        return
        "<?php " . self::wns() . "widgetHandler('" . addslashes($attr['id']) . "','" . str_replace("'", "\\'", $content) . "'); ?>";
    }

    public static function widgetHandler($id, $xml)
    {
        $widgets = WidgetsStack::$__widgets;

        if (!($widgets->{$id} instanceof Widget)) {
            return;
        }

        $xml = '<?xml version="1.0" encoding="utf-8" ?><widget>' . $xml . '</widget>';
        $xml = @simplexml_load_string($xml);
        if (!($xml instanceof SimpleXMLElement)) {
            echo 'Invalid widget XML fragment';

            return;
        }

        $w = clone $widgets->{$id};

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
            $w->{$setting} = preg_replace_callback('/\{tpl:lang (.*?)\}/msu', ['self', 'widgetL10nHandler'], $text);
        }

        echo $w->call(0);
    }

    private static function widgetL10nHandler($m)
    {
        return __($m[1]);
    }
}
