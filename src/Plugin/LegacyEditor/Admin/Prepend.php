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

use Dotclear\Core\Core;
use Dotclear\Html\wiki2xhtml;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Prepend extends AbstractPrepend
{
    public static function checkModule(Core $core): bool
    {
        $self_ns = $core->blog->settings->addNamespace('LegacyEditor');
        return (bool) $self_ns->active;
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
}
