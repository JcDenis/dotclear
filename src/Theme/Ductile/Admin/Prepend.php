<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Theme\Ductile\Admin;

// Dotclear\Theme\Ductile\Admin\Prepend
use Dotclear\App;
use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependAdmin;
use Dotclear\Module\ModuleDefine;

/**
 * Admin prepend for theme Ductile.
 *
 * @ingroup  Theme Ductile
 */
class Prepend extends AbstractPrepend
{
    use TraitPrependAdmin;

    public function loadModule(): void
    {
        App::core()->behavior()->add('adminCurrentThemeDetails', function (ModuleDefine $module): string {
            return $module->id() == $this->define()->id() && App::core()->user()->check('admin', App::core()->blog()->id) ?
                '<p><a href="' . App::core()->adminurl()->get('admin.plugin.' . $this->define()->id()) . '" class="button submit">' . __('Configure theme') . '</a></p>'
                : '';
        });
    }
}
