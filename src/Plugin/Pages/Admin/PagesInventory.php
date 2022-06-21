<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Pages\Admin;

// Dotclear\Plugin\Pages\Admin\PagesInventory
use ArrayObject;
use Dotclear\App;
use Dotclear\Process\Admin\Page\Pager;
use Dotclear\Process\Admin\Inventory\Inventory;
use Dotclear\Helper\GPC\GPC;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Clock;

/**
 * Admin inventory for plugin Pages.
 *
 * @ingroup  Plugin Pages Inventory
 */
class PagesInventory extends Inventory
{
    public function display($page, $nb_per_page, $enclose_block = '')
    {
        if ($this->rs->isEmpty()) {
            echo '<p><strong>' . __('No page') . '</strong></p>';
        } else {
            $pager   = new Pager($page, $this->rs_count, $nb_per_page, 10);
            $entries = [];
            foreach (GPC::request()->array('entries') as $v) {
                $entries[(int) $v] = true;
            }

            $html_block = '<div class="table-outer">' .
                '<table class="maximal dragable"><thead><tr>';

            $cols = [
                'title'    => '<th colspan="3" scope="col" class="first">' . __('Title') . '</th>',
                'date'     => '<th scope="col">' . __('Date') . '</th>',
                'author'   => '<th scope="col">' . __('Author') . '</th>',
                'comments' => '<th scope="col"><img src="?df=images/comments.png" alt="" title="' . __('Comments') .
                '" /><span class="hidden">' . __('Comments') . '</span></th>',
                'trackbacks' => '<th scope="col"><img src="?df=images/trackbacks.png" alt="" title="' . __('Trackbacks') .
                '" /><span class="hidden">' . __('Trackbacks') . '</span></th>',
                'status' => '<th scope="col">' . __('Status') . '</th>',
            ];

            $cols = new ArrayObject($cols);
            App::core()->behavior('adminPagesListHeader')->call($this->rs, $cols);

            $cols = App::core()->listoption()->column()->cleanColumns(id: 'pages', columns: $cols);

            $html_block .= '<tr>' . implode(iterator_to_array($cols)) .
                '</tr></thead><tbody id="pageslist">%s</tbody></table>%s</div>';

            if ($enclose_block) {
                $html_block = sprintf($enclose_block, $html_block);
            }

            $blocks = explode('%s', $html_block);

            echo $pager->getLinks() . $blocks[0];

            $count = 0;
            while ($this->rs->fetch()) {
                echo $this->postLine($count, isset($entries[$this->rs->integer('post_id')]));
                ++$count;
            }

            echo $blocks[1];

            $fmt = fn ($title, $image) => sprintf('<img alt="%1$s" title="%1$s" src="?df=images/%2$s" /> %1$s', $title, $image);
            echo '<p class="info">' . __('Legend: ') .
                $fmt(__('Published'), 'check-on.png') . ' - ' .
                $fmt(__('Unpublished'), 'check-off.png') . ' - ' .
                $fmt(__('Scheduled'), 'scheduled.png') . ' - ' .
                $fmt(__('Pending'), 'check-wrn.png') . ' - ' .
                $fmt(__('Protected'), 'locker.png') . ' - ' .
                $fmt(__('Hidden'), 'hidden.png') . ' - ' .
                $fmt(__('Attachments'), 'attach.png') .
                '</p>';

            echo $blocks[2] . $pager->getLinks();
        }
    }

    private function postLine($count, $checked)
    {
        $img = '<img alt="%1$s" title="%1$s" src="?df=%2$s" class="mark mark-%3$s" />';

        $img_status = sprintf(
            $img,
            App::core()->blog()->posts()->status()->getState($this->rs->integer('post_status')),
            App::core()->blog()->posts()->status()->getIcon($this->rs->integer('post_status')),
            App::core()->blog()->posts()->status()->getId($this->rs->integer('post_status')),
        );
        $sts_class = 'sts-' . App::core()->blog()->posts()->status()->getId($this->rs->integer('post_status'));

        $protected = '';
        if ($this->rs->field('post_password')) {
            $protected = sprintf($img, __('Protected'), 'images/locker.png', 'locked');
        }

        $selected = '';
        if ($this->rs->field('post_selected')) {
            $selected = sprintf($img, __('Hidden'), 'images/hidden.png', 'hidden');
        }

        $attach   = '';
        $nb_media = $this->rs->call('countMedia');
        if (0 < $nb_media) {
            $attach_str = 1 == $nb_media ? __('%d attachment') : __('%d attachments');
            $attach     = sprintf($img, sprintf($attach_str, $nb_media), 'images/attach.png', 'attach');
        }

        $res = '<tr class="line ' . (1 != $this->rs->integer('post_status') ? 'offline ' : '') . $sts_class . '"' .
        ' id="p' . $this->rs->field('post_id') . '">';

        $cols = [
            'position' => '<td class="nowrap handle minimal">' .
            Form::number(['order[' . $this->rs->field('post_id') . ']'], [
                'min'        => 1,
                'default'    => $count + 1,
                'class'      => 'position',
                'extra_html' => 'title="' . sprintf(__('position of %s'), Html::escapeHTML($this->rs->field('post_title'))) . '"',
            ]) .
            '</td>',
            'check' => '<td class="nowrap">' .
            Form::checkbox(
                ['entries[]'],
                $this->rs->integer('post_id'),
                [
                    'checked'    => $checked,
                    'disabled'   => !$this->rs->call('isEditable'),
                    'extra_html' => 'title="' . __('Select this page') . '"',
                ]
            ) . '</td>',
            'title' => '<td class="maximal" scope="row"><a href="' .
            Html::escapeHTML(App::core()->posttype()->getPostAdminURL(type: $this->rs->field('post_type'), id: $this->rs->field('post_id'))) . '">' .
            Html::escapeHTML($this->rs->field('post_title')) . '</a></td>',
            'date'       => '<td class="nowrap">' . Clock::str(format: __('%Y-%m-%d %H:%M'), date: $this->rs->field('post_dt'), to: App::core()->getTimezone()) . '</td>',
            'author'     => '<td class="nowrap">' . $this->rs->field('user_id') . '</td>',
            'comments'   => '<td class="nowrap count">' . $this->rs->field('nb_comment') . '</td>',
            'trackbacks' => '<td class="nowrap count">' . $this->rs->field('nb_trackback') . '</td>',
            'status'     => '<td class="nowrap status">' . $img_status . ' ' . $selected . ' ' . $protected . ' ' . $attach . '</td>',
        ];

        $cols = new ArrayObject($cols);
        App::core()->behavior('adminPagesListValue')->call($this->rs, $cols);

        $cols = App::core()->listoption()->column()->cleanColumns(id: 'pages', columns: $cols);

        $res .= implode(iterator_to_array($cols));
        $res .= '</tr>';

        return $res;
    }
}
