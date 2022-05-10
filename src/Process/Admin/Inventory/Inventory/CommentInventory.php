<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Inventory\Inventory;

// Dotclear\Process\Admin\Inventory\Inventory\CommentInventory
use ArrayObject;
use Dotclear\App;
use Dotclear\Helper\Clock;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Process\Admin\Inventory\Inventory;
use Dotclear\Process\Admin\Page\Pager;

/**
 * Admin comments list helper.
 *
 * @ingroup  Admin Comment Inventory
 */
class CommentInventory extends Inventory
{
    /**
     * Display a comment list.
     *
     * @param int    $page          The page
     * @param int    $nb_per_page   The number of per page
     * @param string $enclose_block The enclose block
     * @param bool   $filter        The filter flag
     * @param bool   $spam          The spam flag
     * @param bool   $show_ip       The show ip flag
     */
    public function display(int $page, int $nb_per_page, string $enclose_block = '', bool $filter = false, bool $spam = false, bool $show_ip = true): void
    {
        if ($this->rs->isEmpty()) {
            if ($filter) {
                echo '<p><strong>' . __('No comments or trackbacks matches the filter') . '</strong></p>';
            } else {
                echo '<p><strong>' . __('No comments') . '</strong></p>';
            }
        } else {
            $pager = new Pager($page, $this->rs_count, $nb_per_page, 10);

            $comments = [];
            if (isset($_REQUEST['comments'])) {
                foreach ($_REQUEST['comments'] as $v) {
                    $comments[(int) $v] = true;
                }
            }
            $html_block = '<div class="table-outer">' .
                '<table>';

            if ($filter) {
                $html_block .= '<caption>' .
                sprintf(__(
                    'Comment or trackback matching the filter.',
                    'List of %s comments or trackbacks matching the filter.',
                    $this->rs_count
                ), $this->rs_count) .
                    '</caption>';
            } else {
                $nb_published   = App::core()->blog()->comments()->getComments(['comment_status' => 1], true)->fInt();
                $nb_spam        = App::core()->blog()->comments()->getComments(['comment_status' => -2], true)->fInt();
                $nb_pending     = App::core()->blog()->comments()->getComments(['comment_status' => -1], true)->fInt();
                $nb_unpublished = App::core()->blog()->comments()->getComments(['comment_status' => 0], true)->fInt();

                $html_block .= '<caption>' .
                sprintf(__('List of comments and trackbacks (%s)'), $this->rs_count) .
                    ($nb_published ?
                    sprintf(
                        __(', <a href="%s">published</a> (1)', ', <a href="%s">published</a> (%s)', $nb_published),
                        App::core()->adminurl()->get('admin.comments', ['status' => 1]),
                        $nb_published
                    ) : '') .
                    ($nb_spam ?
                    sprintf(
                        __(', <a href="%s">spam</a> (1)', ', <a href="%s">spam</a> (%s)', $nb_spam),
                        App::core()->adminurl()->get('admin.comments', ['status' => -2]),
                        $nb_spam
                    ) : '') .
                    ($nb_pending ?
                    sprintf(
                        __(', <a href="%s">pending</a> (1)', ', <a href="%s">pending</a> (%s)', $nb_pending),
                        App::core()->adminurl()->get('admin.comments', ['status' => -1]),
                        $nb_pending
                    ) : '') .
                    ($nb_unpublished ?
                    sprintf(
                        __(', <a href="%s">unpublished</a> (1)', ', <a href="%s">unpublished</a> (%s)', $nb_unpublished),
                        App::core()->adminurl()->get('admin.comments', ['status' => 0]),
                        $nb_unpublished
                    ) : '') .
                    '</caption>';
            }

            $cols = [
                'type'   => '<th colspan="2" scope="col" abbr="comm" class="first">' . __('Type') . '</th>',
                'author' => '<th scope="col">' . __('Author') . '</th>',
                'date'   => '<th scope="col">' . __('Date') . '</th>',
                'status' => '<th scope="col" class="txt-center">' . __('Status') . '</th>',
            ];
            if ($spam) {
                $cols['ip'] = '<th scope="col">' . __('IP') . '</th>';
            }
            $cols['entry'] = '<th scope="col" abbr="entry">' . __('Entry') . '</th>';

            $cols = new ArrayObject($cols);
            App::core()->behavior()->call('adminCommentListHeader', $this->rs, $cols, $spam);

            $html_block .= '<tr>' . implode(iterator_to_array($cols)) . '</tr>%s</table>%s</div>';

            if ($enclose_block) {
                $html_block = sprintf($enclose_block, $html_block);
            }

            $blocks = explode('%s', $html_block);

            echo $pager->getLinks() . $blocks[0];

            while ($this->rs->fetch()) {
                echo $this->commentLine(isset($comments[$this->rs->fInt('comment_id')]), $spam);
            }

            $fmt = fn ($title, $image) => sprintf('<img alt="%1$s" title="%1$s" src="?df=images/%2$s" /> %1$s', $title, $image);

            echo $blocks[1] .
                '<p class="info">' . __('Legend: ') .
                $fmt(__('Published'), 'check-on.png') . ' - ' .
                $fmt(__('Unpublished'), 'check-off.png') . ' - ' .
                $fmt(__('Pending'), 'check-wrn.png') . ' - ' .
                $fmt(__('Junk'), 'junk.png') .
                '</p>' .
                $blocks[2] . $pager->getLinks();
        }
    }

    /**
     * Get a comment line.
     *
     * @param bool $checked The checked flag
     * @param bool $spam    The spam flag
     *
     * @return string The line
     */
    private function commentLine(bool $checked = false, bool $spam = false): string
    {
        $author_url  = App::core()->adminurl()->get('admin.comments', ['author' => $this->rs->f('comment_author')]);
        $post_url    = App::core()->posttype()->getPostAdminURL($this->rs->f('post_type'), $this->rs->f('post_id'));
        $comment_url = App::core()->adminurl()->get('admin.comment', ['id' => $this->rs->f('comment_id')]);
        $img         = '<img alt="%1$s" title="%1$s" src="?df=images/%2$s" />';
        $img_status  = '';
        $sts_class   = '';

        switch ($this->rs->fInt('comment_status')) {
            case 1:
                $img_status = sprintf($img, __('Published'), 'check-on.png');
                $sts_class  = 'sts-online';

                break;

            case 0:
                $img_status = sprintf($img, __('Unpublished'), 'check-off.png');
                $sts_class  = 'sts-offline';

                break;

            case -1:
                $img_status = sprintf($img, __('Pending'), 'check-wrn.png');
                $sts_class  = 'sts-pending';

                break;

            case -2:
                $img_status = sprintf($img, __('Junk'), 'junk.png');
                $sts_class  = 'sts-junk';

                break;
        }

        $post_title = Html::escapeHTML(trim(Html::clean($this->rs->f('post_title'))));
        if (mb_strlen($post_title) > 70) {
            $post_title = mb_strcut($post_title, 0, 67) . '...';
        }
        $comment_title = sprintf(
            __('Edit the %1$s from %2$s'),
            $this->rs->fInt('comment_trackback') ? __('trackback') : __('comment'),
            Html::escapeHTML($this->rs->f('comment_author'))
        );

        $res = '<tr class="line ' . (1 != $this->rs->fInt('comment_status') ? 'offline ' : '') . $sts_class . '"' .
        ' id="c' . $this->rs->f('comment_id') . '">';

        $cols = [
            'check' => '<td class="nowrap">' .
            Form::checkbox(['comments[]'], $this->rs->f('comment_id'), $checked) .
            '</td>',
            'type' => '<td class="nowrap" abbr="' . __('Type and author') . '" scope="row">' .
            '<a href="' . $comment_url . '" title="' . $comment_title . '">' .
            '<img src="?df=images/edit-mini.png" alt="' . __('Edit') . '"/> ' .
            ($this->rs->fInt('comment_trackback') ? __('trackback') : __('comment')) . ' ' . '</a></td>',
            'author' => '<td class="nowrap maximal"><a href="' . $author_url . '">' .
            Html::escapeHTML($this->rs->f('comment_author')) . '</a></td>',
            'date'   => '<td class="nowrap count">' . Clock::str(format: __('%Y-%m-%d %H:%M'), date: $this->rs->f('comment_dt'), to: App::core()->timezone()) . '</td>',
            'status' => '<td class="nowrap status txt-center">' . $img_status . '</td>',
        ];

        if ($spam) {
            $cols['ip'] = '<td class="nowrap"><a href="' .
            App::core()->adminurl()->get('admin.comments', ['ip' => $this->rs->f('comment_ip')]) . '">' .
            $this->rs->f('comment_ip') . '</a></td>';
        }
        $cols['entry'] = '<td class="nowrap discrete"><a href="' . $post_url . '">' . $post_title . '</a>' .
            ('post' != $this->rs->f('post_type') ? ' (' . Html::escapeHTML($this->rs->f('post_type')) . ')' : '') . '</td>';

        $cols = new ArrayObject($cols);
        App::core()->behavior()->call('adminCommentListValue', $this->rs, $cols, $spam);

        $res .= implode(iterator_to_array($cols));
        $res .= '</tr>';

        return $res;
    }
}
