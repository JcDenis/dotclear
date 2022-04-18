<?php
/**
 * @note Dotclear\Core\RsExt\RsExtUser
 * @brief Dotclear user record helpers.
 *
 * @ingroup  Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\RsExt;

use Dotclear\Database\StaticRecord;

class RsExtUser extends RsExtend
{
    /**
     * Returns a user option.
     *
     * @param string $name The name of option
     */
    public function option(string $name): mixed
    {
        $options = $this->options();

        return $options[$name] ?? null;
    }

    /**
     * Returns all user options.
     */
    public function options(): array
    {
        $options = @unserialize($this->rs->f('user_options'));

        return is_array($options) ? $options : [];
    }

    /**
     * Converts this record to a {@link StaticRecord} instance.
     *
     * @return StaticRecord the extent static record
     */
    public function toExtStatic(): StaticRecord
    {
        return ($this->rs instanceof StaticRecord) ?
            $this->rs :
            $this->rs->toStatic();
    }
}
