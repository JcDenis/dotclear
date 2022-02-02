<?php
/**
 * @class Dotclear\Plugin\LegacyEditor\Admin\Prepend
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

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependAdmin;

use Dotclear\Html\wiki2xhtml;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Prepend extends AbstractPrepend
{
    use TraitPrependAdmin;

    public static function loadModule(): void
    {
        $self_ns = dcCore()->blog->settings->addNamespace('LegacyEditor');

        if ($self_ns->active) {
            if (!(dcCore()->wiki2xhtml instanceof wiki2xhtml)) {
                dcCore()->initWikiPost();
            }

            dcCore()->addEditorFormater('LegacyEditor', 'xhtml', fn ($s) => $s);
            dcCore()->addEditorFormater('LegacyEditor', 'wiki', [dcCore()->wiki2xhtml, 'transform']);

            $class = __NAMESPACE__ . '\\Behaviors';
            dcCore()->behaviors->add('adminPostEditor', [$class, 'adminPostEditor']);
            dcCore()->behaviors->add('adminPopupMedia', [$class, 'adminPopupMedia']);
            dcCore()->behaviors->add('adminPopupLink', [$class, 'adminPopupLink']);
            dcCore()->behaviors->add('adminPopupPosts', [$class, 'adminPopupPosts']);
        }
    }

    public static function installModule(): ?bool
    {
        $settings = dcCore()->blog->settings;
        $settings->addNamespace('LegacyEditor');
        $settings->LegacyEditor->put('active', true, 'boolean', 'LegacyEditor plugin activated ?', false, true);

        return true;
    }
}
