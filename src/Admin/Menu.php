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

use Dotclear\Core\Utils;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Menu
{
    private $id;
    protected $itemSpace;
    protected $pinned;
    protected $items;
    protected $title;

    /**
     * Constructs a new instance.
     *
     * @param   string  $id         The identifier
     * @param   string  $title      The title
     * @param   string  $itemSpace  The item space
     */
    public function __construct(string $id, string $title, string $itemSpace = '')
    {
        $this->id        = $id;
        $this->title     = $title;
        $this->itemSpace = $itemSpace;
        $this->pinned    = [];
        $this->items     = [];
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
            '>' . dotclear()->menu->getIconTheme($img) .
            '<a href="' . $link . '"' . $ahtml . '>' . $title . '</a></li>' . "\n";
    }
}
