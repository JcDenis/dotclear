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

use Dotclear\Core\Upgrade\Upgrade;

/**
 * @brief   Upgrade step.
 */
class GrowUp_2_9_1_lt_eq
{
    public static function init(bool $cleanup_sessions): bool
    {
        # Some settings and prefs should be moved from string to array
        Upgrade::prefs2array('dashboard', 'favorites');
        Upgrade::prefs2array('interface', 'media_last_dirs');

        return $cleanup_sessions;
    }
}
