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
        $self_ns = dotclear()->blog->settings->addNamespace('LegacyEditor');

        if ($self_ns->active) {
            if (!(dotclear()->wiki2xhtml instanceof wiki2xhtml)) {
                dotclear()->initWikiPost();
            }

            dotclear()->addEditorFormater('LegacyEditor', 'xhtml', fn ($s) => $s);
            dotclear()->addEditorFormater('LegacyEditor', 'wiki', [dotclear()->wiki2xhtml, 'transform']);

            $class = __NAMESPACE__ . '\\Behaviors';
            dotclear()->behaviors->add('adminPostEditor', [$class, 'adminPostEditor']);
            dotclear()->behaviors->add('adminPopupMedia', [$class, 'adminPopupMedia']);
            dotclear()->behaviors->add('adminPopupLink', [$class, 'adminPopupLink']);
            dotclear()->behaviors->add('adminPopupPosts', [$class, 'adminPopupPosts']);
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
