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

use Dotclear\Core\Core;
use Dotclear\Html\wiki2xhtml;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Prepend extends AbstractPrepend
{
    use TraitPrependAdmin;

    public static function checkModule(Core $core): bool
    {
        return true;
    }

    public static function loadModule(Core $core): void
    {
        $self_ns = $core->blog->settings->addNamespace('LegacyEditor');

        if ($self_ns->active) {
            if (!($core->wiki2xhtml instanceof wiki2xhtml)) {
                $core->initWikiPost();
            }

            $core->addEditorFormater('LegacyEditor', 'xhtml', fn ($s) => $s);
            $core->addEditorFormater('LegacyEditor', 'wiki', [$core->wiki2xhtml, 'transform']);

            $class = __NAMESPACE__ . '\\Behaviors';
            $core->behaviors->add('adminPostEditor', [$class, 'adminPostEditor']);
            $core->behaviors->add('adminPopupMedia', [$class, 'adminPopupMedia']);
            $core->behaviors->add('adminPopupLink', [$class, 'adminPopupLink']);
            $core->behaviors->add('adminPopupPosts', [$class, 'adminPopupPosts']);
        }
    }

    public static function installModule(Core $core): ?bool
    {
        $settings = $core->blog->settings;
        $settings->addNamespace('LegacyEditor');
        $settings->LegacyEditor->put('active', true, 'boolean', 'LegacyEditor plugin activated ?', false, true);

        return true;
    }
}
