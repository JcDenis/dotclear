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

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Prepend extends AbstractPrepend
{
    use TraitPrependAdmin;

    public static function loadModule(): void
    {
        $self_ns = dotclear()->blog->settings->addNamespace('LegacyEditor');

        if ($self_ns->active) {
            dotclear()->initWikiPost();

            dotclear()->formater()->addEditorFormater('LegacyEditor', 'xhtml', fn ($s) => $s);
            dotclear()->formater()->addEditorFormater('LegacyEditor', 'wiki', [dotclear()->wiki2xhtml(), 'transform']);

            $class = __NAMESPACE__ . '\\Behaviors';
            dotclear()->behavior()->add('adminPostEditor', [$class, 'adminPostEditor']);
            dotclear()->behavior()->add('adminPopupMedia', [$class, 'adminPopupMedia']);
            dotclear()->behavior()->add('adminPopupLink', [$class, 'adminPopupLink']);
            dotclear()->behavior()->add('adminPopupPosts', [$class, 'adminPopupPosts']);
        }
    }

    public static function installModule(): ?bool
    {
        $settings = dotclear()->blog->settings;
        $settings->addNamespace('LegacyEditor');
        $settings->LegacyEditor->put('active', true, 'boolean', 'LegacyEditor plugin activated ?', false, true);

        return true;
    }
}
