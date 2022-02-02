<?php
/**
 * @class Dotclear\Theme\BlowUp\Admin\Prepend
 * @brief Dotclear Theme class
 *
 * @package Dotclear
 * @subpackage ThemeResume
 *
 * @copyright Philippe aka amalgame
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Theme\BlowUp\Admin;

use ArrayObject;

use Dotclear\Admin\Page;

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
        dcCore()->behaviors->add('adminCurrentThemeDetails', [__CLASS__, 'behaviorAdminCurrentThemeDetails']);
    }

    public static function installModule(): ?bool
    {
        dcCore()->blog->settings->addNamespace('themes');
        dcCore()->blog->settings->themes->put('blowup_style', '', 'string', 'Blow Up  custom style', false);

        return true;
    }

    public static function behaviorAdminCurrentThemeDetails(AbstractDefine $module): string
    {
        return $module->id() == 'BlowUp' && dcCore()->auth->check('admin', dcCore()->blog->id) ?
            '<p><a href="' . dcCore()->adminurl->get('admin.plugin.BlowUp') . '" class="button submit">' . __('Configure theme') . '</a></p>'
            : '';
    }
}
