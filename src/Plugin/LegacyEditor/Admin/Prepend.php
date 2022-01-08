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

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Prepend extends AbstractPrepend
{
    public static function loadModule(Core $core): ?bool
    {
/*
        # Add Plugin Admin Page sidebar menu item
        $core->menu['System']->addItem(
            __('Legacy editor'),
            $core->adminurl->get('admin.plugin.LegacyEditor'),
            '?pf=LegacyEditor/icon.png',
            $core->adminurl->called() == 'admin.plugin.LegacyEditor',
            $core->auth->check('admin,contentadmin', $core->blog->id)
        );
*/

        $self_ns = $core->blog->settings->addNamespace('LegacyEditor');

        if ($self_ns->active) {
            if (!($core->wiki2xhtml instanceof wiki2xhtml)) {
                $core->initWikiPost();
            }

            //$core->addEditorFormater('LegacyEditor', 'xhtml', function ($s) {return $s;});
            $core->addEditorFormater('LegacyEditor', 'wiki', [$core->wiki2xhtml, 'transform']);

            $class = __NAMESPACE__ . '\\Admin\\Behaviors';
            $core->behaviors->add('adminPostEditor', [$class, 'adminPostEditor']);
            $core->behaviors->add('adminPopupMedia', [$class, 'adminPopupMedia']);
            $core->behaviors->add('adminPopupLink', [$class, 'adminPopupLink']);
            $core->behaviors->add('adminPopupPosts', [$class, 'adminPopupPosts']);
        }

        return true;
    }
}
