<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\SimpleMenu\Common;

// Dotclear\Plugin\SimpleMenu\Common\SimpleMenuWidgets
use ArrayObject;
use Dotclear\App;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Http;
use Dotclear\Plugin\Widgets\Common\Widget;
use Dotclear\Plugin\Widgets\Common\Widgets;
use Dotclear\Process\Public\Template\Engine\TplAttr;

/**
 * Widgets methods for plugin SmpleMenu.
 *
 * @ingroup  Plugin SimpleMenu Widgets
 */
class SimpleMenuWidgets
{
    // \cond
    // php tags break doxygen parser...
    private static $toff = ' ?>';
    private static $ton  = '<?php ';
    // \endcond

    public static $widgets;

    public function __construct()
    {
        App::core()->behavior('initWidgets')->add([$this, 'initWidgets']);
        if (App::core()->processed('Public')) {
            App::core()->template()->addValue('SimpleMenu', [$this, 'simpleMenu']);
        }
        self::$widgets = $this;
    }

    public function initWidgets(Widgets $widgets): void
    {
        $widgets
            ->create('simplemenu', __('Simple menu'), [$this, 'simpleMenuWidget'], null, 'List of simple menu items')
            ->addTitle(__('Menu'))
            ->setting(
                'description',
                __('Item description'),
                0,
                'combo',
                [
                    __('Displayed in link')                   => 0, // span
                    __('Used as link title')                  => 1, // title
                    __('Displayed in link and used as title') => 2, // both
                    __('Not displayed nor used')              => 3, // none
                ]
            )
            ->addHomeOnly()
            ->addContentOnly()
            ->addClass()
            ->addOffline()
        ;
    }

    // Template function
    public function simpleMenu(TplAttr $attr): string
    {
        if (!(bool) App::core()->blog()->settings()->getGroup('system')->getSetting('simpleMenu_active')) {
            return '';
        }

        $description = trim($attr->get('description'));

        if (!preg_match('#^(title|span|both|none)$#', $description)) {
            $description = '';
        }

        return self::$ton . 'echo ' . __CLASS__ . '::$widgets->displayMenu(' .
        "'" . addslashes(trim($attr->get('class'))) . "'," .
        "'" . addslashes(trim($attr->get('id'))) . "'," .
        "'" . addslashes($description) . "'" .
            ');' . self::$toff;
    }

    // Widget function
    public function simpleMenuWidget(Widget $widget): string
    {
        $descr_type = [0 => 'span', 1 => 'title', 2 => 'both', 3 => 'none'];

        if (!App::core()->blog()->settings()->getGroup('system')->getSetting('simpleMenu_active')
            || $widget->isOffline()
            || !$widget->checkHomeOnly()
        ) {
            return '';
        }

        $description = 'title';
        if (isset($descr_type[$widget->get('description')])) {
            $description = $descr_type[$widget->get('description')];
        }
        if ('' == ($menu = $this->displayMenu('', '', $description))) {
            return '';
        }

        return $widget->renderDiv(
            $widget->get('content_only'),
            'simple-menu ' . $widget->get('class'),
            '',
            $widget->renderTitle() . $menu
        );
    }

    public function displayMenu(string $class = '', string $id = '', string $description = ''): string
    {
        $ret = '';

        if (!(bool) App::core()->blog()->settings()->getGroup('system')->getSetting('simpleMenu_active')) {
            return $ret;
        }

        $menu = App::core()->blog()->settings()->getGroup('system')->getSetting('simpleMenu');
        if (is_array($menu)) {
            // Current relative URL
            $url     = $_SERVER['REQUEST_URI'];
            $abs_url = http::getHost() . $url;

            // Home recognition var
            $home_url       = html::stripHostURL(App::core()->blog()->url);
            $home_directory = dirname($home_url);
            if ('/' != $home_directory) {
                $home_directory = $home_directory . '/';
            }

            // Menu items loop
            foreach ($menu as $i => $m) {
                // $href = lien de l'item de menu
                $href = $m['url'];
                $href = html::escapeHTML($href);

                // Cope with request only URL (ie ?query_part)
                $href_part = '';
                if ('' != $href && substr($href, 0, 1) == '?') {
                    $href_part = substr($href, 1);
                }

                $targetBlank = ((isset($m['targetBlank'])) && ($m['targetBlank'])) ? true : false;

                // Active item test
                $active = false;
                if (($url == $href) || ($abs_url == $href) || ($_SERVER['URL_REQUEST_PART'] == $href) || (('' != $href_part) && ($_SERVER['URL_REQUEST_PART'] == $href_part)) || (('' == $_SERVER['URL_REQUEST_PART']) && (($href == $home_url) || ($href == $home_directory)))) {
                    $active = true;
                }
                $title = $span = '';

                if ($m['descr']) {
                    if (('title' == $description || 'both' == $description) && $targetBlank) {
                        $title = html::escapeHTML($m['descr']) . ' (' .
                        __('new window') . ')';
                    } elseif ('title' == $description || 'both' == $description) {
                        $title = html::escapeHTML($m['descr']);
                    }
                    if ('span' == $description || 'both' == $description) {
                        $span = ' <span class="simple-menu-descr">' . html::escapeHTML($m['descr']) . '</span>';
                    }
                }

                if (empty($title) && $targetBlank) {
                    $title = __('new window');
                }
                if ($active && !$targetBlank) {
                    $title = (empty($title) ? __('Active page') : $title . ' (' . __('active page') . ')');
                }

                $label = html::escapeHTML($m['label']);

                $item = new ArrayObject([
                    'url'    => $href,   // URL
                    'label'  => $label,  // <a> link label
                    'title'  => $title,  // <a> link title (optional)
                    'span'   => $span,   // description (will be displayed after <a> link)
                    'active' => $active, // status (true/false)
                    'class'  => '',      // additional <li> class (optional)
                ]);

                // --BEHAVIOR-- publicSimpleMenuItem
                App::core()->behavior('publicSimpleMenuItem')->call($i, $item);

                $ret .= '<li class="li' . ($i + 1) .
                    ($item['active'] ? ' active' : '') .
                    (0                == $i ? ' li-first' : '') .
                    (count($menu) - 1 == $i ? ' li-last' : '') .
                    ($item['class'] ? ' ' . $item['class'] : '') .
                    '">' .
                    '<a href="' . $href . '"' .
                    (!empty($item['title']) ? ' title="' . $label . ' - ' . $item['title'] . '"' : '') .
                    (($targetBlank) ? ' target="_blank" rel="noopener noreferrer"' : '') . '>' .
                    '<span class="simple-menu-label">' . $item['label'] . '</span>' .
                    $item['span'] . '</a>' .
                    '</li>';
            }
            // Final rendering
            if ($ret) {
                $ret = '<nav role="navigation"><ul ' . ($id ? 'id="' . $id . '"' : '') . ' class="simple-menu' . ($class ? ' ' . $class : '') . '">' . "\n" . $ret . "\n" . '</ul></nav>';
            }
        }

        return $ret;
    }
}
