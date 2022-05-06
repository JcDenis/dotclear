<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Theme\Blowup\Admin;

// Dotclear\Theme\Blowup\Admin\Prepend
use Dotclear\App;
use Dotclear\Modules\ModuleDefine;
use Dotclear\Modules\ModulePrepend;

/**
 * Admin prepend for theme Blowup.
 *
 * @ingroup  Theme Blowup
 */
class Prepend extends ModulePrepend
{
    public function loadModule(): void
    {
        App::core()->behavior()->add('adminCurrentThemeDetails', function (ModuleDefine $module): string {
            return $this->define()->id() == $module->id() && App::core()->user()->check('admin', App::core()->blog()->id) ?
                '<p><a href="' . App::core()->adminurl()->get('admin.theme.' . $this->define()->id()) . '" class="button submit">' . __('Configure theme') . '</a></p>'
                : '';
        });
    }

    public function installModule(): ?bool
    {
        App::core()->blog()->settings()->get('themes')->put('Blowup_style', '', 'string', 'Blow Up  custom style', false);

        return true;
    }
}
