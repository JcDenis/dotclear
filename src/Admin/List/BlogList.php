<?php
/**
 * @class Dotclear\Admin\List\BlogList
 * @brief Dotclear admin list helper
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin;

use Dotclear\Core\Core;

use Dotclear\Admin\Pager;
use Dotclear\Admin\List;

use Dotclear\Html\Html;
use Dotclear\Html\Form;
use Dotclear\Utils\Dt;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class BlogList extends List
{
    /**
     * Display a blog list
     *
     * @param      integer  $page           The page
     * @param      integer  $nb_per_page    The number of per page
     * @param      string   $enclose_block  The enclose block
     * @param      bool     $filter         The filter flag
     */
    public function display($page, $nb_per_page, $enclose_block = '', $filter = false)
    {
        if ($this->rs->isEmpty()) {
            if ($filter) {
                echo '<p><strong>' . __('No blog matches the filter') . '</strong></p>';
            } else {
                echo '<p><strong>' . __('No blog') . '</strong></p>';
            }
        } else {
            $blogs = [];
            if (isset($_REQUEST['blogs'])) {
                foreach ($_REQUEST['blogs'] as $v) {
                    $blogs[$v] = true;
                }
            }

            $pager = new Pager($page, $this->rs_count, $nb_per_page, 10);

            $cols = [
                'blog' => '<th' .
                ($this->core->auth->isSuperAdmin() ? ' colspan="2"' : '') .
                ' scope="col" abbr="comm" class="first nowrap">' . __('Blog id') . '</th>',
                'name'   => '<th scope="col" abbr="name">' . __('Blog name') . '</th>',
                'url'    => '<th scope="col" class="nowrap">' . __('URL') . '</th>',
                'posts'  => '<th scope="col" class="nowrap">' . __('Entries (all types)') . '</th>',
                'upddt'  => '<th scope="col" class="nowrap">' . __('Last update') . '</th>',
                'status' => '<th scope="col" class="txt-center">' . __('Status') . '</th>',
            ];

            $cols = new \ArrayObject($cols);
            $this->core->callBehavior('adminBlogListHeader', $this->core, $this->rs, $cols);

            $html_block = '<div class="table-outer"><table>' .
            (
                $filter ?
                '<caption>' .
                sprintf(__('%d blog matches the filter.', '%d blogs match the filter.', $this->rs_count), $this->rs_count) .
                '</caption>'
                :
                '<caption class="hidden">' . __('Blogs list') . '</caption>'
            ) .
            '<tr>' . implode(iterator_to_array($cols)) . '</tr>%s</table>%s</div>';

            if ($enclose_block) {
                $html_block = sprintf($enclose_block, $html_block);
            }

            $blocks = explode('%s', $html_block);

            echo $pager->getLinks();

            echo $blocks[0];

            while ($this->rs->fetch()) {
                echo $this->blogLine(isset($blogs[$this->rs->blog_id]));
            }

            echo $blocks[1];

            $fmt = function ($title, $image) {
                return sprintf('<img alt="%1$s" title="%1$s" src="images/%2$s" /> %1$s', $title, $image);
            };
            echo '<p class="info">' . __('Legend: ') .
                $fmt(__('online'), 'check-on.png') . ' - ' .
                $fmt(__('offline'), 'check-off.png') . ' - ' .
                $fmt(__('removed'), 'check-wrn.png') .
                '</p>';

            echo $blocks[2];

            echo $pager->getLinks();
        }
    }

    /**
     * Get a blog line
     *
     * @param      bool    $checked  The checked flag
     *
     * @return     string
     */
    private function blogLine($checked = false)
    {
        $blog_id = Html::escapeHTML($this->rs->blog_id);

        $cols = [
            'check' => ($this->core->auth->isSuperAdmin() ?
                '<td class="nowrap">' .
                Form::checkbox(['blogs[]'], $this->rs->blog_id, $checked) .
                '</td>' : ''),
            'blog' => '<td class="nowrap">' .
            ($this->core->auth->isSuperAdmin() ?
                '<a href="' . $this->core->adminurl->get('admin.blog', ['id' => $blog_id]) . '"  ' .
                'title="' . sprintf(__('Edit blog settings for %s'), $blog_id) . '">' .
                '<img src="images/edit-mini.png" alt="' . __('Edit blog settings') . '" /> ' . $blog_id . '</a> ' :
                $blog_id . ' ') .
            '</td>',
            'name' => '<td class="maximal">' .
            '<a href="' . $this->core->adminurl->get('admin.home', ['switchblog' => $this->rs->blog_id]) . '" ' .
            'title="' . sprintf(__('Switch to blog %s'), $this->rs->blog_id) . '">' .
            Html::escapeHTML($this->rs->blog_name) . '</a>' .
            '</td>',
            'url' => '<td class="nowrap">' .
            '<a class="outgoing" href="' .
            Html::escapeHTML($this->rs->blog_url) . '">' . Html::escapeHTML($this->rs->blog_url) .
            ' <img src="images/outgoing-link.svg" alt="" /></a></td>',
            'posts' => '<td class="nowrap count">' .
            $this->core->countBlogPosts($this->rs->blog_id) .
            '</td>',
            'upddt' => '<td class="nowrap count">' .
            Dt::str(__('%Y-%m-%d %H:%M'), strtotime($this->rs->blog_upddt) + Dt::getTimeOffset($this->core->auth->getInfo('user_tz'))) .
            '</td>',
            'status' => '<td class="nowrap status txt-center">' .
            sprintf(
                '<img src="images/%1$s.png" alt="%2$s" title="%2$s" />',
                ($this->rs->blog_status == 1 ? 'check-on' : ($this->rs->blog_status == 0 ? 'check-off' : 'check-wrn')),
                $this->core->getBlogStatus($this->rs->blog_status)
            ) .
            '</td>',
        ];

        $cols = new \ArrayObject($cols);
        $this->core->callBehavior('adminBlogListValue', $this->core, $this->rs, $cols);

        return
        '<tr class="line" id="b' . $blog_id . '">' .
        implode(iterator_to_array($cols)) .
            '</tr>';
    }
}
