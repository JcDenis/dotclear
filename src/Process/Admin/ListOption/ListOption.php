<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\ListOption;

// Dotclear\Process\Admin\ListOption\ListOption
use ArrayObject;
use Dotclear\App;

/**
 * User list option preference library.
 *
 * Dotclear utility class that provides reuseable user preference
 * across all admin page with lists and filters
 *
 * Accessible from App::core()->listoption()->
 *
 * @ingroup  Admin User Preference
 */
class ListOption
{
    /**
     * @var ArrayObject $sorts
     *                  Sorts filters preferences
     */
    private $sorts;

    /**
     * Get default columns.
     *
     * @return array The default columns
     */
    public function getDefaultColumns(): array
    {
        return ['posts' => [__('Posts'), [
            'date'       => [true, __('Date')],
            'category'   => [true, __('Category')],
            'author'     => [true, __('Author')],
            'comments'   => [true, __('Comments')],
            'trackbacks' => [true, __('Trackbacks')],
        ]]];
    }

    /**
     * Get users columns preferences.
     *
     * @param string      $type    The columns type
     * @param ArrayObject $columns The columns list
     *
     * @return ArrayObject The user columns
     */
    public function getUserColumns(string $type = null, ArrayObject $columns = null): ArrayObject
    {
        // Get default colums (admin lists)
        $cols = $this->getDefaultColumns();
        $cols = new ArrayObject($cols);

        // --BEHAVIOR-- adminColumnsLists
        App::core()->behavior()->call('adminColumnsLists', $cols);

        // Load user settings
        $cols_user = @App::core()->user()->preference()->get('interface')->get('cols');
        if (is_array($cols_user) || $cols_user instanceof ArrayObject) {
            foreach ($cols_user as $ct => $cv) {
                foreach ($cv as $cn => $cd) {
                    if (isset($cols[$ct][1][$cn])) {
                        $cols[$ct][1][$cn][0] = $cd;

                        // remove unselected columns if type is given
                        if (!$cd && !empty($type) && !empty($columns) && $ct == $type && isset($columns[$cn])) {
                            unset($columns[$cn]);
                        }
                    }
                }
            }
        }
        if (null !== $columns) {
            return $columns;
        }
        if (null !== $type) {
            return new ArrayObject($cols[$type] ?? []);
        }

        return $cols;
    }

    /**
     * Get default filters.
     *
     * @return array The default filters
     */
    public function getDefaultFilters(): array
    {
        $users = [null, null, null, null, null];
        if (App::core()->user()->isSuperAdmin()) {
            $users = [
                __('Users'),
                App::core()->combo()->getUsersSortbyCombo(),
                'user_id',
                'asc',
                [__('users per page'), 30],
            ];
        }

        return [
            'posts' => [
                __('Posts'),
                App::core()->combo()->getPostsSortbyCombo(),
                'post_dt',
                'desc',
                [__('entries per page'), 30],
            ],
            'comments' => [
                __('Comments'),
                App::core()->combo()->getCommentsSortbyCombo(),
                'comment_dt',
                'desc',
                [__('comments per page'), 30],
            ],
            'blogs' => [
                __('Blogs'),
                App::core()->combo()->getBlogsSortbyCombo(),
                'blog_upddt',
                'desc',
                [__('blogs per page'), 30],
            ],
            'users' => $users,
            'media' => [
                __('Media manager'),
                [
                    __('Name') => 'name',
                    __('Date') => 'date',
                    __('Size') => 'size',
                ],
                'name',
                'asc',
                [__('media per page'), 30],
            ],
            'search' => [
                __('Search'),
                null,
                null,
                null,
                [__('results per page'), 20],
            ],
        ];
    }

    /**
     * Get sorts filters users preference.
     *
     * @return ArrayObject The filters
     */
    public function getUserFilters(): ArrayObject
    {
        if (null === $this->sorts) {
            $sorts = $this->getDefaultFilters();
            $sorts = new ArrayObject($sorts);

            // --BEHAVIOR-- adminFiltersLists
            App::core()->behavior()->call('adminFiltersLists', $sorts);

            $sorts_user = @App::core()->user()->preference()->get('interface')->get('sorts');
            if (is_array($sorts_user)) {
                foreach ($sorts_user as $stype => $sdata) {
                    if (!isset($sorts[$stype])) {
                        continue;
                    }
                    if (null !== $sorts[$stype][1] && in_array($sdata[0], $sorts[$stype][1])) {
                        $sorts[$stype][2] = $sdata[0];
                    }
                    if (null !== $sorts[$stype][3] && in_array($sdata[1], ['asc', 'desc'])) {
                        $sorts[$stype][3] = $sdata[1];
                    }
                    if (is_array($sorts[$stype][4]) && is_numeric($sdata[2]) && 0 < $sdata[2]) {
                        $sorts[$stype][4][1] = abs($sdata[2]);
                    }
                }
            }
            $this->sorts = $sorts;
        }

        return $this->sorts;
    }

    /**
     * Get sorts filters users preference for a given type.
     *
     * @param string $type The filter list type
     *
     * @return array<string,mixed> The typed filter
     */
    public function getUserFiltersType(string $type): array
    {
        $this->getUserFilters();

        return $this->sorts[$type] ?? [];
    }

    /**
     * Get sortby sorts filters users preference for a given type.
     *
     * @param string $type The filter list type
     *
     * @return string The typed filter sortby value
     */
    public function getUserFiltersSortby(string $type): string
    {
        $this->getUserFilters();

        return isset($this->sorts[$type]) && null !== $this->sorts[$type][2] ? $this->sorts[$type][2] : '';
    }

    /**
     * Get order sorts filters users preference for a given type.
     *
     * @param string $type The filter list type
     *
     * @return string The typed filter order value
     */
    public function getUserFiltersOrder(string $type): string
    {
        $this->getUserFilters();

        return isset($this->sorts[$type]) && null !== $this->sorts[$type][3] ? $this->sorts[$type][3] : '';
    }

    /**
     * Get nb sorts filters users preference for a given type.
     *
     * @param string $type The filter list type
     *
     * @return int The typed filter nb value
     */
    public function getUserFiltersNb(string $type): int
    {
        $this->getUserFilters();

        return isset($this->sorts[$type]) && is_array($this->sorts[$type][4]) ? abs((int) $this->sorts[$type][4][1]) : 20;
    }
}
