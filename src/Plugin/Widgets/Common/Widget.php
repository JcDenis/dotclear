<?php
/**
 * @note Dotclear\Plugin\Widgets\Common\Widget
 * @brief Dotclear Plugins class
 *
 * @ingroup  PluginWidgets
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
    /** Widget displayed on every page */
    public const ALL_PAGES = 0;

    /** Widget displayed on home page only */
    public const HOME_ONLY = 1;

    /** Widget displayed on every page but home page */
    public const EXCEPT_HOME = 2;

    /** @var mixed Widget callback function */
    public $append_callback;

    /** @var array Widget settings */
    protected $settings = [];

    /**
     * Serialize widget settings.
     *
     * @param int|string $order Widget order
     *
     * @return array Serialized widget settngs
     */
    public function serialize(string|int $order): array
    {
        $values = [];
        foreach ($this->settings as $k => $v) {
            $values[$k] = $v['value'];
        }

        $values['id']    = $this->id;
        $values['order'] = (int) $order;

        return $values;
    }

    /**
     * Constructor.
     *
     * @param string $id       Widget id
     * @param string $name     Widget name
     * @param mixed  $callback Callbackk function
     * @param string $desc     Widget description
     */
    public function __construct(private string $id, private string $name, private mixed $callback, private string $desc = '')
    {
    }

    /**
     * Get widget id.
     *
     * @return string Widget id
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * Get widget name.
     *
     * @return string Widget name
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Get widget description.
     *
     * @return string Widget description
     */
    public function desc(): string
    {
        return $this->desc;
    }

    /**
     * Get widget callback.
     *
     * @return string Widget callback
     */
    public function getCallback(): mixed
    {
        return $this->callback;
    }

    /**
     * Call widget callback funcion.
     *
     * @param int|string $i Widget id or index
     *
     * @return string Widget callback  result
     */
    public function call(int|string $i = 0): string
    {
        if (is_callable($this->callback)) {
            return call_user_func($this->callback, $this, $i);
        }

        return '<p>Callback not found for widget ' . $this->id . '</p>';
    }

    // / @name Widget settings helpers
    // @{
    /**
     * Add form title setting.
     *
     * @param string $title The title
     */
    public function addTitle(string $title = ''): Widget
    {
        return $this->setting('title', __('Title (optional)') . ' :', $title);
    }

    /**
     * Add form home only setting.
     */
    public function addHomeOnly(): Widget
    {
        return $this->setting(
            'homeonly',
            __('Display on:'),
            self::ALL_PAGES,
            'combo',
            [__('All pages') => self::ALL_PAGES, __('Home page only') => self::HOME_ONLY, __('Except on home page') => self::EXCEPT_HOME]
        );
    }

    /**
     * Check if widget is home only.
     *
     * @param string     $type         Current page type
     * @param int|string $alt_not_home Not on home page
     * @param int|string $alt_home     Only home page
     */
    public function checkHomeOnly(string $type, string|int $alt_not_home = 1, string|int $alt_home = 0): bool
    {
        return !(
            $this->get('homeonly')    == self::HOME_ONLY      && !dotclear()->url()->isHome($type)      && $alt_not_home
            || $this->get('homeonly') == self::EXCEPT_HOME && (dotclear()->url()->isHome($type) || $alt_home)
        );
    }

    /**
     * Add form content only.
     *
     * @param int $content_only Show only widget content (without title)
     */
    public function addContentOnly(int $content_only = 0): Widget
    {
        return $this->setting('content_only', __('Content only'), $content_only, 'check');
    }

    /**
     * Add form class.
     *
     * @param string $class The class
     */
    public function addClass(string $class = ''): Widget
    {
        return $this->setting('class', __('CSS class:'), $class);
    }

    /**
     * Add form offline.
     *
     * @param int|string $offline Set offline
     */
    public function addOffline(string|int $offline = 0): Widget
    {
        return $this->setting('offline', __('Offline'), $offline, 'check');
    }

    /**
     * Check if widget is offline.
     */
    public function isOffline(): bool
    {
        return (bool) $this->settings['offline']['value'];
    }
    // @}

    // / @name Widget rendering tool
    // @{
    /**
     * Render complete widget.
     *
     * @param int|string $content_only Render only content
     * @param string     $class        div class
     * @param string     $attr         div attributes
     * @param string     $content      The content
     */
    public function renderDiv(string|int $content_only, string $class, string $attr, string $content): string
    {
        if ($content_only) {
            return $content;
        }
        $ret = '<div class="widget' . ($class ? ' ' . Html::escapeHTML($class) : '') . '"' . ($attr ? ' ' . $attr : '') . '>' . "\n";
        $ret .= $content . "\n";
        $ret .= '</div>' . "\n";

        return $ret;
    }

    /**
     * Render widget title.
     *
     * @param null|string $title  The custom title (or null for settings title)
     * @param bool        $escape HTML escape title
     */
    public function renderTitle(?string $title = null, bool $escape = true): string
    {
        if (null === $title) {
            $title = $this->get('title');
        }

        if (!$title) {
            return '';
        }

        $theme = dotclear()->themes()->getModule((string) dotclear()->blog()->settings()->get('system')->get('theme'));
        if (!$theme) {
            return '';
        }

        $wtscheme = $theme->options('widgettitleformat');
        if (empty($wtscheme)) {
            $tplset = $theme->templateset();
            if (empty($tplset) || dotclear()->config()->get('template_default') == $tplset) {
                // Use H2 for mustek based themes
                $wtscheme = '<h2>%s</h2>';
            } else {
                // Use H3 for dotty based themes
                $wtscheme = '<h3>%s</h3>';
            }
        }

        return sprintf($wtscheme, $escape ? Html::escapeHTML($title) : $title);
    }

    /**
     * Render widget subtitle.
     *
     * @param string $title  The subtitle
     * @param bool   $render If false, return subtitle scheme
     */
    public function renderSubtitle(string $title, bool $render = true): string
    {
        if (!$title && $render) {
            return '';
        }

        $theme = dotclear()->themes()->getModule((string) dotclear()->blog()->settings()->get('system')->get('theme'));
        if (!$theme) {
            return '';
        }

        $wtscheme = $theme->options('widgetsubtitleformat');
        if (empty($wtscheme)) {
            $tplset = $theme->templateset();
            if (empty($tplset) || dotclear()->config()->get('template_default') == $tplset) {
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

        return sprintf($wtscheme, $title);
    }
    // @}

    // / @name Widget settings
    // @{
    /**
     * Get a widget setting value.
     *
     * @param string $setting The setting name
     *
     * @return mixed The setting value
     */
    public function get(string $setting): mixed
    {
        return isset($this->settings[$setting]) ? $this->settings[$setting]['value'] : null;
    }

    /**
     * Set a widget setting value.
     *
     * @param string $setting The setting name
     * @param mixed  $value   The setting value
     */
    public function set(string $setting, mixed $value): void
    {
        if (isset($this->settings[$setting])) {
            $this->settings[$setting]['value'] = $value;
        }
    }

    /**
     * Set a widget setting.
     *
     * @param string $name  The setting name
     * @param string $title The setting title
     * @param mixed  $value The setting value
     * @param string $type  The setting type
     */
    public function setting(string $name, string $title, mixed $value, string $type = 'text'): Widget|false
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
            'number'   => false,
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
            ++$index;
        }

        // If any, the last argument should be an array (key → value) of opts
        if (func_num_args() > $index) {
            $opts = func_get_arg($index);
        }

        $this->settings[$name] = [
            'title' => $title,
            'type'  => $type,
            'value' => $value,
        ];

        if (isset($options)) {
            $this->settings[$name]['options'] = $options;
        }
        if (isset($opts)) {
            $this->settings[$name]['opts'] = $opts;
        }

        return $this;
    }

    /**
     * Get widget settings.
     */
    public function settings(): array
    {
        return $this->settings;
    }

    /**
     * Get HTML form of settings.
     *
     * @param string $pr Form prefix
     * @param int    $i  Form index
     */
    public function formSettings(string $pr = '', int &$i = 0): string
    {
        $res = '';
        foreach ($this->settings as $id => $s) {
            $res .= $this->formSetting($id, $s, $pr, $i);
            ++$i;
        }

        return $res;
    }

    /**
     * Get HTML form of a setting.
     *
     * @param string $id Setting id
     * @param array  $s  Setting properties
     * @param string $pr Form prefix
     * @param int    $i  Form index
     */
    public function formSetting(string $id, array $s, string $pr = '', int &$i = 0): string
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
                    'extra_html' => 'lang="' . dotclear()->user()->getInfo('user_lang') . '" spellcheck="true"',
                ]) .
                '</p>';

                break;

            case 'textarea':
                $res .= '<p><label for="' . $wfid . '">' . $s['title'] . '</label> ' .
                form::textarea([$iname, $wfid], 30, 8, [
                    'default'    => Html::escapeHTML($s['value']),
                    'class'      => 'maximal' . $class,
                    'extra_html' => 'lang="' . dotclear()->user()->getInfo('user_lang') . '" spellcheck="true"',
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
                        $res .= 0 < $k ? '<br/>' : '';
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
                    'default' => $s['value'],
                ]) .
                '</p>';

                break;

            case 'email':
                $res .= '<p><label for="' . $wfid . '">' . $s['title'] . '</label> ' .
                form::email([$iname, $wfid], [
                    'default'      => Html::escapeHTML($s['value']),
                    'autocomplete' => 'email',
                ]) .
                '</p>';

                break;

            case 'number':
                $res .= '<p><label for="' . $wfid . '">' . $s['title'] . '</label> ' .
                form::number([$iname, $wfid], [
                    'default' => $s['value'],
                ]) .
                '</p>';

                break;
        }

        return $res;
    }
    // @}
}
