<?php
/**
 * @class Dotclear\Plugin\LegacyEditor\Admin\Config
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PlugniUserPref
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\LegacyEditor\Admin;

use Dotclear\Module\AbstractConfig;

use Dotclear\Html\Form;
use Dotclear\Network\Http;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Config extends AbstractConfig
{
    public function setConfiguration($post, $redir): void
    {
        dcCore()->blog->settings->addNameSpace('LegacyEditor');
        dcCore()->blog->settings->LegacyEditor->put('active', !empty($post['LegacyEditor_active']), 'boolean');

        dcCore()->notices->addSuccessNotice(__('The configuration has been updated.'));
        Http::redirect($redir);
    }

    public function getConfiguration(): void
    {
        dcCore()->blog->settings->addNamespace('LegacyEditor');

        echo
        '<div class="fieldset">' .
        '<h3>' . __('Plugin activation') . '</h3>' .

        '<p><label class="classic" for="LegacyEditor_active">' .
        Form::checkbox('LegacyEditor_active', 1, (bool) dcCore()->blog->settings->LegacyEditor->active) .
        __('Enable LegacyEditor plugin') . '</label></p>' .

        '</div>';
    }
}