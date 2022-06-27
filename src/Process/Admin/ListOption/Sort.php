<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\ListOption;

// Dotclear\Process\Admin\ListOption\Sort
use Dotclear\App;

/**
 * User list sorts group helper.
 *
 * @ingroup  Admin User Preference
 */
final class Sort
{
    /**
     * @var array<string,SortGroup> $stack
     *                              The sorts groups list
     */
    private $stack = [];

    /**
     * Constructor.
     *
     * Set up sorts.
     */
    public function __construct()
    {
        $users = [null, null, null, null, null];
        if (App::core()->user()->isSuperAdmin()) {
            $this->addGroup(new SortGroup(
                id: 'users',
                title: __('User'),
                combo: App::core()->combo()->getUsersSortbyCombo(),
                sortby: 'user_id',
                sortorder: 'asc',
                sortlimit: 30,
                keyword: __('users per page'),
            ));
        } else {
            $this->addGroup(new SortGroup(
                id: 'users',
                title: __('User'),
            ));
        }

        $this->addGroup(new SortGroup(
            id: 'posts',
            title: __('Posts'),
            combo: App::core()->combo()->getPostsSortbyCombo(),
            sortby: 'post_dt',
            sortorder: 'desc',
            sortlimit: 30,
            keyword: __('entries per page'),
        ));
        $this->addGroup(new SortGroup(
            id: 'comments',
            title: __('Comments'),
            combo: App::core()->combo()->getCommentsSortbyCombo(),
            sortby: 'comment_dt',
            sortorder: 'desc',
            sortlimit: 20,
            keyword: __('comments per page'),
        ));
        $this->addGroup(new SortGroup(
            id: 'blogs',
            title: __('Blogs'),
            combo: App::core()->combo()->getBlogsSortbyCombo(),
            sortby: 'blog_upddt',
            sortorder: 'desc',
            sortlimit: 30,
            keyword: __('blogs per page'),
        ));
        $this->addGroup(new SortGroup(
            id: 'media',
            title: __('Media manager'),
            combo: [
                __('Name') => 'name',
                __('Date') => 'date',
                __('Size') => 'size',
            ],
            sortby: 'name',
            sortorder: 'asc',
            sortlimit: 30,
            keyword: __('media per page'),
        ));
        $this->addGroup(new SortGroup(
            id: 'search',
            title: __('Search'),
            sortlimit: 20,
            keyword: __('results per page'),
        ));

        // --BEHAVIOR-- adminAfterConstructSort, Sort
        App::core()->behavior('adminAfterConstructSort')->call(sort: $this);

        // Load user settings
        $sorts_user = @App::core()->user()->preferences('interface')->getPreference('sorts');
        if (is_array($sorts_user)) {
            foreach ($sorts_user as $stype => $sdata) {
                $group = $this->getGroup(id: $stype);
                if (null == $group) {
                    continue;
                }
                if (null !== $group->combo && in_array($sdata[0], $group->combo)) {
                    $group->setSortBy(by: $sdata[0]);
                }
                if (null !== $group->getSortOrder() && in_array($sdata[1], App::core()->combo()->getOrderCombo())) {
                    $group->setSortOrder(order: $sdata[1]);
                }
                if (null !== $group->keyword && is_numeric($sdata[2]) && 0 < $sdata[2]) {
                    $group->setSortLimit(limit: abs($sdata[2]));
                }
            }
        }
    }

    /**
     * Add a sorts group.
     *
     * @param SortGroup $group The sorts group instance
     */
    public function addGroup(SortGroup $group): void
    {
        $this->stack[$group->id] = $group;
    }

    /**
     * Get a sorts group.
     *
     * @param string $id The sorts group ID
     *
     * @return null|SortGroup The sorts group instance or null if not exists
     */
    public function getGroup(string $id): ?SortGroup
    {
        return $this->stack[$id] ?? null;
    }

    /**
     * Get all sorts groups.
     *
     * @return array<string,SortGroup> The sorts groups list
     */
    public function getGroups(): array
    {
        return $this->stack;
    }
}
