<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\CKEditor\Admin;

// Dotclear\Plugin\CKEditor\Admin\Prepend
use Dotclear\App;
use Dotclear\Modules\ModulePrepend;

/**
 * Admin prepend for plugin CKEditor.
 *
 * @ingroup  Plugin CKEditor
 */
class Prepend extends ModulePrepend
{
    public function loadModule(): void
    {
        // Menu and favs
        $this->addStandardMenu('Plugins');
        // $this->addStandardFavorites('admin');

        // Settings
        if (!App::core()->blog()->settings()->getGroup('dcckeditor')->getSetting('active')) {
            // return;
        }

        // Admin url for post js
        App::core()->adminurl()->register(
            'admin.plugin.CKEditorPost',
            'Dotclear\\Plugin\\CKEditor\\Admin\\HandlerPost'
        );

        // Formater
        App::core()->formater()->addEditorFormater('CKEditor', 'xhtml', fn ($s) => $s);

        // Behaviors
        new CKEditorBehavior();
    }

    public function installModule(): ?bool
    {
        $s = App::core()->blog()->settings()->getGroup('dcckeditor');

        $s->putSetting('active', true, 'boolean', 'CKEditor plugin activated?', false, true);
        $s->putSetting('alignment_buttons', true, 'boolean', 'Add alignment buttons?', false, true);
        $s->putSetting('list_buttons', true, 'boolean', 'Add list buttons?', false, true);
        $s->putSetting('textcolor_button', false, 'boolean', 'Add text color button?', false, true);
        $s->putSetting('background_textcolor_button', false, 'boolean', 'Add background text color button?', false, true);
        $s->putSetting('cancollapse_button', false, 'boolean', 'Add collapse button?', false, true);
        $s->putSetting('format_select', true, 'boolean', 'Add format selection?', false, true);
        $s->putSetting('format_tags', 'p;h1;h2;h3;h4;h5;h6;pre;address', 'string', 'Custom formats', false, true);
        $s->putSetting('table_button', false, 'boolean', 'Add table button?', false, true);
        $s->putSetting('clipboard_buttons', false, 'boolean', 'Add clipboard buttons?', false, true);
        $s->putSetting('action_buttons', true, 'boolean', 'Add undo/redo buttons?', false, true);
        $s->putSetting('disable_native_spellchecker', true, 'boolean', 'Disables the built-in spell checker if the browser provides one?', false, true);

        return true;
    }
}
