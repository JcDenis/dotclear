<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Menu;

// Dotclear\Process\Admin\Menu\MenuGroup
use Dotclear\App;
use Dotclear\Helper\Lexical;

/**
 * Admin menu items helper.
 *
 * Accessible from App::core()->summary()->menu('a_menu_id')
 *
 * @ingroup  Admin
 */
class MenuGroup
{
    /**
     * @var array<int,string> $pinned
     *                        The pinned menu items
     */
    private $pinned = [];

    /**
     * @var array<string,string> $items
     *                           The items list
     */
    private $items  = [];

    /**
     * Constructor.
     *
     * @param string $id        The identifier
     * @param string $title     The title
     * @param string $itemSpace The item space
     */
    public function __construct(private string $id, private string $title, private string $itemSpace = '')
    {
    }

    /**
     * Adds an item.
     *
     * @param MenuItem $item The menu item
     */
    public function addItem(MenuItem $item): void
    {
        if ($item->show()) {
            if ($item->pinned()) {
                $this->pinned[] = $item->html();
            } else {
                $this->items[$item->title()] = $item->html();
            }
        }
    }

    /**
     * Prepends an item.
     *
     * @param MenuItem $item The menu item
     */
    public function prependItem(MenuItem $item): void
    {
        if ($item->show()) {
            if ($item->pinned()) {
                array_unshift($this->pinned, $item->html());
            } else {
                $this->items[$item->title()] = $item->html();
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
}
