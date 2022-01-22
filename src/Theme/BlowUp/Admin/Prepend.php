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

use Dotclear\Core\Core;

use Dotclear\Admin\Notices;
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

    public static function checkModule(Core $core): bool
    {
        return true;
    }

    public static function loadModule(Core $core): void
    {
        $core->behaviors->add('adminCurrentThemeDetails', [__CLASS__, 'behaviorAdminCurrentThemeDetails']);
    }

    public static function installModule(Core $core): ?bool
    {
        $core->blog->settings->addNamespace('themes');
        $core->blog->settings->themes->put('blowup_style', '', 'string', 'Blow Up  custom style', false);

        return true;
    }

    public static function behaviorAdminCurrentThemeDetails(Core $core, AbstractDefine $module): string
    {
        return $module->id() == 'BlowUp' && $core->auth->check('admin', $core->blog->id) ?
            '<p><a href="' . $core->adminurl->get('admin.plugin.BlowUp') . '" class="button submit">' . __('Configure theme') . '</a></p>'
            : '';
    }
}
