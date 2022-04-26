<?php
/**
 * @ingroup  PluginThemeEditor
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
use Dotclear\App;

App::core()->help()->context('themeEditor', dirname(__FILE__) . '/help/help.html');
