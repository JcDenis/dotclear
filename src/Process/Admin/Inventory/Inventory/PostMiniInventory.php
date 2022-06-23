<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Inventory\Inventory;

// Dotclear\Process\Admin\Inventory\Inventory\PostMiniInventory
use Dotclear\App;
use Dotclear\Helper\Clock;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Mapper\NamedStrings;
use Dotclear\Process\Admin\Inventory\Inventory;
use Dotclear\Process\Admin\Page\Pager;

/**
 * Admin posts mini list helper.
 *
 * @ingroup  Admin Post Inventory
 */
class PostMiniInventory extends Inventory
{
    /**
     * Display a mini post list.
     *
     * @param int    $page          The page
     * @param int    $nb_per_page   The number of per page
     * @param string $enclose_block The enclose block
     */
    public function display(int $page, int $nb_per_page, string $enclose_block = ''): void
    {
        if ($this->rs->isEmpty()) {
            echo '<p><strong>' . __('No entry') . '</strong></p>';
        } else {
            $pager = new Pager($page, $this->rs_count, $nb_per_page, 10);

            $html_block = '<div class="table-outer clear">' .
            '<table><caption class="hidden">' . __('Entries list') . '</caption><tr>';

            $cols = new NamedStrings([
                'title'  => '<th scope="col">' . __('Title') . '</th>',
                'date'   => '<th scope="col">' . __('Date') . '</th>',
                'author' => '<th scope="col">' . __('Author') . '</th>',
                'status' => '<th scope="col">' . __('Status') . '</th>',
            ]);

            App::core()->behavior('adminPostMiniListHeader')->call($this->rs, $cols);

            App::core()->listoption()->column()->cleanColumns(id: 'posts', columns: $cols);

            $html_block .= '<tr>' . implode($cols->dump()) . '</tr>%s</table></div>';
            if ($enclose_block) {
                $html_block = sprintf($enclose_block, $html_block);
            }

            $blocks = explode('%s', $html_block);

            echo $pager->getLinks() . $blocks[0];

            while ($this->rs->fetch()) {
                echo $this->postLine();
            }

            echo $blocks[1] . $pager->getLinks();
        }
    }

    /**
     * Get a line.
     *
     * @return string The line
     */
    private function postLine(): string
    {
        $img = '<img alt="%1$s" title="%1$s" src="?df=images/%2$s" />';

        $img_status = sprintf(
            $img,
            App::core()->blog()->posts()->status()->getState($this->rs->integer('post_status')),
            App::core()->blog()->posts()->status()->getIcon($this->rs->integer('post_status')),
        );
        $sts_class = 'sts-' . App::core()->blog()->posts()->status()->getId($this->rs->integer('post_status'));

        $protected = '';
        if ($this->rs->field('post_password')) {
            $protected = sprintf($img, __('Protected'), 'locker.png');
        }

        $selected = '';
        if ($this->rs->field('post_selected')) {
            $selected = sprintf($img, __('Selected'), 'selected.png');
        }

        $attach   = '';
        $nb_media = $this->rs->call('countMedia');
        if (0 < $nb_media) {
            $attach_str = 1 == $nb_media ? __('%d attachment') : __('%d attachments');
            $attach     = sprintf($img, sprintf($attach_str, $nb_media), 'attach.png');
        }

        $res = '<tr class="line ' . (1 != $this->rs->integer('post_status') ? 'offline ' : '') . $sts_class . '"' .
        ' id="p' . $this->rs->field('post_id') . '">';

        $cols = new NamedStrings([
            'title' => '<td scope="row" class="maximal"><a href="' .
            Html::escapeHTML(App::core()->posttype()->getPostAdminURL(type: $this->rs->field('post_type'), id: $this->rs->field('post_id'))) . '" ' .
            'title="' . Html::escapeHTML($this->rs->call('getURL')) . '">' .
            Html::escapeHTML(trim(Html::clean($this->rs->field('post_title')))) . '</a></td>',
            'date'   => '<td class="nowrap count">' . Clock::str(format: __('%Y-%m-%d %H:%M'), date: $this->rs->field('post_dt'), to: App::core()->getTimezone()) . '</td>',
            'author' => '<td class="nowrap">' . Html::escapeHTML($this->rs->field('user_id')) . '</td>',
            'status' => '<td class="nowrap status">' . $img_status . ' ' . $selected . ' ' . $protected . ' ' . $attach . '</td>',
        ]);

        App::core()->behavior('adminPostMiniListValue')->call($this->rs, $cols);

        App::core()->listoption()->column()->cleanColumns(id: 'posts', columns: $cols);

        $res .= implode($cols->dump());
        $res .= '</tr>';

        return $res;
    }
}
