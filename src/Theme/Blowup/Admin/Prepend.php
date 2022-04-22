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
use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependAdmin;
use Dotclear\Module\AbstractDefine;

/**
 * Admin prepend for theme Blowup.
 *
 * @ingroup  Theme Blowup
 */
class Prepend extends AbstractPrepend
{
    use TraitPrependAdmin;

    public function loadModule(): void
    {
        dotclear()->behavior()->add('adminCurrentThemeDetails', function (AbstractDefine $module): string {
            return 'Blowup' == $module->id() && dotclear()->user()->check('admin', dotclear()->blog()->id) ?
                '<p><a href="' . dotclear()->adminurl()->get('admin.plugin.Blowup') . '" class="button submit">' . __('Configure theme') . '</a></p>'
                : '';
        });
    }

    public function installModule(): ?bool
    {
        dotclear()->blog()->settings()->get('themes')->put('Blowup_style', '', 'string', 'Blow Up  custom style', false);

        return true;
    }
}
