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
use Dotclear\App;
use Dotclear\Database\Param;
use Dotclear\Helper\Clock;
use Dotclear\Helper\GPC\GPC;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Mapper\NamedStrings;
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

            $cols = new NamedStrings([
                'title'    => '<th colspan="2" class="first">' . __('Title') . '</th>',
                'date'     => '<th scope="col">' . __('Date') . '</th>',
                'category' => '<th scope="col">' . __('Category') . '</th>',
                'author'   => '<th scope="col">' . __('Author') . '</th>',
                'comments' => '<th scope="col"><img src="?df=images/comments.png" alt="" title="' . __('Comments') .
                '" /><span class="hidden">' . __('Comments') . '</span></th>',
                'trackbacks' => '<th scope="col"><img src="?df=images/trackbacks.png" alt="" title="' . __('Trackbacks') .
                '" /><span class="hidden">' . __('Trackbacks') . '</span></th>',
                'status' => '<th scope="col">' . __('Status') . '</th>',
            ]);

            // --BEHAVIOR-- adminBeforeGetPostListHeader, Record, NamedStrings
            App::core()->behavior('adminBeforeGetPostListHeader')->call(record: $this->rs, cols: $cols);

            App::core()->listoption()->column()->cleanColumns(id: 'posts', columns: $cols);

            $html_block .= '<tr>' . implode($cols->dump()) . '</tr>%s</table>%s</div>';
            if ($enclose_block) {
                $html_block = sprintf($enclose_block, $html_block);
            }

            $blocks = explode('%s', $html_block);

            echo $pager->getLinks() . $blocks[0];

            while ($this->rs->fetch()) {
                echo $this->postLine(isset($entries[$this->rs->integer('post_id')]));
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

        if ($this->rs->field('cat_title')) {
            $cat_title = sprintf(
                $cat_link,
                $this->rs->field('cat_id'),
                Html::escapeHTML($this->rs->field('cat_title'))
            );
        } else {
            $cat_title = __('(No cat)');
        }

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
            $selected = sprintf($img, __('Selected'), 'images/selected.png', 'selected');
        }

        $attach   = '';
        $nb_media = $this->rs->call('countMedia');
        if (0 < $nb_media) {
            $attach_str = 1 == $nb_media ? __('%d attachment') : __('%d attachments');
            $attach     = sprintf($img, sprintf($attach_str, $nb_media), 'images/attach.png', 'attach');
        }

        $res = '<tr class="line ' . (1 != $this->rs->field('post_status') ? 'offline ' : '') . $sts_class . '"' .
        ' id="p' . $this->rs->field('post_id') . '">';

        $cols = new NamedStrings([
            'check' => '<td class="nowrap">' .
            Form::checkbox(
                ['entries[]'],
                $this->rs->field('post_id'),
                [
                    'checked'  => $checked,
                    'disabled' => !$this->rs->call('isEditable'),
                ]
            ) .
            '</td>',
            'title' => '<td class="maximal" scope="row"><a href="' .
            Html::escapeHTML(App::core()->posttype()->getPostAdminURL(type: $this->rs->field('post_type'), id: $this->rs->field('post_id'))) . '">' .
            Html::escapeHTML(trim(Html::clean($this->rs->field('post_title')))) . '</a></td>',
            'date'       => '<td class="nowrap count">' . Clock::str(format: __('%Y-%m-%d %H:%M'), date: $this->rs->field('post_dt'), to: App::core()->getTimezone()) . '</td>',
            'category'   => '<td class="nowrap">' . $cat_title . '</td>',
            'author'     => '<td class="nowrap">' . Html::escapeHTML($this->rs->field('user_id')) . '</td>',
            'comments'   => '<td class="nowrap count">' . $this->rs->field('nb_comment') . '</td>',
            'trackbacks' => '<td class="nowrap count">' . $this->rs->field('nb_trackback') . '</td>',
            'status'     => '<td class="nowrap status">' . $img_status . ' ' . $selected . ' ' . $protected . ' ' . $attach . '</td>',
        ]);

        // --BEHAVIOR-- adminBeforeGetPostListValue, Record, NamedStrings
        App::core()->behavior('adminBeforeGetPostListValue')->call(record: $this->rs, cols: $cols);

        App::core()->listoption()->column()->cleanColumns(id: 'posts', columns: $cols);

        $res .= implode($cols->dump());
        $res .= '</tr>';

        return $res;
    }
}
