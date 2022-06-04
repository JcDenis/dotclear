<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Blog\Settings;

// Dotclear\Core\Blog\Settings\Setting
use Dotclear\Exception\InvalidValueFormat;

/**
 * Setting representation.
 *
 * @ingroup  Core Setting
 */
final class Setting
{
    private const NS_GROUP_SCHEMA = '/^[a-zA-Z][a-zA-Z0-9]+$/';
    private const NS_ID_SCHEMA    = '/^[a-zA-Z][a-zA-Z0-9_]+$/';

    /**
     * Constructor.
     *
     * @param string $group  The setting group
     * @param string $id     The setting ID
     * @param mixed  $value  The setting value
     * @param string $type   The setting type
     * @param string $label  The setting label
     * @param bool   $global Setting is global
     */
    public function __construct(
        public readonly string $group,
        public readonly string $id,
        public mixed $value,
        public readonly string $type,
        public readonly string $label,
        public readonly bool $global,
    ) {
        if (!preg_match(self::NS_GROUP_SCHEMA, $this->group)) {
            throw new InvalidValueFormat(sprintf(__('Invalid setting Namespace: %s'), $this->group));
        }
        if (!preg_match(self::NS_ID_SCHEMA, $this->id)) {
            throw new InvalidValueFormat(sprintf(__('%s is not a valid setting id'), $this->id));
        }
    }
}
