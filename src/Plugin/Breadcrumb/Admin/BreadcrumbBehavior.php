<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Breadcrumb\Admin;

// Dotclear\Plugin\Breadcrumb\Admin\BreadcrumbBehavior
use Dotclear\App;
use Dotclear\Core\Blog\Settings\SettingsGroup;
use Dotclear\Helper\GPC\GPC;
use Dotclear\Helper\Html\Form;

/**
 * Admin behaviors for plugin Breacrumb.
 *
 * @ingroup  Plugin Breadcrumb Behavior
 */
class BreadcrumbBehavior
{
    public function __construct()
    {
        App::core()->behavior('adminAfterGetBlogPreferencesForm')->add([$this, 'adminAfterGetBlogPreferencesForm']);
        App::core()->behavior('adminBeforeUpdateBlogSettings')->add([$this, 'adminBeforeUpdateBlogSettings']);
    }

    public function adminAfterGetBlogPreferencesForm(SettingsGroup $settings): void
    {
        $group = App::core()->blog()->settings('breadcrumb');

        echo '<div class="fieldset"><h4 id="breadcrumb_params">' . __('Breadcrumb') . '</h4>' .
        '<p><label class="classic">' .
        Form::checkbox('breadcrumb_enabled', '1', $group->getSetting('breadcrumb_enabled')) .
        __('Enable breadcrumb for this blog') . '</label></p>' .
        '<p class="form-note">' . __('The {{tpl:Breadcrumb [separator=" &amp;rsaquo; "]}} tag should be present (or inserted if not) in the template.') . '</p>' .
        Form::checkbox('breadcrumb_alone', '1', $group->getSetting('breadcrumb_alone')) .
        __('Do not encapsulate breadcrumb in a &lt;p id="breadcrumb"&gt;...&lt;/p&gt; tag.') . '</label></p>' .
            '</div>';
    }

    public function adminBeforeUpdateBlogSettings(SettingsGroup $settings): void
    {
        $group = App::core()->blog()->settings('breadcrumb');
        $group->putSetting('breadcrumb_enabled', !GPC::post()->empty('breadcrumb_enabled'), 'boolean');
        $group->putSetting('breadcrumb_alone', !GPC::post()->empty('breadcrumb_alone'), 'boolean');
    }
}
