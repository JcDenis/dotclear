<?php
/**
 * @class Dotclear\Plugin\Buildtools\Admin\MaintenanceTaskBuildtools
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

use Dotclear\Plugin\Maintenance\Admin\Lib\MaintenanceTask;
use Dotclear\Plugin\Buildtools\Admin\L10nFaker;

class MaintenanceTaskBuildtools extends MaintenanceTask
{
    protected $tab   = 'dev';
    protected $group = 'l10n';

    protected function init()
    {
        $this->task        = __('Generate fake l10n');
        $this->success     = __('fake l10n file generated.');
        $this->error       = __('Failed to generate fake l10n file.');
        $this->description = __('Generate a php file that contents strings to translate that are not be done with core tools.');
    }

    public function execute()
    {
        /*
        $widget = dotclear()->plugins()?->getModules('widgets');
        include $widget['root'] . '/_default_widgets.php';
        */

        $faker = new L10nFaker();
        $faker->generate_file();

        return true;
    }
}
