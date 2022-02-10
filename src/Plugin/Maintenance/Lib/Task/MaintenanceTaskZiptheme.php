<?php
/**
 * @class Dotclear\Plugin\Maintenance\Lib\Task\MaintenanceTaskZiptheme
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginMaintenance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Maintenance\Lib\Task;

use Dotclear\Plugin\Maintenance\Lib\MaintenanceTask;

use Dotclear\File\Zip\Zip;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class MaintenanceTaskZiptheme extends MaintenanceTask
{
    protected $perm  = 'admin';
    protected $blog  = true;
    protected $tab   = 'backup';
    protected $group = 'zipblog';

    protected function init()
    {
        $this->task = __('Download active theme of current blog');

        $this->description = __('It may be useful to backup the active theme before any change or update. This compress theme folder into a single zip file.');
    }

    public function execute()
    {
        // Get theme path
        $theme = dotclear()->themes->getModule((string) dotclear()->blog->settings->system->theme);
        if (!$theme) {
            return false;
        }
        $dir = $theme->root();
        if (!is_dir($dir)) {
            return false;
        }

        // Create zip
        @set_time_limit(300);
        $fp  = fopen('php://output', 'wb');
        $zip = new fileZip($fp);
        $zip->addExclusion('#(^|/).(.*?)_(m|s|sq|t).jpg$#');
        $zip->addDirectory($dir . '/', '', true);

        // Log task execution here as we sent file and stop script
        $this->log();

        // Send zip
        header('Content-Disposition: attachment;filename=theme-' . $theme->id() . '.zip');
        header('Content-Type: application/x-zip');
        $zip->write();
        unset($zip);
        exit(1);
    }
}
