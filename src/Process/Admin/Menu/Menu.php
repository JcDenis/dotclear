<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Menu;

// Dotclear\Process\Admin\Menu\Menu
use Dotclear\App;
use Dotclear\Helper\Lexical;

/**
 * Admin menu item helper.
 *
 * Accessible from App::core()->summary()->menu('a_menu_id')
 *
 * @ingroup  Admin
 */
class Menu
{
    /**
     * @var array<int,string> $pinned
     *                        The pinned menu items
     */
    protected $pinned = [];

    /**
     * @var array<string,string> $items
     *                           The items list
     */
    protected $items  = [];

    /**
     * Constructor.
     *
     * @param string $id        The identifier
     * @param string $title     The title
     * @param string $itemSpace The item space
     */
    public function __construct(private string $id, protected string $title, protected string $itemSpace = '')
    {
    }

    /**
     * Adds an item.
     *
     * @param string       $title  The title
     * @param array|string $url    The url
     * @param array|string $img    The image
     * @param bool         $active The active flag
     * @param bool         $show   The show flag
     * @param null|string  $id     The identifier
     * @param null|string  $class  The class
     * @param bool         $pinned The pinned flag
     */
    public function addItem(string $title, string|array $url, string|array $img, bool $active = false, bool $show = true, ?string $id = null, ?string $class = null, bool $pinned = false): void
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
     * @param string       $title  The title
     * @param array|string $url    The url
     * @param array|string $img    The image
     * @param bool         $active The active flag
     * @param bool         $show   The show flag
     * @param null|string  $id     The identifier
     * @param null|string  $class  The class
     * @param bool         $pinned The pinned flag
     */
    public function prependItem(string $title, string|array $url, string|array $img, bool $active = false, bool $show = true, ?string $id = null, ?string $class = null, bool $pinned = false): void
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
     * Draw a menu.
     *
     * @return string The forged menu
     */
    public function draw(): string
    {
        if (0 == count($this->items) + count($this->pinned)) {
            return '';
        }

        $res = '<div id="' . $this->id . '">' .
            ($this->title ? '<h3>' . $this->title . '</h3>' : '') .
            '<ul>' . "\n";

        // 1. Display pinned items (unsorted)
        for ($i = 0; count($this->pinned) > $i; ++$i) {
            if ($i + 1 < count($this->pinned) && '' != $this->itemSpace) {
                $res .= preg_replace('|</li>$|', $this->itemSpace . '</li>', $this->pinned[$i]);
                $res .= "\n";
            } else {
                $res .= $this->pinned[$i] . "\n";
            }
        }

        // 2. Display unpinned itmes (sorted)
        $i = 0;
        Lexical::lexicalKeySort($this->items);
        foreach ($this->items as $title => $item) {
            if ($i + 1 < count($this->items) && '' != $this->itemSpace) {
                $res .= preg_replace('|</li>$|', $this->itemSpace . '</li>', $item);
                $res .= "\n";
            } else {
                $res .= $item . "\n";
            }
            ++$i;
        }

        $res .= '</ul></div>' . "\n";

        return $res;
    }

    /**
     * Get a menu item HTML code.
     *
     * @param string       $title  The title
     * @param array|string $url    The url
     * @param array|string $img    The image
     * @param bool         $active The active flag
     * @param null|string  $id     The identifier
     * @param null|string  $class  The class
     *
     * @return string The forged menu item
     */
    protected function itemDef(string $title, string|array $url, string|array $img, bool $active = false, ?string $id = null, ?string $class = null): string
    {
        if (is_array($url)) {
            $link  = $url[0];
            $ahtml = (!empty($url[1])) ? ' ' . $url[1] : '';
        } else {
            $link  = $url;
            $ahtml = '';
        }

        return
            '<li' . (($active || $class) ? ' class="' . (($active) ? 'active ' : '') . (($class) ? $class : '') . '"' : '') . '>' .
            '<a href="' . $link . '"' . $ahtml . '>' . App::core()->summary()->getIconTheme($img) . $title . '</a>' .
            '</li>' . "\n";
    }
}
