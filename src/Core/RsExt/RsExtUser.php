<?php
/**
 * @class Dotclear\Core\RsExt\RsExtUser
 * @brief Dotclear user record helpers.
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\RsExt;

use Dotclear\Core\RsExt\RsExtend;
use Dotclear\Core\RsExt\RsExtStaticRecord;

class RsExtUser extends RsExtend
{
    private static $sortfield;
    private static $sortsign;

    /**
     * Returns a user option.
     *
     * @param   string  $name     The name of option
     *
     * @return  mixed
     */
    public function option(string $name)
    {
        $options = $this->options();

        if (isset($options[$name])) {
            return $options[$name];
        }
    }

    /**
     * Returns all user options.
     *
     * @return  array
     */
    public function options(): array
    {
        $options = @unserialize($this->rs->user_options);
        
        return is_array($options) ? $options : [];
    }

    /**
     * Converts this record to a {@link extStaticRecord} instance.
     *
     * @return  extStaticRecord  The extent static record.
     */
    public function toExtStatic(): RsExtStaticRecord
    {
        return ($this->rs instanceof RsExtStaticRecord) ? 
            $this->rs :
            new RsExtStaticRecord($this->rs);
    }
}
