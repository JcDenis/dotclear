<?php
/**
 * @ingroup  PluginPages
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
use Dotclear\App;

App::core()->help()->context('pages', __DIR__ . '/help/pages.html');
App::core()->help()->context('page', __DIR__ . '/help/page.html');
