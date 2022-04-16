<?php
/**
 * @class Dotclear\Plugin\SimpleMenu\Common\SimpleMenuWidgets
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginSimpleMenu
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\SimpleMenu\Common;

use ArrayObject;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Http;
use Dotclear\Plugin\Widgets\Common\Widget;
use Dotclear\Plugin\Widgets\Common\Widgets;

class SimpleMenuWidgets
{
    public static $widgets;

    public function __construct()
    {
        dotclear()->behavior()->add('initWidgets', [$this, 'initWidgets']);
        if (dotclear()->processed('Public')) {
            dotclear()->template()->addValue('SimpleMenu', [$this, 'simpleMenu']);
        }
        self::$widgets = $this;
    }

    public function initWidgets(Widgets $w): void
    {
        $w
            ->create('simplemenu', __('Simple menu'), [$this, 'simpleMenuWidget'], null, 'List of simple menu items')
            ->addTitle(__('Menu'))
            ->setting('description', __('Item description'), 0, 'combo',
                [
                    __('Displayed in link')                   => 0, // span
                    __('Used as link title')                  => 1, // title
                    __('Displayed in link and used as title') => 2, // both
                    __('Not displayed nor used')              => 3 // none
                ])
            ->addHomeOnly()
            ->addContentOnly()
            ->addClass()
            ->addOffline();
    }

    # Template function
    public function simpleMenu(ArrayObject $attr): string
    {
        if (!(bool) dotclear()->blog()->settings()->get('system')->get('simpleMenu_active')) {
            return '';
        }

        $class       = isset($attr['class']) ? trim($attr['class']) : '';
        $id          = isset($attr['id']) ? trim($attr['id']) : '';
        $description = isset($attr['description']) ? trim($attr['description']) : '';

        if (!preg_match('#^(title|span|both|none)$#', $description)) {
            $description = '';
        }

        return '<?php echo ' . __CLASS__ . '::$widgets->displayMenu(' .
        "'" . addslashes($class) . "'," .
        "'" . addslashes($id) . "'," .
        "'" . addslashes($description) . "'" .
            '); ?>';
    }

    # Widget function
    public function simpleMenuWidget(Widget $widget): string
    {
        $descr_type = [0 => 'span', 1 => 'title', 2 => 'both', 3 => 'none'];

        if (!dotclear()->blog()->settings()->get('system')->get('simpleMenu_active')
            || $widget->get('offline') 
            || !$widget->checkHomeOnly(dotclear()->url()->type)
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

        if (!(bool) dotclear()->blog()->settings()->get('system')->get('simpleMenu_active')) {
            return $ret;
        }

        $menu = dotclear()->blog()->settings()->get('system')->get('simpleMenu');
        if (is_array($menu)) {
            // Current relative URL
            $url     = $_SERVER['REQUEST_URI'];
            $abs_url = http::getHost() . $url;

            // Home recognition var
            $home_url       = html::stripHostURL(dotclear()->blog()->url);
            $home_directory = dirname($home_url);
            if ($home_directory != '/') {
                $home_directory = $home_directory . '/';
            }

            // Menu items loop
            foreach ($menu as $i => $m) {
                # $href = lien de l'item de menu
                $href = $m['url'];
                $href = html::escapeHTML($href);

                # Cope with request only URL (ie ?query_part)
                $href_part = '';
                if ($href != '' && substr($href, 0, 1) == '?') {
                    $href_part = substr($href, 1);
                }

                $targetBlank = ((isset($m['targetBlank'])) && ($m['targetBlank'])) ? true : false;

                # Active item test
                $active = false;
                if (($url == $href) || ($abs_url == $href) || ($_SERVER['URL_REQUEST_PART'] == $href) || (($href_part != '') && ($_SERVER['URL_REQUEST_PART'] == $href_part)) || (($_SERVER['URL_REQUEST_PART'] == '') && (($href == $home_url) || ($href == $home_directory)))) {
                    $active = true;
                }
                $title = $span = '';

                if ($m['descr']) {
                    if (($description == 'title' || $description == 'both') && $targetBlank) {
                        $title = html::escapeHTML($m['descr']) . ' (' .
                        __('new window') . ')';
                    } elseif ($description == 'title' || $description == 'both') {
                        $title = html::escapeHTML($m['descr']);
                    }
                    if ($description == 'span' || $description == 'both') {
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
                    'class'  => ''      // additional <li> class (optional)
                ]);

                # --BEHAVIOR-- publicSimpleMenuItem
                dotclear()->behavior()->call('publicSimpleMenuItem', $i, $item);

                $ret .= '<li class="li' . ($i + 1) .
                    ($item['active'] ? ' active' : '') .
                    ($i == 0 ? ' li-first' : '') .
                    ($i == count($menu) - 1 ? ' li-last' : '') .
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
