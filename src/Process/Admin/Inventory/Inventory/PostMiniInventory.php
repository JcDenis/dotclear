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
use ArrayObject;
use Dotclear\App;
use Dotclear\Helper\Clock;
use Dotclear\Helper\Html\Html;
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

            $cols = [
                'title'  => '<th scope="col">' . __('Title') . '</th>',
                'date'   => '<th scope="col">' . __('Date') . '</th>',
                'author' => '<th scope="col">' . __('Author') . '</th>',
                'status' => '<th scope="col">' . __('Status') . '</th>',
            ];

            $cols = new ArrayObject($cols);
            App::core()->behavior()->call('adminPostMiniListHeader', $this->rs, $cols);

            $cols = App::core()->listoption()->getUserColumns('posts', $cols);

            $html_block .= '<tr>' . implode(iterator_to_array($cols)) . '</tr>%s</table></div>';
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
        $img        = '<img alt="%1$s" title="%1$s" src="?df=images/%2$s" />';
        $img_status = '';
        $sts_class  = '';

        switch ($this->rs->fInt('post_status')) {
            case 1:
                $img_status = sprintf($img, __('Published'), 'check-on.png');
                $sts_class  = 'sts-online';

                break;

            case 0:
                $img_status = sprintf($img, __('Unpublished'), 'check-off.png');
                $sts_class  = 'sts-offline';

                break;

            case -1:
                $img_status = sprintf($img, __('Scheduled'), 'scheduled.png');
                $sts_class  = 'sts-scheduled';

                break;

            case -2:
                $img_status = sprintf($img, __('Pending'), 'check-wrn.png');
                $sts_class  = 'sts-pending';

                break;
        }

        $protected = '';
        if ($this->rs->f('post_password')) {
            $protected = sprintf($img, __('Protected'), 'locker.png');
        }

        $selected = '';
        if ($this->rs->f('post_selected')) {
            $selected = sprintf($img, __('Selected'), 'selected.png');
        }

        $attach   = '';
        $nb_media = $this->rs->call('countMedia');
        if (0 < $nb_media) {
            $attach_str = 1 == $nb_media ? __('%d attachment') : __('%d attachments');
            $attach     = sprintf($img, sprintf($attach_str, $nb_media), 'attach.png');
        }

        $res = '<tr class="line ' . (1 != $this->rs->fInt('post_status') ? 'offline ' : '') . $sts_class . '"' .
        ' id="p' . $this->rs->f('post_id') . '">';

        $cols = [
            'title' => '<td scope="row" class="maximal"><a href="' .
            App::core()->posttype()->getPostAdminURL($this->rs->f('post_type'), $this->rs->f('post_id')) . '" ' .
            'title="' . Html::escapeHTML($this->rs->call('getURL')) . '">' .
            Html::escapeHTML(trim(Html::clean($this->rs->f('post_title')))) . '</a></td>',
            'date'   => '<td class="nowrap count">' . Clock::str(format: __('%Y-%m-%d %H:%M'), date: $this->rs->f('post_dt'), to: App::core()->timezone()) . '</td>',
            'author' => '<td class="nowrap">' . Html::escapeHTML($this->rs->f('user_id')) . '</td>',
            'status' => '<td class="nowrap status">' . $img_status . ' ' . $selected . ' ' . $protected . ' ' . $attach . '</td>',
        ];

        $cols = new ArrayObject($cols);
        App::core()->behavior()->call('adminPostMiniListValue', $this->rs, $cols);

        $cols = App::core()->listoption()->getUserColumns('posts', $cols);

        $res .= implode(iterator_to_array($cols));
        $res .= '</tr>';

        return $res;
    }
}
