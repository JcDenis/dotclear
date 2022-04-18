<?php
/**
 * @note Dotclear\Process\Admin\Filter\Filters
 * @brief Admin list filters library
 *
 * Dotclear utility class that provides reuseable list filters
 * Returned null or DefaultFilter instance
 * Should be used with Filter
 *
 * @ingroup  Admin
 *
 * @since 2.20
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Filter;

use Dotclear\Process\Admin\Filter\Filter\DefaultFilter;

class Filters
{
    /**
     * Common default input field.
     */
    public function getInputFilter(string $id, string $title, ?string $param = null): DefaultFilter
    {
        return DefaultFilter::init($id)
            ->param($param ?: $id)
            ->form('input')
            ->title($title)
        ;
    }

    /**
     * Common default select field.
     */
    public function getSelectFilter(string $id, string $title, array $options, ?string $param = null): ?DefaultFilter
    {
        if (empty($options)) {
            return null;
        }

        return DefaultFilter::init($id)
            ->param($param ?: $id)
            ->title($title)
            ->options($options)
        ;
    }

    /**
     * Common page filter (no field).
     */
    public function getPageFilter(string $id = 'page'): DefaultFilter
    {
        return DefaultFilter::init($id)
            ->value(!empty($_GET[$id]) ? max(1, (int) $_GET[$id]) : 1)
            ->param('limit', fn ($f) => [(($f[0] - 1) * $f['nb']), $f['nb']])
        ;
    }

    /**
     * Common search field.
     */
    public function getSearchFilter(): DefaultFilter
    {
        return DefaultFilter::init('q')
            ->param('q', fn ($f) => $f['q'])
            ->form('input')
            ->title(__('Search:'))
            ->prime(true)
        ;
    }
}
