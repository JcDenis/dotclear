<?php
/**
 * @class Dotclear\Plugin\Breadcrumb\Admin\Prepend
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginBreadcrumb
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Breadcrumb\Admin;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependAdmin;

use Dotclear\Core\Settings;
use Dotclear\Html\Form;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Prepend extends AbstractPrepend
{
    use TraitPrependAdmin;

    public static function checkModule(): bool
    {
        return true;
    }

    public static function loadModule(): void
    {
        dcCore()->behaviors->add('adminBlogPreferencesForm', [__CLASS__, 'behaviorAdminBlogPreferencesForm']);
        dcCore()->behaviors->add('adminBeforeBlogSettingsUpdate', [__CLASS__, 'behaviorAdminBeforeBlogSettingsUpdate']);
    }

    public static function behaviorAdminBlogPreferencesForm(Settings $settings): void
    {
        $settings->addNameSpace('breadcrumb');
        echo
        '<div class="fieldset"><h4 id="breadcrumb_params">' . __('Breadcrumb') . '</h4>' .
        '<p><label class="classic">' .
        Form::checkbox('breadcrumb_enabled', '1', $settings->breadcrumb->breadcrumb_enabled) .
        __('Enable breadcrumb for this blog') . '</label></p>' .
        '<p class="form-note">' . __('The {{tpl:Breadcrumb [separator=" &amp;rsaquo; "]}} tag should be present (or inserted if not) in the template.') . '</p>' .
        Form::checkbox('breadcrumb_alone', '1', $settings->breadcrumb->breadcrumb_alone) .
        __('Do not encapsulate breadcrumb in a &lt;p id="breadcrumb"&gt;...&lt;/p&gt; tag.') . '</label></p>' .
            '</div>';
    }

    public static function behaviorAdminBeforeBlogSettingsUpdate(Settings $settings): void
    {
        $settings->addNameSpace('breadcrumb');
        $settings->breadcrumb->put('breadcrumb_enabled', !empty($_POST['breadcrumb_enabled']), 'boolean');
        $settings->breadcrumb->put('breadcrumb_alone', !empty($_POST['breadcrumb_alone']), 'boolean');
    }
}
