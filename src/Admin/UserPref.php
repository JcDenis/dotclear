<?php
/**
 * @class Dotclear\Admin\UserPref
 * @brief Admin user preference library
 *
 * Dotclear utility class that provides reuseable user preference
 * across all admin page with lists and filters
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin;

use ArrayObject;

use Dotclear\Core\Core;

use Dotclear\Admin\Combos;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class UserPref
{
    /** @var    Core            Core instance */
    protected $core;

    /** @var    ArrayObject     Sorts filters preferences*/
    protected $sorts = null;

    /**
     * Constructor
     *
     * @param   Core    $core   Core instance
     */
    public function __construct(Core $core)
    {
        $this->core = $core;
    }

    /**
     * Get default columns
     *
     * @return  array   The default columns
     */
    public function getDefaultColumns(): array
    {
        return ['posts' => [__('Posts'), [
            'date'       => [true, __('Date')],
            'category'   => [true, __('Category')],
            'author'     => [true, __('Author')],
            'comments'   => [true, __('Comments')],
            'trackbacks' => [true, __('Trackbacks')]
        ]]];
    }

    /**
     * Get users columns preferences
     *
     * @param   string|null     $type       The columns type
     * @param   array|null      $columns    The columns list
     *
     * @return  array|ArrayObject           The user columns
     */
    public function getUserColumns(string $type = null, ?array $columns = null): mixed
    {
        # Get default colums (admin lists)
        $cols = $this->getDefaultColumns();
        $cols = new ArrayObject($cols);

        # --BEHAVIOR-- adminColumnsLists
        $this->core->behaviors->call('adminColumnsLists', $cols);

        # Load user settings
        $cols_user = @$this->core->auth->user_prefs->interface->cols;
        if (is_array($cols_user) || $cols_user instanceof ArrayObject) {
            foreach ($cols_user as $ct => $cv) {
                foreach ($cv as $cn => $cd) {
                    if (isset($cols[$ct][1][$cn])) {
                        $cols[$ct][1][$cn][0] = $cd;

                        # remove unselected columns if type is given
                        if (!$cd && !empty($type) && !empty($columns) && $ct == $type && isset($columns[$cn])) {
                            unset($columns[$cn]);
                        }
                    }
                }
            }
        }
        if ($columns !== null) {
            return $columns;
        }
        if ($type !== null) {
            return $cols[$type] ?? [];
        }

        return $cols;
    }

    /**
     * Get default filters
     *
     * @return  array   The default filters
     */
    public function getDefaultFilters(): array
    {
        $users = [null, null, null, null, null];
        if ($this->core->auth->isSuperAdmin()) {
            $users = [
                __('Users'),
                Combos::getUsersSortbyCombo(),
                'user_id',
                'asc',
                [__('users per page'), 30]
            ] ;
        }

        return [
            'posts' => [
                __('Posts'),
                Combos::getPostsSortbyCombo(),
                'post_dt',
                'desc',
                [__('entries per page'), 30]
            ],
            'comments' => [
                __('Comments'),
                Combos::getCommentsSortbyCombo(),
                'comment_dt',
                'desc',
                [__('comments per page'), 30]
            ],
            'blogs' => [
                __('Blogs'),
                Combos::getBlogsSortbyCombo(),
                'blog_upddt',
                'desc',
                [__('blogs per page'), 30]
            ],
            'users' => $users,
            'media' => [
                __('Media manager'),
                [
                    __('Name') => 'name',
                    __('Date') => 'date',
                    __('Size') => 'size'
                ],
                'name',
                'asc',
                [__('media per page'), 30]
            ],
            'search' => [
                __('Search'),
                null,
                null,
                null,
                [__('results per page'), 20]
            ]
        ];
    }

    /**
     * Get sorts filters users preference for a given type
     *
     * @param   string|null     $type       The filter list type
     * @param   string|null     $option     The filter list option
     *
     * @return  string|array|ArrayObject    Filters or typed filter or field value(s)
     */
    public function getUserFilters(?string $type = null, ?string $option = null): mixed
    {
        if ($this->sorts === null) {
            $sorts = $this->getDefaultFilters();
            $sorts = new ArrayObject($sorts);

            # --BEHAVIOR-- adminFiltersLists
            $this->core->behaviors->call('adminFiltersLists', $sorts);

            if ($this->core->auth->user_prefs->interface === null) {
                $this->core->auth->user_prefs->addWorkspace('interface');
            }
            $sorts_user = @$this->core->auth->user_prefs->interface->sorts;
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
                    if (is_array($sorts[$stype][4]) && is_numeric($sdata[2]) && $sdata[2] > 0) {
                        $sorts[$stype][4][1] = abs($sdata[2]);
                    }
                }
            }
            $this->sorts = $sorts;
        }

        if (null === $type) {
            return $this->sorts;
        } elseif (isset($this->sorts[$type])) {
            if (null === $option) {
                return $this->sorts[$type];
            }
            if ($option == 'sortby' && null !== $this->sorts[$type][2]) {
                return $this->sorts[$type][2];
            }
            if ($option == 'order' && null !== $this->sorts[$type][3]) {
                return $this->sorts[$type][3];
            }
            if ($option == 'nb' && is_array($this->sorts[$type][4])) {
                return abs((integer) $this->sorts[$type][4][1]);
            }
        }

        return null;
    }
}
