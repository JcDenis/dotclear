<?php

/**
 * @package     Dotclear
 * @subpackage  Upgrade
 *
 * @copyright   Olivier Meunier & Association Dotclear
 * @copyright   AGPL-3.0
 */
declare(strict_types=1);

namespace Dotclear\Core\Upgrade\GrowUp;

use Dotclear\App;

/**
 * @brief   Upgrade step.
 */
class GrowUp_2_16_9_lt
{
    public static function init(bool $cleanup_sessions): bool
    {
        // Fix 87,5% which should be 87.5% in pref for htmlfontsize
        $strReq = 'UPDATE ' . App::con()->prefix() . App::userWorkspace()::WS_TABLE_NAME .
            " SET pref_value = REPLACE(pref_value, '87,5%', '87.5%') " .
            " WHERE pref_id = 'htmlfontsize' " .
            " AND pref_ws = 'interface' ";
        App::con()->execute($strReq);

        return $cleanup_sessions;
    }
}
