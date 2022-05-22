<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Blog\Posts;

// Dotclear\Core\Posts\LangsParam
use Dotclear\Database\Param;

/**
 * Posts query parameter helper.
 *
 * @ingroup  Core Post Param
 */
final class LangsParam extends Param
{
    /**
     * Retrieve post count for selected language code.
     *
     * @return null|string The lang code
     */
    public function post_lang(): ?string
    {
        return $this->getCleanedValue('post_lang', 'string');
    }

    /**
     * Get only entries with given type(s).
     *
     * default "post", array for many types and '' for no type
     *
     * @return array<int,string> The post(s) type(s)
     */
    public function post_type(): array
    {
        $types = $this->getCleanedValues('post_type', 'string');
        if (in_array('', $types, true)) {
            return [];
        }

        return empty($types) ? ['post'] : $types;
    }

    /**
     * Get entries with given language code.
     *
     * @return string The lang code
     */
    public function order(string $default = 'desc'): string
    {
        $order = $this->getCleanedValue('post_lang', 'string', $default);

        return in_array($order, ['asc', 'desc']) ? $order : $default;
    }
}
