<?php
/**
 * @class Dotclear\Admin\Filter
 * @brief Admin list filters library
 *
 * Dotclear utility class that provides reuseable list filters
 * Returned null or DefaultFilter instance
 * Should be used with Filter
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @since 2.20
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin;

use Dotclear\Core\Core;

use Dotclear\Admin\Filter\DefaultFilter;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Filters
{
    /**
     * Common default input field
     */
    public static function getInputFilter(string $id, string $title, ?string $param = null): DefaultFilter
    {
        return (new DefaultFilter($id))
            ->param($param ?: $id)
            ->form('input')
            ->title($title);
    }

    /**
     * Common default select field
     */
    public static function getSelectFilter(string $id, string $title, array $options, ?string $param = null): ?DefaultFilter
    {
        if (empty($options)) {
            return null;
        }

        return (new DefaultFilter($id))
            ->param($param ?: $id)
            ->title($title)
            ->options($options);
    }

    /**
     * Common page filter (no field)
     */
    public static function getPageFilter(string $id = 'page'): DefaultFilter
    {
        return (new DefaultFilter($id))
            ->value(!empty($_GET[$id]) ? max(1, (integer) $_GET[$id]) : 1)
            ->param('limit', function ($f) { return [(($f[0] - 1) * $f['nb']), $f['nb']]; });
    }

    /**
     * Common search field
     */
    public static function getSearchFilter(): DefaultFilter
    {
        return (new DefaultFilter('q'))
            ->param('q', function ($f) { return $f['q']; })
            ->form('input')
            ->title(__('Search:'))
            ->prime(true);
    }
}
