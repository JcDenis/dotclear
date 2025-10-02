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
 *
 * @todo switch to SqlStatement
 */
class GrowUp_2_15_1_lt
{
    public static function init(bool $cleanup_sessions): bool
    {
        // Remove unsafe-inline from CSP script directives
        $strReq = 'UPDATE ' . App::db()->con()->prefix() . App::blogWorkspace()::NS_TABLE_NAME .
            " SET setting_value = REPLACE(setting_value, '''unsafe-inline''', '') " .
            " WHERE setting_id = 'csp_admin_script' " .
            " AND setting_ns = 'system' ";
        App::db()->con()->execute($strReq);

        return $cleanup_sessions;
    }
}
