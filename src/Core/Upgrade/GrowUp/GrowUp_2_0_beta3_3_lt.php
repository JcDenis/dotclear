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
class GrowUp_2_0_beta3_3_lt
{
    public static function init(bool $cleanup_sessions): bool
    {
        // Populate media_dir field (since 2.0-beta3.3)
        $strReq = 'SELECT media_id, media_file FROM ' . App::db()->con()->prefix() . App::postMedia()::MEDIA_TABLE_NAME . ' ';
        $rs_m   = App::db()->con()->select($strReq);
        while ($rs_m->fetch()) {
            $cur            = App::media()->openMediaCursor();
            $cur->media_dir = dirname($rs_m->media_file);
            $cur->update('WHERE media_id = ' . (int) $rs_m->media_id);
        }

        return $cleanup_sessions;
    }
}
