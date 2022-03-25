<?php
/**
 * @class Dotclear\Plugin\CKEditor\Admin\Prepend
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginCKEditor
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\CKEditor\Admin;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependAdmin;
use Dotclear\Plugin\CKEditor\Admin\CKEditorBehavior;

class Prepend extends AbstractPrepend
{
    use TraitPrependAdmin;

    public function loadModule(): void
    {
        # Menu and favs
        $this->addStandardMenu('Plugins');
        //$this->addStandardFavorites('admin');

        # Settings
        if (!dotclear()->blog()->settings()->dcckeditor->active) {
            //return;
        }

        # Admin url for post js
        dotclear()->adminurl()->register(
            'admin.plugin.CKEditorPost',
            'Dotclear\\Plugin\\CKEditor\\Admin\\HandlerPost'
        );

        # Formater
        dotclear()->formater()->addEditorFormater('CKEditor', 'xhtml', fn ($s) => $s);

        # Behaviors
        new CKEditorBehavior();;
    }

    public function installModule(): ?bool
    {
        $s = dotclear()->blog()->settings()->dcckeditor;

        $s->put('active', true, 'boolean', 'dcCKEditor plugin activated?', false, true);
        $s->put('alignment_buttons', true, 'boolean', 'Add alignment buttons?', false, true);
        $s->put('list_buttons', true, 'boolean', 'Add list buttons?', false, true);
        $s->put('textcolor_button', false, 'boolean', 'Add text color button?', false, true);
        $s->put('background_textcolor_button', false, 'boolean', 'Add background text color button?', false, true);
        $s->put('cancollapse_button', false, 'boolean', 'Add collapse button?', false, true);
        $s->put('format_select', true, 'boolean', 'Add format selection?', false, true);
        $s->put('format_tags', 'p;h1;h2;h3;h4;h5;h6;pre;address', 'string', 'Custom formats', false, true);
        $s->put('table_button', false, 'boolean', 'Add table button?', false, true);
        $s->put('clipboard_buttons', false, 'boolean', 'Add clipboard buttons?', false, true);
        $s->put('action_buttons', true, 'boolean', 'Add undo/redo buttons?', false, true);
        $s->put('disable_native_spellchecker', true, 'boolean', 'Disables the built-in spell checker if the browser provides one?', false, true);

        return true;
    }
}