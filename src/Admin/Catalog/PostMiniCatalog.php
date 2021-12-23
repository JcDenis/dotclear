<?php
/**
 * @class Dotclear\Admin\Catalog\PostMiniCatalog
 * @brief Dotclear admin list helper
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin\Catalog;

use Dotclear\Core\Core;

use Dotclear\Admin\Pager;
use Dotclear\Admin\Catalog;

use Dotclear\Html\Html;
use Dotclear\Utils\Dt;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class PostMiniCatalog extends Catalog
{
    /**
     * Display a mini post list
     *
     * @param      integer  $page           The page
     * @param      integer  $nb_per_page    The number of per page
     * @param      string   $enclose_block  The enclose block
     */
    public function display($page, $nb_per_page, $enclose_block = '')
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

            $cols = new \ArrayObject($cols);
            $this->core->callBehavior('adminPostMiniListHeader', $this->core, $this->rs, $cols);

            // Cope with optional columns
            $this->userColumns('posts', $cols);

            $html_block .= '<tr>' . implode(iterator_to_array($cols)) . '</tr>%s</table></div>';
            if ($enclose_block) {
                $html_block = sprintf($enclose_block, $html_block);
            }

            echo $pager->getLinks();

            $blocks = explode('%s', $html_block);

            echo $blocks[0];

            while ($this->rs->fetch()) {
                echo $this->postLine();
            }

            echo $blocks[1];

            echo $pager->getLinks();
        }
    }

    /**
     * Get a line.
     *
     * @return     string
     */
    private function postLine()
    {
        $img        = '<img alt="%1$s" title="%1$s" src="images/%2$s" />';
        $img_status = '';
        $sts_class  = '';
        switch ($this->rs->post_status) {
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
        if ($this->rs->post_password) {
            $protected = sprintf($img, __('Protected'), 'locker.png');
        }

        $selected = '';
        if ($this->rs->post_selected) {
            $selected = sprintf($img, __('Selected'), 'selected.png');
        }

        $attach   = '';
        $nb_media = $this->rs->countMedia();
        if ($nb_media > 0) {
            $attach_str = $nb_media == 1 ? __('%d attachment') : __('%d attachments');
            $attach     = sprintf($img, sprintf($attach_str, $nb_media), 'attach.png');
        }

        $res = '<tr class="line ' . ($this->rs->post_status != 1 ? 'offline ' : '') . $sts_class . '"' .
        ' id="p' . $this->rs->post_id . '">';

        $cols = [
            'title' => '<td scope="row" class="maximal"><a href="' .
            $this->core->getPostAdminURL($this->rs->post_type, $this->rs->post_id) . '" ' .
            'title="' . Html::escapeHTML($this->rs->getURL()) . '">' .
            Html::escapeHTML(trim(Html::clean($this->rs->post_title))) . '</a></td>',
            'date'   => '<td class="nowrap count">' . Dt::dt2str(__('%Y-%m-%d %H:%M'), $this->rs->post_dt) . '</td>',
            'author' => '<td class="nowrap">' . Html::escapeHTML($this->rs->user_id) . '</td>',
            'status' => '<td class="nowrap status">' . $img_status . ' ' . $selected . ' ' . $protected . ' ' . $attach . '</td>',
        ];

        $cols = new \ArrayObject($cols);
        $this->core->callBehavior('adminPostMiniListValue', $this->core, $this->rs, $cols);

        // Cope with optional columns
        $this->userColumns('posts', $cols);

        $res .= implode(iterator_to_array($cols));
        $res .= '</tr>';

        return $res;
    }
}
