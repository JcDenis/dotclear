<?php
/**
 * @class Dotclear\Plugin\SimpleMenu\Public\PublicWidgets
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginSimpleMenu
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\SimpleMenu\Public;

use ArrayObject;

use Dotclear\Core\Core;

use Dotclear\Html\Html;
use Dotclear\Network\Http;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class PublicWidgets
{
    protected $core;

    public function __construct(Core $core)
    {
        $this->core = $core;
    }

    # Template function
    public static function simpleMenu($attr)
    {
        if (!(boolean) $this->core->blog->settings->system->simpleMenu_active) {
            return '';
        }

        $class       = isset($attr['class']) ? trim($attr['class']) : '';
        $id          = isset($attr['id']) ? trim($attr['id']) : '';
        $description = isset($attr['description']) ? trim($attr['description']) : '';

        if (!preg_match('#^(title|span|both|none)$#', $description)) {
            $description = '';
        }

        return '<?php echo ' . __NAMESPACE__ .'\\Prepend::$widgets->displayMenu(' .
        "'" . addslashes($class) . "'," .
        "'" . addslashes($id) . "'," .
        "'" . addslashes($description) . "'" .
            '); ?>';
    }

    # Widget function
    public function simpleMenuWidget($w)
    {
        $descr_type = [0 => 'span', 1 => 'title', 2 => 'both', 3 => 'none'];

        if (!(boolean) $this->core->blog->settings->system->simpleMenu_active) {
            return;
        }

        if ($w->offline) {
            return;
        }

        if (($w->homeonly == 1 && !$this->core->url->isHome($core->url->type)) || ($w->homeonly == 2 && $this->core->url->isHome($this->core->url->type))) {
            return;
        }

        $description = 'title';
        if (isset($descr_type[$w->description])) {
            $description = $descr_type[$w->description];
        }
        $menu = $this->displayMenu('', '', $description);
        if ($menu == '') {
            return;
        }

        return $w->renderDiv($w->content_only, 'simple-menu ' . $w->class, '',
            ($w->title ? $w->renderTitle(html::escapeHTML($w->title)) : '') . $menu);
    }

    public function displayMenu($class = '', $id = '', $description = '')
    {
        $ret = '';

        if (!(boolean) $this->core->blog->settings->system->simpleMenu_active) {
            return $ret;
        }

        $menu = $this->core->blog->settings->system->simpleMenu;
        if (is_array($menu)) {
            // Current relative URL
            $url     = $_SERVER['REQUEST_URI'];
            $abs_url = http::getHost() . $url;

            // Home recognition var
            $home_url       = html::stripHostURL($this->core->blog->url);
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
                $this->core->behaviors->call('publicSimpleMenuItem', $i, $item);

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
