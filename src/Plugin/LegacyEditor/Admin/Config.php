<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\LegacyEditor\Admin;

// Dotclear\Plugin\LegacyEditor\Admin\Config
use Dotclear\App;
use Dotclear\Helper\Html\Form;
use Dotclear\Modules\ModuleConfig;

/**
 * Admin config page for plugin LegacyEditor.
 *
 * @ingroup  Plugin LegacyEditor
 */
class Config extends ModuleConfig
{
    public function setConfiguration($post): void
    {
        App::core()->blog()->settings()->getGroup('LegacyEditor')->putSetting('active', !empty($post['LegacyEditor_active']), 'boolean');

        App::core()->notice()->addSuccessNotice(__('The configuration has been updated.'));
        $this->redirect();
    }

    public function getConfiguration(): void
    {
        echo '<div class="fieldset">' .
        '<h3>' . __('Plugin activation') . '</h3>' .

        '<p><label class="classic" for="LegacyEditor_active">' .
        Form::checkbox('LegacyEditor_active', 1, (bool) App::core()->blog()->settings()->getGroup('LegacyEditor')->getSetting('active')) .
        __('Enable LegacyEditor plugin') . '</label></p>' .

        '</div>';
    }
}
