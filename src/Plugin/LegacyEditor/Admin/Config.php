<?php
/**
 * @class Dotclear\Plugin\LegacyEditor\Admin\Config
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginLegacyEditor
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\LegacyEditor\Admin;

use Dotclear\Helper\Html\Form;
use Dotclear\Module\AbstractConfig;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Config extends AbstractConfig
{
    public function setConfiguration($post): void
    {
        dotclear()->blog()->settings()->LegacyEditor->put('active', !empty($post['LegacyEditor_active']), 'boolean');

        dotclear()->notice()->addSuccessNotice(__('The configuration has been updated.'));
        $this->redirect();
    }

    public function getConfiguration(): void
    {
        echo
        '<div class="fieldset">' .
        '<h3>' . __('Plugin activation') . '</h3>' .

        '<p><label class="classic" for="LegacyEditor_active">' .
        Form::checkbox('LegacyEditor_active', 1, (bool) dotclear()->blog()->settings()->LegacyEditor->active) .
        __('Enable LegacyEditor plugin') . '</label></p>' .

        '</div>';
    }
}
