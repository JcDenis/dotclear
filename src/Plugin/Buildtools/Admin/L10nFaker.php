<?php
/**
 * @class Dotclear\Plugin\Buildtools\Admin\L10nFaker
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginBuildtools
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Buildtools\Admin;

use Dotclear\Core\Media;
use Dotclear\Plugin\Widgets\Lib\WidgetsStack;


if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

/**
 * Do not use l10nFaker with DOTCLEAR_RUN_VERBOSE as generate uncomplete fake plugin
 */
class L10nFaker
{
    protected $bundled_plugins;

    public function __construct()
    {
        $this->bundled_plugins = explode(',', dotclear()->config()->plugin_official);
    }

    protected function fake_l10n($str)
    {
        return sprintf('__("%s");' . "\n", str_replace('"', '\\"', $str));
    }
    public function generate_file()
    {
        $widgets_stack = new WidgetsStack();

        $main   = "<?php\n";
        $plugin = "<?php\n";
        $main .= "# Media sizes\n\n";
        foreach (dotclear()->media()->thumb_sizes as $k => $v) {
            $main .= $this->fake_l10n($v[2]);
        }
        $post_types = dotclear()->posttype()->getPostTypes();
        $main .= "\n# Post types\n\n";
        foreach ($post_types as $k => $v) {
            $main .= $this->fake_l10n($v['label']);
        }
        $ws = dotclear()->auth()->user_prefs->favorites; // Favs old school !
        if ($ws) {
            $main .= "\n# Favorites\n\n";
            foreach ($ws->dumpPrefs() as $k => $v) {
                $fav = unserialize($v['value']);
                $main .= $this->fake_l10n($fav['title']);
            }
        }
        file_put_contents(DOTCLEAR_ROOT_DIR . '/Core/_fake_l10n.php', $main);
        $plugin .= "\n# Plugin names\n\n";
        foreach ($this->bundled_plugins as $id) {
            $p = dotclear()->plugins->getModule($id);
            if (!$p) {
                continue; // cope with dev branch and maybe unknow plugins
            }
            $plugin .= $this->fake_l10n($p->description());
        }
        $plugin .= "\n# Widget settings names\n\n";
        $widgets = $widgets_stack::$__widgets->elements();
        foreach ($widgets as $w) {
            $plugin .= $this->fake_l10n($w->desc());
        }
        mkdir(__DIR__ . '/../../_fake_plugin');
        file_put_contents(__DIR__ . '/../../_fake_plugin/_fake_l10n.php', $plugin);
    }
}
