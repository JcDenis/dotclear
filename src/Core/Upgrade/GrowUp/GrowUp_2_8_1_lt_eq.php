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
class GrowUp_2_8_1_lt_eq
{
    public static function init(bool $cleanup_sessions): bool
    {
        # switch from jQuery 1.11.1 to 1.11.2
        $strReq = 'UPDATE ' . App::db()->con()->prefix() . App::blogWorkspace()::NS_TABLE_NAME .
            " SET setting_value = '1.11.3' " .
            " WHERE setting_id = 'jquery_version' " .
            " AND setting_ns = 'system' " .
            " AND setting_value = '1.11.1' ";
        App::db()->con()->execute($strReq);
        # Some new settings should be initialized, prepare db queries
        $strReq = 'INSERT INTO ' . App::db()->con()->prefix() . App::blogWorkspace()::NS_TABLE_NAME .
            ' (setting_id,setting_ns,setting_value,setting_type,setting_label)' .
            ' VALUES(\'%s\',\'system\',\'%s\',\'boolean\',\'%s\')';
        App::db()->con()->execute(sprintf($strReq, 'no_search', '0', 'Disable internal search system'));

        return $cleanup_sessions;
    }
}
