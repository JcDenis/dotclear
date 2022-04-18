<?php
/**
 * @note Dotclear\Theme\Ductile\Admin\Prepend
 * @brief Dotclear Theme class
 *
 * @ingroup  ThemeResume
 *
 * @copyright Philippe aka amalgame
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Theme\Ductile\Admin;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependAdmin;
use Dotclear\Module\AbstractDefine;

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
