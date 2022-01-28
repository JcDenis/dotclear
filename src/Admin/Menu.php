<?php
/**
 * @class Dotclear\Admin\Menu
 * @brief Dotclear admin menu helper
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin;

use Dotclear\Core\Core;
use Dotclear\Core\Utils;

use Dotclear\File\Files;
use Dotclear\File\Path;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Menu
{
    private $id;
    protected $core;
    protected $itemSpace;
    protected $pinned;
    protected $items;
    protected $title;
    public static $iconset;

    /**
     * Constructs a new instance.
     *
     * @param   Core    $core       Core instance
     * @param   string  $id         The identifier
     * @param   string  $title      The title
     * @param   string  $itemSpace  The item space
     */
    public function __construct(Core $core, string $id, string $title, string $itemSpace = '')
    {
        $this->core      = $core;
        $this->id        = $id;
        $this->title     = $title;
        $this->itemSpace = $itemSpace;
        $this->pinned    = [];
        $this->items     = [];

        if (!self::$iconset) {
            self::$iconset   = (string) @$this->core->auth->user_prefs->interface->iconset;
        }
    }

    public static function setIconset(string $iconset): void
    {
        self::$iconset = $iconset;
    }

    /**
     * Adds an item.
     *
     * @param      string           $title   The title
     * @param      string           $url     The url
     * @param      string|array     $img     The image
     * @param      mixed            $active  The active flag
     * @param      bool             $show    The show flag
     * @param      mixed            $id      The identifier
     * @param      mixed            $class   The class
     * @param      bool             $pinned  The pinned flag
     */
    public function addItem($title, $url, $img, $active, $show = true, $id = null, $class = null, $pinned = false)
    {
        if ($show) {
            $item = $this->itemDef($title, $url, $img, $active, $id, $class);
            if ($pinned) {
                $this->pinned[] = $item;
            } else {
                $this->items[$title] = $item;
            }
        }
    }

    /**
     * Prepends an item.
     *
     * @param      string           $title   The title
     * @param      string           $url     The url
     * @param      string|array     $img     The image
     * @param      mixed            $active  The active flag
     * @param      bool             $show    The show flag
     * @param      mixed            $id      The identifier
     * @param      mixed            $class   The class
     * @param      bool             $pinned  The pinned flag
     */
    public function prependItem($title, $url, $img, $active, $show = true, $id = null, $class = null, $pinned = false)
    {
        if ($show) {
            $item = $this->itemDef($title, $url, $img, $active, $id, $class);
            if ($pinned) {
                array_unshift($this->pinned, $item);
            } else {
                $this->items[$title] = $item;
            }
        }
    }

    /**
     * Draw a menu
     *
     * @return     string  ( description_of_the_return_value )
     */
    public function draw()
    {
        if (count($this->items) + count($this->pinned) == 0) {
            return '';
        }

        $res = '<div id="' . $this->id . '">' .
            ($this->title ? '<h3>' . $this->title . '</h3>' : '') .
            '<ul>' . "\n";

        // 1. Display pinned items (unsorted)
        for ($i = 0; $i < count($this->pinned); $i++) {
            if ($i + 1 < count($this->pinned) && $this->itemSpace != '') {
                $res .= preg_replace('|</li>$|', $this->itemSpace . '</li>', $this->pinned[$i]);
                $res .= "\n";
            } else {
                $res .= $this->pinned[$i] . "\n";
            }
        }

        // 2. Display unpinned itmes (sorted)
        $i = 0;
        Utils::lexicalKeySort($this->items);
        foreach ($this->items as $title => $item) {
            if ($i + 1 < count($this->items) && $this->itemSpace != '') {
                $res .= preg_replace('|</li>$|', $this->itemSpace . '</li>', $item);
                $res .= "\n";
            } else {
                $res .= $item . "\n";
            }
            $i++;
        }

        $res .= '</ul></div>' . "\n";

        return $res;
    }

    /**
     * Get a menu item HTML code
     *
     * @param   string          $title   The title
     * @param   mixed           $url     The url
     * @param   string|array    $img     The image
     * @param   mixed           $active  The active flag
     * @param   mixed           $id      The identifier
     * @param   mixed            $class   The class
     *
     * @return  string
     */
    protected function itemDef($title, $url, $img, $active, $id = null, $class = null)
    {
        if (is_array($url)) {
            $link  = $url[0];
            $ahtml = (!empty($url[1])) ? ' ' . $url[1] : '';
        } else {
            $link  = $url;
            $ahtml = '';
        }

        return
            '<li' . (($active || $class) ? ' class="' . (($active) ? 'active ' : '') . (($class) ? $class : '') . '"' : '') .
            '>' . $this->getIconTheme($img) .
            '<a href="' . $link . '"' . $ahtml . '>' . $title . '</a></li>' . "\n";
    }

    /**
     * Compose HTML icon markup for favorites, menu, … depending on theme (light, dark)
     *
     * @param   mixed   $img        string (default) or array (0 : light, 1 : dark)
     * @param   bool    $fallback   use fallback image if none given
     * @param   string  $alt        alt attribute
     * @param   string  $title      title attribute
     *
     * @return  string
     */
    public function getIconTheme($img, $fallback = true, $alt = '', $title = '', $class = '')
    {
        $unknown_img = 'images/menu/no-icon.svg';
        $dark_img    = '';
        if (is_array($img)) {
            $light_img = $img[0] ?: ($fallback ? $unknown_img : '');   // Fallback to no icon if necessary
            if (isset($img[1]) && $img[1] !== '') {
                $dark_img = $img[1];
            }
        } else {
            $light_img = $img ?: ($fallback ? $unknown_img : '');  // Fallback to no icon if necessary
        }

        $title = $title !== '' ? ' title="' . $title . '"' : '';
        if ($light_img !== '' && $dark_img !== '') {
            $icon = '<img src="' . $this->getIconURL($light_img) .
            '" class="light-only' . ($class !== '' ? ' ' . $class : '') . '" alt="' . $alt . '"' . $title . ' />' .
                '<img src="' . $this->getIconURL($dark_img) .
            '" class="dark-only' . ($class !== '' ? ' ' . $class : '') . '" alt="' . $alt . '"' . $title . ' />';
        } elseif ($light_img !== '') {
            $icon = '<img src="' . $this->getIconURL($light_img) .
            '" class="' . ($class !== '' ? $class : '') . '" alt="' . $alt . '"' . $title . ' />';
        } else {
            $icon = '';
        }

        return $icon;
    }

    /**
     * Parse icon path and url from Iconset
     *
     * This can't call "Iconset" nor behaviors as modules are not loaded yet.
     * This use self::$iconset that content full path to iconset icons.
     *
     * @param   string  $img    Image path
     *
     * @return  string          New image path
     */
    public function getIconURL(string|array $img): string
    {
        $allow_types = ['svg', 'png', 'webp', 'jpg', 'jpeg', 'gif'];
        if (!empty(self::$iconset) && !empty($img)) {

            # Extract module name from path
            $split  = explode('/', self::$iconset);
            $module = array_pop($split);
            if ((preg_match('/^images\/menu\/(.+)(\..*)$/', $img, $m)) || (preg_match('/\?mf=(.+)(\..*)$/', $img, $m))) {
                $name = $m[1] ?? '';
                $ext  = $m[2] ?? '';
                if ($name !== '' && $ext !== '') {
                    $icon = Path::real(self::$iconset . '/files/' . $name . $ext, true);
                    if ($icon !== false) {
                        # Find same (name and extension)
                        if (is_file($icon) && is_readable($icon) && in_array(Files::getExtension($icon), $allow_types)) {
                            return '?mf=Iconset/' . $module . '/files/' . $name . $ext;
                        }
                    }
                    # Look for other extensions
                    foreach ($allow_types as $ext) {
                        $icon = Path::real(self::$iconset . '/files/' . $name . '.' . $ext, true);
                        if ($icon !== false) {
                            if (is_file($icon) && is_readable($icon)) {
                                return '?mf=Iconset/' . $module . '/files/' . $name . '.' . $ext;
                            }
                        }
                    }
                    # Not in iconset nor in Dotclear
                    $icon = Path::real(Core::path(DOTCLEAR_ROOT_DIR, 'Admin', 'files', $img));
                    if ($icon === false || !is_file($icon) || !is_readable($icon)) {
                        $img = 'images/menu/no-icon.svg';
                    }
                }
            }
        }

        # By default use Dotclear Admin files
        if (strpos($img, '?') === false) {
            $img = '?df=' . $img;
        }

        return $img;
    }
}
