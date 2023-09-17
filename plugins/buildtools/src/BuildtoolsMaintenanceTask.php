<?php
/**
 * @package     Dotclear
 *
 * @copyright   Olivier Meunier & Association Dotclear
 * @copyright   GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\buildtools;

use Dotclear\Plugin\maintenance\MaintenanceTask;
use Dotclear\Plugin\widgets\Widgets;

/**
 * @brief   The module maintenance task.
 * @ingroup buildtools
 */
class BuildtoolsMaintenanceTask extends MaintenanceTask
{
    /**
     * Maintenance Tab name.
     *
     * @var     string  $tab
     */
    protected $tab = 'dev';

    /**
     * Maintenance group name.
     *
     * @var     string  $group
     */
    protected $group = 'l10n';

    /**
     * Initializes the task.
     */
    protected function init(): void
    {
        $this->task        = __('Generate fake l10n');
        $this->success     = __('fake l10n file generated.');
        $this->error       = __('Failed to generate fake l10n file.');
        $this->description = __('Generate a php file that contents strings to translate that are not be done with core tools.');
    }

    /**
     * Execute the task.
     *
     * @return  bool
     */
    public function execute(): bool
    {
        Widgets::init();

        $faker = new l10nFaker();
        $faker->generate_file();

        return true;
    }
}
