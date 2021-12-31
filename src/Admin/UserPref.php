<?php
/**
 * @class Dotclear\Admin\Menu
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
use Dotclear\Core\Utils;

use Dotclear\Admin\Combos;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class UserPref
{
    /** @var Core core instance */
    public static $core;

    /** @var ArrayObject columns preferences */
    protected static $cols = null;

    /** @var ArrayObject sorts filters preferences*/
    protected static $sorts = null;

    public static function getDefaultColumns()
    {
        return ['posts' => [__('Posts'), [
            'date'       => [true, __('Date')],
            'category'   => [true, __('Category')],
            'author'     => [true, __('Author')],
            'comments'   => [true, __('Comments')],
            'trackbacks' => [true, __('Trackbacks')]
        ]]];
    }

    public static function getUserColumns($type = null, $columns = null)
    {
        # Get default colums (admin lists)
        $cols = self::getDefaultColumns();
        $cols = new ArrayObject($cols);

        # --BEHAVIOR-- adminColumnsLists
        self::$core->behaviors->call('adminColumnsLists', $cols);

        # Load user settings
        $cols_user = @self::$core->auth->user_prefs->interface->cols;
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

    public static function getDefaultFilters()
    {
        $users = [null, null, null, null, null];
        if (self::$core->auth->isSuperAdmin()) {
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
     * @param       string      $type   The filter list type
     * @return      mixed               Filters or typed filter or field value(s)
     */
    public static function getUserFilters($type = null, $option = null)
    {
        if (self::$sorts === null) {
            $sorts = self::getDefaultFilters();
            $sorts = new ArrayObject($sorts);

            # --BEHAVIOR-- adminFiltersLists
            self::$core->behaviors->call('adminFiltersLists', $sorts);

            if (self::$core->auth->user_prefs->interface === null) {
                self::$core->auth->user_prefs->addWorkspace('interface');
            }
            $sorts_user = @self::$core->auth->user_prefs->interface->sorts;
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
            self::$sorts = $sorts;
        }

        if (null === $type) {
            return self::$sorts;
        } elseif (isset(self::$sorts[$type])) {
            if (null === $option) {
                return self::$sorts[$type];
            }
            if ($option == 'sortby' && null !== self::$sorts[$type][2]) {
                return self::$sorts[$type][2];
            }
            if ($option == 'order' && null !== self::$sorts[$type][3]) {
                return self::$sorts[$type][3];
            }
            if ($option == 'nb' && is_array(self::$sorts[$type][4])) {
                return abs((integer) self::$sorts[$type][4][1]);
            }
        }

        return null;
    }
}
