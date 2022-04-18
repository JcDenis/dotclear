<?php
/**
 * @note Dotclear\Plugin\Maintenance\Admin\Lib\Task\MaintenanceTaskZipmedia
 * @brief Dotclear Plugins class
 *
 * @ingroup  PluginMaintenance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Maintenance\Admin\Lib\Task;

use Dotclear\Helper\File\Zip\Zip;
use Dotclear\Plugin\Maintenance\Admin\Lib\MaintenanceTask;

class MaintenanceTaskZipmedia extends MaintenanceTask
{
    protected $perm  = 'admin';
    protected $blog  = true;
    protected $tab   = 'backup';
    protected $group = 'zipblog';

    protected function init(): void
    {
        $this->task = __('Download media folder of current blog');

        $this->description = __('It may be useful to backup your media folder. This compress all content of media folder into a single zip file. Notice : with some hosters, the media folder cannot be compressed with this plugin if it is too big.');
    }

    public function execute(): int|bool
    {
        if (!dotclear()->media()) {
            return false;
        }

        // Instance media
        dotclear()->media()->chdir('');
        dotclear()->media()->getDir();

        // Create zip
        @set_time_limit(300);
        $fp  = fopen('php://output', 'wb');
        $zip = new Zip($fp);
        $zip->addExclusion('#(^|/).(.*?)_(m|s|sq|t).jpg$#');
        $zip->addDirectory(dotclear()->media()->root . '/', '', true);

        // Log task execution here as we sent file and stop script
        $this->log();

        // Send zip
        header('Content-Disposition: attachment;filename=' . date('Y-m-d') . '-' . dotclear()->blog()->id . '-' . 'media.zip');
        header('Content-Type: application/x-zip');
        $zip->write();
        unset($zip);

        exit(1);
    }
}
