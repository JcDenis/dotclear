<?php
/**
 * @package     Dotclear
 *
 * @copyright   Olivier Meunier & Association Dotclear
 * @copyright   AGPL-3.0
 */
declare(strict_types=1);

namespace Dotclear\Core\Backend\Listing;

use ArrayObject;
use Dotclear\App;
use Dotclear\Helper\Date;
use Dotclear\Helper\Html\Html;
use form;

/**
 * @brief   Blogs list pager form helper.
 *
 * @since   2.20
 */
class ListingBlogs extends Listing
{
    /**
     * Display a blog list.
     *
     * @param   int     $page           The page
     * @param   int     $nb_per_page    The number of blogs per page
     * @param   string  $enclose_block  The enclose block
     * @param   bool    $filter         The filter
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

            $pager = new Pager($page, (int) $this->rs_count, $nb_per_page, 10);

            $cols = [
                'blog' => '<th' .
                (App::auth()->isSuperAdmin() ? ' colspan="2"' : '') .
                ' scope="col" abbr="comm" class="first nowrap">' . __('Blog id') . '</th>',
                'name'   => '<th scope="col" abbr="name">' . __('Blog name') . '</th>',
                'url'    => '<th scope="col" class="nowrap">' . __('URL') . '</th>',
                'posts'  => '<th scope="col" class="nowrap">' . __('Entries (all types)') . '</th>',
                'upddt'  => '<th scope="col" class="nowrap">' . __('Last update') . '</th>',
                'status' => '<th scope="col" class="txt-center">' . __('Status') . '</th>',
            ];

            $cols = new ArrayObject($cols);

            # --BEHAVIOR-- adminBlogListHeaderV2 -- MetaRecord, ArrayObject
            App::behavior()->callBehavior('adminBlogListHeaderV2', $this->rs, $cols);

            // Cope with optional columns
            $this->userColumns('blogs', $cols);

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

            $fmt = fn ($title, $image, $class) => sprintf('<img alt="%1$s" class="mark mark-%3$s" src="images/%2$s"> %1$s', $title, $image, $class);
            echo '<p class="info">' . __('Legend: ') .
                $fmt(__('online'), 'published.svg', 'published') . ' - ' .
                $fmt(__('offline'), 'unpublished.svg', 'unpublished') . ' - ' .
                $fmt(__('removed'), 'pending.svg', 'pending') .
                '</p>';

            echo $blocks[2];

            echo $pager->getLinks();
        }
    }

    /**
     * Get a blog line.
     *
     * @param   bool    $checked    The checked flag
     *
     * @return  string
     */
    private function blogLine(bool $checked = false): string
    {
        $blog_id = Html::escapeHTML($this->rs->blog_id);

        $cols = [
            'check' => (App::auth()->isSuperAdmin() ?
                '<td class="nowrap">' .
                form::checkbox(['blogs[]'], $this->rs->blog_id, $checked) .
                '</td>' : ''),
            'blog' => '<td class="nowrap">' .
            (App::auth()->isSuperAdmin() ?
                '<a href="' . App::backend()->url()->get('admin.blog', ['id' => $blog_id]) . '"  ' .
                'title="' . sprintf(__('Edit blog settings for %s'), $blog_id) . '">' .
                '<img class="mark mark-edit light-only" src="images/edit.svg" alt="' . __('Edit blog settings') . '">' .
                '<img class="mark mark-edit dark-only" src="images/edit-dark.svg" alt="' . __('Edit blog settings') . '">' .
                ' ' . $blog_id . '</a> ' :
                $blog_id . ' ') .
            '</td>',
            'name' => '<td class="maximal">' .
            '<a href="' . App::backend()->url()->get('admin.home', ['switchblog' => $this->rs->blog_id]) . '" ' .
            'title="' . sprintf(__('Switch to blog %s'), $this->rs->blog_id) . '">' .
            Html::escapeHTML($this->rs->blog_name) . '</a>' .
            '</td>',
            'url' => '<td class="nowrap">' .
            '<a class="outgoing" href="' .
            Html::escapeHTML($this->rs->blog_url) . '">' . Html::escapeHTML($this->rs->blog_url) .
            ' <img src="images/outgoing-link.svg" alt=""></a></td>',
            'posts' => '<td class="nowrap count">' .
            App::blogs()->countBlogPosts($this->rs->blog_id) .
            '</td>',
            'upddt' => '<td class="nowrap count">' .
            '<time datetime="' . Date::iso8601((int) strtotime($this->rs->blog_upddt), App::auth()->getInfo('user_tz')) . '">' .
            Date::str(__('%Y-%m-%d %H:%M'), strtotime($this->rs->blog_upddt) + Date::getTimeOffset(App::auth()->getInfo('user_tz'))) .
            '</time>' .
            '</td>',
            'status' => '<td class="nowrap status txt-center">' .
            sprintf(
                '<img src="images/%1$s" class="mark mark-%3$s" alt="%2$s">',
                ($this->rs->blog_status == App::blog()::BLOG_ONLINE ? 'published.svg' : ($this->rs->blog_status == App::blog()::BLOG_OFFLINE ? 'unpublished.svg' : 'pending.svg')),
                App::blogs()->getBlogStatus((int) $this->rs->blog_status),
                ($this->rs->blog_status == App::blog()::BLOG_ONLINE ? 'published' : ($this->rs->blog_status == App::blog()::BLOG_OFFLINE ? 'unpublished' : 'pending')),
            ) .
            '</td>',
        ];

        $cols = new ArrayObject($cols);
        # --BEHAVIOR-- adminBlogListValueV2 -- MetaRecord, ArrayObject
        App::behavior()->callBehavior('adminBlogListValueV2', $this->rs, $cols);

        // Cope with optional columns
        $this->userColumns('blogs', $cols);

        return
        '<tr class="line" id="b' . $blog_id . '">' .
        implode(iterator_to_array($cols)) .
            '</tr>';
    }
}
