<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Meta;

// Dotclear\Core\Meta\MetaParam
use Dotclear\Database\Param;

/**
 * Meta query parameter helper.
 *
 * @ingroup  Core Meta Param
 */
final class MetaParam extends Param
{
    /**
     * Get meta belonging to given meta type.
     *
     * @return null|string The meta type
     */
    public function meta_type(): ?string
    {
        return $this->getCleanedValue('meta_type', 'string');
    }

    /**
     * Get meta belonging to given meta id.
     *
     * @return null|string The meta id
     */
    public function meta_id(): ?string
    {
        return $this->getCleanedValue('meta_id', 'string');
    }

    /**
     * Get meta belonging to given post(s) id(s).
     *
     * @return array<int,int> The post(s) id(s)
     */
    public function post_id(): array
    {
        return $this->getCleanedValues('post_id', 'int');
    }
}
