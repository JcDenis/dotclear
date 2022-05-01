<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Buildtools\Admin;

// Dotclear\Plugin\Buildtools\Admin\L10nFaker
use Dotclear\App;
use Dotclear\Helper\File\Path;

/**
 * Buildtools L10n faker.
 *
 * @warning
 * Do not use l10nFaker without production mode ON
 * or it results on generate uncomplete fake plugin.
 *
 * @ingroup  Plugin Buildtools Localisation
 */
class L10nFaker
{
    protected $bundled_plugins;

    public function __construct()
    {
        $this->bundled_plugins = App::core()->config()->get('plugin_official');
    }

    protected function fake_l10n($str)
    {
        return sprintf('__("%s");' . "\n", str_replace('"', '\\"', $str));
    }

    public function generate_file()
    {
        $plugin = "<?php\n\n# Plugin names\n\n";
        foreach ($this->bundled_plugins as $id) {
            $p = App::core()->plugins()?->getModule($id);
            if (!$p) {
                continue; // cope with dev branch and maybe unknow plugins
            }
            $plugin .= $this->fake_l10n($p->description(false));
        }
        mkdir(Path::implode(__DIR__, '..', '..', '_fake_plugin'));
        file_put_contents(Path::implode(__DIR__, '..', '..', '_fake_plugin', '_fake_l10n.php'), $plugin);
    }
}
