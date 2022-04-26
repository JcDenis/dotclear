<?php
/**
 * @ingroup  PluginAboutConfig
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
use Dotclear\App;

App::core()->help()->context('aboutConfig', __DIR__ . '/help/help.html');
