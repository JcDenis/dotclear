<?php
/**
 * @class Dotclear\Theme\Blowup\Admin\Prepend
 * @brief Dotclear Theme class
 *
 * @package Dotclear
 * @subpackage ThemeResume
 *
 * @copyright Philippe aka amalgame
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Theme\Blowup\Admin;

use function Dotclear\core;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependAdmin;
use Dotclear\Module\AbstractDefine;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Prepend extends AbstractPrepend
{
    use TraitPrependAdmin;

    public static function loadModule(): void
    {
        core()->behaviors->add('adminCurrentThemeDetails', function (AbstractDefine $module): string {
            return $module->id() == 'Blowup' && core()->auth->check('admin', core()->blog->id) ?
                '<p><a href="' . core()->adminurl->get('admin.plugin.Blowup') . '" class="button submit">' . __('Configure theme') . '</a></p>'
                : '';
        });
    }

    public static function installModule(): ?bool
    {
        core()->blog->settings->addNamespace('themes');
        core()->blog->settings->themes->put('Blowup_style', '', 'string', 'Blow Up  custom style', false);

        return true;
    }
}
