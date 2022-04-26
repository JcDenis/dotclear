<?php
/**
 * @ingroup  PluginMaintenance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
use Dotclear\App;

App::core()->help()->context('maintenance', __DIR__ . '/help/maintenance.html');
