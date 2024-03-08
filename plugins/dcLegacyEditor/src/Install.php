<?php
/**
 * @package     Dotclear
 *
 * @copyright   Olivier Meunier & Association Dotclear
 * @copyright   AGPL-3.0
 */
declare(strict_types=1);

namespace Dotclear\Plugin\dcLegacyEditor;

use Dotclear\App;
use Dotclear\Core\Process;

/**
 * @brief   The module install process.
 * @ingroup dcLegacyEditor
 */
class Install extends Process
{
    public static function init(): bool
    {
        return self::status(My::checkContext(My::INSTALL));
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        // Rename settings namespace
        if (version_compare(App::version()->getVersion(My::id()), '1.0', '<=')
            && App::blog()->settings()->exists('dclegacyeditor')
        ) {
            App::blog()->settings()->delWorkspace(My::id());
            App::blog()->settings()->renWorkspace('dclegacyeditor', My::id());
        }

        My::settings()->put('active', true, 'boolean', 'dcLegacyEditor plugin activated ?', false, true);

        return true;
    }
}
