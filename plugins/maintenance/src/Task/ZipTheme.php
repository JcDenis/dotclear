<?php
/**
 * @package     Dotclear
 *
 * @copyright   Olivier Meunier & Association Dotclear
 * @copyright   GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\maintenance\Task;

use Dotclear\App;
use Dotclear\Helper\File\Path;
use Dotclear\Helper\File\Zip\Zip;
use Dotclear\Plugin\maintenance\MaintenanceTask;

/**
 * @brief   The theme zip maintenance task.
 * @ingroup maintenance
 */
class ZipTheme extends MaintenanceTask
{
    /**
     * Task ID (class name).
     *
     * @var     null|string     $id
     */
    protected ?string $id = 'dcMaintenanceZiptheme';

    /**
     * Task permissions.
     *
     * @var     null|string     $perm
     */
    protected ?string $perm = 'admin';

    /**
     * Task limited to current blog.
     *
     * @var     bool    $blog
     */
    protected bool $blog = true;

    /**
     * Task tab container.
     *
     * @var     string  $tab
     */
    protected string $tab = 'backup';

    /**
     * Task group container.
     *
     * @var     string  $group
     */
    protected string $group = 'zipblog';

    /**
     * Initialize task object.
     */
    protected function init(): void
    {
        $this->task = __('Download active theme of current blog');

        $this->description = __('It may be useful to backup the active theme before any change or update. This compress theme folder into a single zip file.');
    }

    public function execute()
    {
        // Get theme path
        $path  = App::blog()->themesPath();
        $theme = App::blog()->settings()->system->theme;
        $dir   = Path::real($path . '/' . $theme);
        if (empty($path) || empty($theme) || $dir === false || !is_dir($dir)) {
            return false;
        }

        // Create zip
        @set_time_limit(300);
        $fp  = fopen('php://output', 'wb');
        $zip = new Zip($fp);

        $zip->addExclusion('#(^|/).(.*?)_(m|s|sq|t).jpg$#');
        $zip->addDirectory($dir . '/', '', true);

        // Log task execution here as we sent file and stop script
        $this->log();

        // Send zip
        header('Content-Disposition: attachment;filename=theme-' . $theme . '.zip');
        header('Content-Type: application/x-zip');

        $zip->write();
        unset($zip);
        exit(1);
    }
}
