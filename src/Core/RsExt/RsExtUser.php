<?php
declare(strict_types=1);

namespace Dotclear\Core\RsExt;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class RsExtUser
{
    private static $sortfield;
    private static $sortsign;

    /**
     * Returns a user option.
     *
     * @param      record  $rs       Invisible parameter
     * @param      string  $name     The name of option
     *
     * @return     mixed
     */
    public static function option($rs, $name)
    {
        $options = self::options($rs);

        if (isset($options[$name])) {
            return $options[$name];
        }
    }

    /**
     * Returns all user options.
     *
     * @param      record  $rs       Invisible parameter
     *
     * @return     array
     */
    public static function options($rs)
    {
        $options = @unserialize($rs->user_options);
        if (is_array($options)) {
            return $options;
        }

        return [];
    }

    /**
     * Converts this record to a {@link extStaticRecord} instance.
     *
     * @param      record  $rs       Invisible parameter
     *
     * @return     extStaticRecord  The extent static record.
     */
    public static function toExtStatic($rs)
    {
        if ($rs instanceof RsExtStaticRecord) {
            return $rs;
        }

        return new RsExtStaticRecord($rs);
    }
}

