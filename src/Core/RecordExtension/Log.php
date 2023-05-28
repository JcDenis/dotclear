<?php
/**
 * @brief Extent log Record class.
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\RecordExtension;

use dcUtils;
use Dotclear\Database\MetaRecord;

class Log
{
    /**
     * Gets the user common name.
     *
     * @param   MetaRecord  $rs     Invisible parameter
     *
     * @return  string  The user common name.
     */
    public static function getUserCN(MetaRecord $rs): string
    {
        $user = 'unknown';

        if (is_string($rs->f('user_id'))
            && is_string($rs->f('user_name'))
            && is_string($rs->f('user_firstname'))
            && is_string($rs->f('user_displayname'))
        ) {
            $user = dcUtils::getUserCN(
                $rs->f('user_id'),
                $rs->f('user_name'),
                $rs->f('user_firstname'),
                $rs->f('user_displayname')
            );
        }

        return $user === 'unknown' ? __('unknown') : $user;
    }
}