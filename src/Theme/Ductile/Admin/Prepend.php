<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Theme\Ductile\Admin;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependAdmin;
use Dotclear\Module\AbstractDefine;

/**
 * Admin prepend for theme DUctile.
 *
 * \Dotclear\Theme\Ductile\Admin\Prepend
 *
 * @ingroup  Theme Ductile
 */
class Prepend extends AbstractPrepend
{
    use TraitPrependAdmin;

    public function loadModule(): void
    {
        dotclear()->behavior()->add('adminCurrentThemeDetails', function (AbstractDefine $module): string {
            return $module->id() == 'Ductile' && dotclear()->user()->check('admin', dotclear()->blog()->id) ?
                '<p><a href="' . dotclear()->adminurl()->get('admin.plugin.Ductile') . '" class="button submit">' . __('Configure theme') . '</a></p>'
                : '';
        });
    }
}
