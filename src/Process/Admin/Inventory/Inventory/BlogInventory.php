<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Inventory\Inventory;

use ArrayObject;
use Dotclear\Helper\Dt;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Process\Admin\Inventory\Inventory;
use Dotclear\Process\Admin\Page\Pager;

/**
 * Admin blogs list helper.
 *
 * \Dotclear\Process\Admin\Inventory\Inventory\BlogInventory
 *
 * @ingroup  Admin Blog Inventory
 */
class BlogInventory extends Inventory
{
    /**
     * Display a blog list.
     *
     * @param int    $page          The page
     * @param int    $nb_per_page   The number of per page
     * @param string $enclose_block The enclose block
     * @param bool   $filter        The filter flag
     */
    public function display(int $page, int $nb_per_page, string $enclose_block = '', bool $filter = false): void
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
                (dotclear()->user()->isSuperAdmin() ? ' colspan="2"' : '') .
                ' scope="col" abbr="comm" class="first nowrap">' . __('Blog id') . '</th>',
                'name'   => '<th scope="col" abbr="name">' . __('Blog name') . '</th>',
                'url'    => '<th scope="col" class="nowrap">' . __('URL') . '</th>',
                'posts'  => '<th scope="col" class="nowrap">' . __('Entries (all types)') . '</th>',
                'upddt'  => '<th scope="col" class="nowrap">' . __('Last update') . '</th>',
                'status' => '<th scope="col" class="txt-center">' . __('Status') . '</th>',
            ];

            $cols = new ArrayObject($cols);
            dotclear()->behavior()->call('adminBlogListHeader', $this->rs, $cols);

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

            echo $pager->getLinks() . $blocks[0];

            while ($this->rs->fetch()) {
                echo $this->blogLine(isset($blogs[$this->rs->f('blog_id')]));
            }

            $fmt = fn ($title, $image) => sprintf('<img alt="%1$s" title="%1$s" src="?df=images/%2$s" /> %1$s', $title, $image);

            echo $blocks[1] .
                '<p class="info">' . __('Legend: ') .
                $fmt(__('online'), 'check-on.png') . ' - ' .
                $fmt(__('offline'), 'check-off.png') . ' - ' .
                $fmt(__('removed'), 'check-wrn.png') .
                '</p>' .
                $blocks[2] . $pager->getLinks();
        }
    }

    /**
     * Get a blog line.
     *
     * @param bool $checked The checked flag
     *
     * @return string The line
     */
    private function blogLine(bool $checked = false): string
    {
        $blog_id = Html::escapeHTML($this->rs->f('blog_id'));

        $cols = [
            'check' => (dotclear()->user()->isSuperAdmin() ?
                '<td class="nowrap">' .
                Form::checkbox(['blogs[]'], $this->rs->f('blog_id'), $checked) .
                '</td>' : ''),
            'blog' => '<td class="nowrap">' .
            (dotclear()->user()->isSuperAdmin() ?
                '<a href="' . dotclear()->adminurl()->get('admin.blog', ['id' => $blog_id]) . '"  ' .
                'title="' . sprintf(__('Edit blog settings for %s'), $blog_id) . '">' .
                '<img src="?df=images/edit-mini.png" alt="' . __('Edit blog settings') . '" /> ' . $blog_id . '</a> ' :
                $blog_id . ' ') .
            '</td>',
            'name' => '<td class="maximal">' .
            '<a href="' . dotclear()->adminurl()->get('admin.home', ['switchblog' => $this->rs->f('blog_id')]) . '" ' .
            'title="' . sprintf(__('Switch to blog %s'), $this->rs->f('blog_id')) . '">' .
            Html::escapeHTML($this->rs->f('blog_name')) . '</a>' .
            '</td>',
            'url' => '<td class="nowrap">' .
            '<a class="outgoing" href="' .
            Html::escapeHTML($this->rs->f('blog_url')) . '">' . Html::escapeHTML($this->rs->f('blog_url')) .
            ' <img src="?df=images/outgoing-link.svg" alt="" /></a></td>',
            'posts' => '<td class="nowrap count">' .
            dotclear()->blogs()->countBlogPosts($this->rs->f('blog_id')) .
            '</td>',
            'upddt' => '<td class="nowrap count">' .
            Dt::str(__('%Y-%m-%d %H:%M'), strtotime($this->rs->f('blog_upddt')) + Dt::getTimeOffset(dotclear()->user()->getInfo('user_tz'))) .
            '</td>',
            'status' => '<td class="nowrap status txt-center">' .
            sprintf(
                '<img src="?df=images/%1$s.png" alt="%2$s" title="%2$s" />',
                (1 == $this->rs->f('blog_status') ? 'check-on' : (0 == $this->rs->f('blog_status') ? 'check-off' : 'check-wrn')),
                dotclear()->blogs()->getBlogStatus($this->rs->fInt('blog_status'))
            ) .
            '</td>',
        ];

        $cols = new ArrayObject($cols);
        dotclear()->behavior()->call('adminBlogListValue', $this->rs, $cols);

        return
        '<tr class="line" id="b' . $blog_id . '">' .
        implode(iterator_to_array($cols)) .
            '</tr>';
    }
}
