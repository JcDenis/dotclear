<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Inventory\Inventory;

// Dotclear\Process\Admin\Inventory\Inventory\PostInventory
use ArrayObject;
use Dotclear\App;
use Dotclear\Database\Param;
use Dotclear\Helper\Clock;
use Dotclear\Helper\GPC\GPC;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Process\Admin\Inventory\Inventory;
use Dotclear\Process\Admin\Page\Pager;

/**
 * Admin posts list helper.
 *
 * @ingroup  Admin Post Inventory
 */
class PostInventory extends Inventory
{
    /**
     * Display admin post list.
     *
     * @param int    $page          The page
     * @param int    $nb_per_page   The number of per page
     * @param string $enclose_block The enclose block
     * @param bool   $filter        The filter
     */
    public function display(int $page, int $nb_per_page, string $enclose_block = '', bool $filter = false): void
    {
        if ($this->rs->isEmpty()) {
            if ($filter) {
                echo '<p><strong>' . __('No entry matches the filter') . '</strong></p>';
            } else {
                echo '<p><strong>' . __('No entry') . '</strong></p>';
            }
        } else {
            $pager   = new Pager($page, $this->rs_count, $nb_per_page, 10);
            $entries = [];
            foreach (GPC::request()->array('entries') as $v) {
                $entries[(int) $v] = true;
            }

            $html_block = '<div class="table-outer"><table>';

            if ($filter) {
                $html_block .= '<caption>' . sprintf(__('List of %s entries matching the filter.'), $this->rs_count) . '</caption>';
            } else {
                $param = new Param();

                $param->set('post_status', 1);
                $nb_published   = App::core()->blog()->posts()->countPosts(param: $param);

                $param->set('post_status', -2);
                $nb_pending     = App::core()->blog()->posts()->countPosts(param: $param);

                $param->set('post_status', -1);
                $nb_programmed  = App::core()->blog()->posts()->countPosts(param: $param);

                $param->set('post_status', 0);
                $nb_unpublished = App::core()->blog()->posts()->countPosts(param: $param);

                $html_block .= '<caption>' .
                sprintf(__('List of entries (%s)'), $this->rs_count) .
                    ($nb_published ?
                    sprintf(
                        __(', <a href="%s">published</a> (1)', ', <a href="%s">published</a> (%s)', $nb_published),
                        App::core()->adminurl()->get('admin.posts', ['status' => 1]),
                        $nb_published
                    ) : '') .
                    ($nb_pending ?
                    sprintf(
                        __(', <a href="%s">pending</a> (1)', ', <a href="%s">pending</a> (%s)', $nb_pending),
                        App::core()->adminurl()->get('admin.posts', ['status' => -2]),
                        $nb_pending
                    ) : '') .
                    ($nb_programmed ?
                    sprintf(
                        __(', <a href="%s">programmed</a> (1)', ', <a href="%s">programmed</a> (%s)', $nb_programmed),
                        App::core()->adminurl()->get('admin.posts', ['status' => -1]),
                        $nb_programmed
                    ) : '') .
                    ($nb_unpublished ?
                    sprintf(
                        __(', <a href="%s">unpublished</a> (1)', ', <a href="%s">unpublished</a> (%s)', $nb_unpublished),
                        App::core()->adminurl()->get('admin.posts', ['status' => 0]),
                        $nb_unpublished
                    ) : '') .
                    '</caption>';
            }

            $cols = [
                'title'    => '<th colspan="2" class="first">' . __('Title') . '</th>',
                'date'     => '<th scope="col">' . __('Date') . '</th>',
                'category' => '<th scope="col">' . __('Category') . '</th>',
                'author'   => '<th scope="col">' . __('Author') . '</th>',
                'comments' => '<th scope="col"><img src="?df=images/comments.png" alt="" title="' . __('Comments') .
                '" /><span class="hidden">' . __('Comments') . '</span></th>',
                'trackbacks' => '<th scope="col"><img src="?df=images/trackbacks.png" alt="" title="' . __('Trackbacks') .
                '" /><span class="hidden">' . __('Trackbacks') . '</span></th>',
                'status' => '<th scope="col">' . __('Status') . '</th>',
            ];
            $cols = new ArrayObject($cols);
            App::core()->behavior()->call('adminPostListHeader', $this->rs, $cols);

            $cols = App::core()->listoption()->getUserColumns('posts', $cols);

            $html_block .= '<tr>' . implode(iterator_to_array($cols)) . '</tr>%s</table>%s</div>';
            if ($enclose_block) {
                $html_block = sprintf($enclose_block, $html_block);
            }

            $blocks = explode('%s', $html_block);

            echo $pager->getLinks() . $blocks[0];

            while ($this->rs->fetch()) {
                echo $this->postLine(isset($entries[$this->rs->fInt('post_id')]));
            }

            $img     = '<img alt="%1$s" title="%1$s" src="?df=%2$s" /> %1$s';
            $legends = [];
            foreach (App::core()->blog()->posts()->status()->getCodes() as $code) {
                $legends[] = sprintf(
                    $img,
                    App::core()->blog()->posts()->status()->getState($code),
                    App::core()->blog()->posts()->status()->getIcon($code),
                );
            }
            $legends[] = sprintf($img, __('Protected'), 'images/locker.png');
            $legends[] = sprintf($img, __('Selected'), 'images/selected.png');
            $legends[] = sprintf($img, __('Attachments'), 'images/attach.png');

            echo $blocks[1] .
            '<p class="info">' . __('Legend: ') . implode(' - ', $legends) . '</p>' .
            $blocks[2] . $pager->getLinks();
        }
    }

    /**
     * Get a line.
     *
     * @param bool $checked The checked flag
     *
     * @return string The line
     */
    private function postLine(bool $checked): string
    {
        if (App::core()->user()->check('categories', App::core()->blog()->id)) {
            $cat_link = '<a href="' . App::core()->adminurl()->get('admin.category', ['id' => '%s'], '&amp;', true) . '">%s</a>';
        } else {
            $cat_link = '%2$s';
        }

        if ($this->rs->f('cat_title')) {
            $cat_title = sprintf(
                $cat_link,
                $this->rs->f('cat_id'),
                Html::escapeHTML($this->rs->f('cat_title'))
            );
        } else {
            $cat_title = __('(No cat)');
        }

        $img = '<img alt="%1$s" title="%1$s" src="?df=%2$s" class="mark mark-%3$s" />';

        $img_status = sprintf(
            $img,
            App::core()->blog()->posts()->status()->getState($this->rs->fInt('post_status')),
            App::core()->blog()->posts()->status()->getIcon($this->rs->fInt('post_status')),
            App::core()->blog()->posts()->status()->getId($this->rs->fInt('post_status')),
        );
        $sts_class = 'sts-' . App::core()->blog()->posts()->status()->getId($this->rs->fInt('post_status'));

        $protected = '';
        if ($this->rs->f('post_password')) {
            $protected = sprintf($img, __('Protected'), 'images/locker.png', 'locked');
        }

        $selected = '';
        if ($this->rs->f('post_selected')) {
            $selected = sprintf($img, __('Selected'), 'images/selected.png', 'selected');
        }

        $attach   = '';
        $nb_media = $this->rs->call('countMedia');
        if (0 < $nb_media) {
            $attach_str = 1 == $nb_media ? __('%d attachment') : __('%d attachments');
            $attach     = sprintf($img, sprintf($attach_str, $nb_media), 'images/attach.png', 'attach');
        }

        $res = '<tr class="line ' . (1 != $this->rs->f('post_status') ? 'offline ' : '') . $sts_class . '"' .
        ' id="p' . $this->rs->f('post_id') . '">';

        $cols = [
            'check' => '<td class="nowrap">' .
            Form::checkbox(
                ['entries[]'],
                $this->rs->f('post_id'),
                [
                    'checked'  => $checked,
                    'disabled' => !$this->rs->call('isEditable'),
                ]
            ) .
            '</td>',
            'title' => '<td class="maximal" scope="row"><a href="' .
            Html::escapeHTML(App::core()->posttype()->getPostAdminURL(type: $this->rs->f('post_type'), id: $this->rs->f('post_id'))) . '">' .
            Html::escapeHTML(trim(Html::clean($this->rs->f('post_title')))) . '</a></td>',
            'date'       => '<td class="nowrap count">' . Clock::str(format: __('%Y-%m-%d %H:%M'), date: $this->rs->f('post_dt'), to: App::core()->timezone()) . '</td>',
            'category'   => '<td class="nowrap">' . $cat_title . '</td>',
            'author'     => '<td class="nowrap">' . Html::escapeHTML($this->rs->f('user_id')) . '</td>',
            'comments'   => '<td class="nowrap count">' . $this->rs->f('nb_comment') . '</td>',
            'trackbacks' => '<td class="nowrap count">' . $this->rs->f('nb_trackback') . '</td>',
            'status'     => '<td class="nowrap status">' . $img_status . ' ' . $selected . ' ' . $protected . ' ' . $attach . '</td>',
        ];
        $cols = new ArrayObject($cols);
        App::core()->behavior()->call('adminPostListValue', $this->rs, $cols);

        $cols = App::core()->listoption()->getUserColumns('posts', $cols);

        $res .= implode(iterator_to_array($cols));
        $res .= '</tr>';

        return $res;
    }
}
