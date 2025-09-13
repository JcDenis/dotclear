<?php

/**
 * @package     Dotclear
 * @subpackage  Backend
 *
 * @copyright   Olivier Meunier & Association Dotclear
 * @copyright   AGPL-3.0
 */
declare(strict_types=1);

namespace Dotclear\Core\Backend\Listing;

use ArrayObject;
use Dotclear\App;
use Dotclear\Helper\Date;
use Dotclear\Helper\Html\Form\Caption;
use Dotclear\Helper\Html\Form\Checkbox;
use Dotclear\Helper\Html\Form\Div;
use Dotclear\Helper\Html\Form\Img;
use Dotclear\Helper\Html\Form\Link;
use Dotclear\Helper\Html\Form\None;
use Dotclear\Helper\Html\Form\Para;
use Dotclear\Helper\Html\Form\Set;
use Dotclear\Helper\Html\Form\Strong;
use Dotclear\Helper\Html\Form\Table;
use Dotclear\Helper\Html\Form\Td;
use Dotclear\Helper\Html\Form\Text;
use Dotclear\Helper\Html\Form\Th;
use Dotclear\Helper\Html\Form\Timestamp;
use Dotclear\Helper\Html\Form\Tr;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Stack\Status;

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
            echo (new Para())
                ->items([
                    (new Strong($filter ? __('No blog matches the filter') : __('No blog'))),
                ])
            ->render();

            return;
        }

        // At least one blog to render
        $blogs = [];
        if (isset($_REQUEST['blogs'])) {
            foreach ($_REQUEST['blogs'] as $v) {
                $blogs[$v] = true;
            }
        }

        $pager = (new Pager($page, (int) $this->rs_count, $nb_per_page, 10))->getLinks();

        $cols = [
            'blog' => (new Th())
                ->colspan(App::auth()->isSuperAdmin() ? 2 : 1)
                ->scope('col')
                ->abbr('comm')
                ->class(['first', 'nowrap'])
                ->text(__('Blog id')),

            'name' => (new Th())
                ->scope('col')
                ->abbr('name')
                ->text(__('Blog name')),

            'url' => (new Th())
                ->scope('col')
                ->class('nowrap')
                ->text(__('URL')),

            'posts' => (new Th())
                ->scope('col')
                ->class('nowrap')
                ->text(__('Entries (all types)')),

            'upddt' => (new Th())
                ->scope('col')
                ->class('nowrap')
                ->text(__('Last update')),

            'status' => (new Th())
                ->scope('col')
                ->text(__('Status')),
        ];

        /**
         * @var ArrayObject<string, mixed>
         */
        $cols = new ArrayObject($cols);

        # --BEHAVIOR-- adminBlogListHeaderV2 -- MetaRecord, ArrayObject<string, mixed>, bool
        App::behavior()->callBehavior('adminBlogListHeaderV2', $this->rs, $cols, true);

        // Cope with optional columns
        $this->userColumns('blogs', $cols, true);

        // Prepare listing
        $lines = [
            (new Tr())
                ->items($cols),
        ];
        while ($this->rs->fetch()) {
            $lines[] = $this->blogLine(isset($blogs[$this->rs->blog_id]));
        }

        $buffer = (new Div())
            ->class('table-outer')
            ->items([
                (new Table())
                    ->caption((new Caption($filter ?
                        sprintf(__('%d blog matches the filter.', '%d blogs match the filter.', $this->rs_count), $this->rs_count) :
                        __('Blogs list')))
                        ->class(array_filter([$filter ? '' : 'hidden'])))
                    ->items($lines),
                (new Para())
                    ->class('info')
                    ->items([
                        (new Text(
                            null,
                            __('Legend: ') . (new Set())
                            ->separator(' - ')
                            ->items(array_map(fn (Status $k): Img|Set|Text => App::status()->blog()->image($k->id(), true), App::status()->blog()->dump()))
                            ->render(),
                        )),
                    ]),
            ])
        ->render();
        if ($enclose_block !== '') {
            $buffer = sprintf($enclose_block, $buffer);
        }

        echo $pager . $buffer . $pager;
    }

    /**
     * Get a blog line.
     *
     * @param   bool    $checked    The checked flag
     */
    private function blogLine(bool $checked = false): Tr
    {
        $blog_id = Html::escapeHTML($this->rs->blog_id);

        $cols = [
            'check' => App::auth()->isSuperAdmin() ?
                (new Td())
                    ->class('nowrap')
                    ->items([
                        (new Checkbox(['blogs[]'], $checked))
                            ->value($this->rs->blog_id),
                    ]) :
                (new None()),

            'blog' => (new Td())
                ->class('nowrap')
                ->items([
                    App::auth()->isSuperAdmin() ?
                    (new Link())
                        ->href(App::backend()->url()->get('admin.blog', ['id' => $blog_id]))
                        ->title(sprintf(__('Edit blog settings for %s'), $blog_id))
                        ->items([
                            (new Img('images/edit.svg'))
                                ->class(['mark', 'mark-edit', 'light-only'])
                                ->alt(__('Edit blog settings')),
                            (new Img('images/edit-dark.svg'))
                                ->class(['mark', 'mark-edit', 'dark-only'])
                                ->alt(__('Edit blog settings')),
                            (new Text(null, $blog_id)),
                        ]) :
                    (new Text(null, $blog_id)),
                ]),

            'name' => (new Td())
                ->class('maximal')
                ->items([
                    (new Link())
                        ->href(App::backend()->url()->get('admin.home', ['switchblog' => $this->rs->blog_id]))
                        ->title(sprintf(__('Switch to blog %s'), $this->rs->blog_id))
                        ->text(Html::escapeHTML($this->rs->blog_name)),
                ]),

            'url' => (new Td())
                ->class('nowrap')
                ->items([
                    (new Link())
                        ->class('outgoing')
                        ->href(Html::escapeHTML($this->rs->blog_url))
                        ->separator(' ')
                        ->items([
                            (new Text(null, Html::escapeHTML($this->rs->blog_url))),
                            (new Img('images/outgoing-link.svg'))
                                ->alt(''),
                        ]),
                ]),

            'posts' => (new Td())
                ->class(['nowrap', 'count'])
                ->text((string) App::blogs()->countBlogPosts($this->rs->blog_id)),

            'upddt' => (new Td())
                ->class(['nowrap', 'count'])
                ->items([
                    (new Timestamp(Date::str(__('%Y-%m-%d %H:%M'), strtotime($this->rs->blog_upddt) + Date::getTimeOffset(App::auth()->getInfo('user_tz')))))
                        ->datetime(Date::iso8601((int) strtotime($this->rs->blog_upddt), App::auth()->getInfo('user_tz'))),
                ]),

            'status' => (new Td())
                ->class(['nowrap', 'status'])
                ->items([App::status()->blog()->image((int) $this->rs->blog_status)]),
        ];

        /**
         * @var ArrayObject<string, mixed>
         */
        $cols = new ArrayObject($cols);
        # --BEHAVIOR-- adminBlogListValueV2 -- MetaRecord, ArrayObject<string, mixed>, bool
        App::behavior()->callBehavior('adminBlogListValueV2', $this->rs, $cols, true);

        // Cope with optional columns
        $this->userColumns('blogs', $cols, true);

        return (new Tr())
            ->id('b' . $blog_id)
            ->class('line')
            ->items($cols);
    }
}
