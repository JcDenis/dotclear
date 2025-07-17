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
use Dotclear\Core\Upgrade\Upgrade;

/**
 * @brief   Upgrade step.
 */
class GrowUp_2_9_lt_eq
{
    public static function init(bool $cleanup_sessions): bool
    {
        # Some new settings should be initialized, prepare db queries
        $strReq = 'INSERT INTO ' . App::con()->prefix() . App::blogWorkspace()::NS_TABLE_NAME .
            ' (setting_id,setting_ns,setting_value,setting_type,setting_label)' .
            ' VALUES(\'%s\',\'system\',\'%s\',\'%s\',\'%s\')';
        App::con()->execute(
            sprintf($strReq, 'media_video_width', '400', 'integer', 'Media video insertion width')
        );
        App::con()->execute(
            sprintf($strReq, 'media_video_height', '300', 'integer', 'Media video insertion height')
        );
        App::con()->execute(
            sprintf($strReq, 'media_flash_fallback', '1', 'boolean', 'Flash player fallback for audio and video media')
        );

        # Some settings and prefs should be moved from string to array
        Upgrade::settings2array('system', 'date_formats');
        Upgrade::settings2array('system', 'time_formats');
        Upgrade::settings2array('antispam', 'antispam_filters');
        Upgrade::settings2array('pings', 'pings_uris');
        Upgrade::settings2array('system', 'simpleMenu');
        Upgrade::prefs2array('dashboard', 'favorites');

        return $cleanup_sessions;
    }
}
