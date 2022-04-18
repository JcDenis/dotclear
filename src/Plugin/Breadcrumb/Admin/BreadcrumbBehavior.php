<?php
/**
 * @note Dotclear\Plugin\Breadcrumb\Admin\BreadcrumbBehavior
 * @brief Dotclear Plugins class
 *
 * @ingroup  PluginBreadcrumb
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Breadcrumb\Admin;

use Dotclear\Core\Blog\Settings\Settings;
use Dotclear\Helper\Html\Form;

class BreadcrumbBehavior
{
    public function __construct()
    {
        dotclear()->behavior()->add('adminBlogPreferencesForm', [$this, 'behaviorAdminBlogPreferencesForm']);
        dotclear()->behavior()->add('adminBeforeBlogSettingsUpdate', [$this, 'behaviorAdminBeforeBlogSettingsUpdate']);
    }

    public function behaviorAdminBlogPreferencesForm(Settings $settings): void
    {
        echo '<div class="fieldset"><h4 id="breadcrumb_params">' . __('Breadcrumb') . '</h4>' .
        '<p><label class="classic">' .
        Form::checkbox('breadcrumb_enabled', '1', $settings->get('breadcrumb')->get('breadcrumb_enabled')) .
        __('Enable breadcrumb for this blog') . '</label></p>' .
        '<p class="form-note">' . __('The {{tpl:Breadcrumb [separator=" &amp;rsaquo; "]}} tag should be present (or inserted if not) in the template.') . '</p>' .
        Form::checkbox('breadcrumb_alone', '1', $settings->get('breadcrumb')->get('breadcrumb_alone')) .
        __('Do not encapsulate breadcrumb in a &lt;p id="breadcrumb"&gt;...&lt;/p&gt; tag.') . '</label></p>' .
            '</div>';
    }

    public function behaviorAdminBeforeBlogSettingsUpdate(Settings $settings): void
    {
        $settings->get('breadcrumb')->put('breadcrumb_enabled', !empty($_POST['breadcrumb_enabled']), 'boolean');
        $settings->get('breadcrumb')->put('breadcrumb_alone', !empty($_POST['breadcrumb_alone']), 'boolean');
    }
}
