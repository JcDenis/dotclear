<?php
/**
 * @ingroup  PluginPings
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
use Dotclear\App;

App::core()->help()->context('pings', __DIR__ . '/help/pings.html');
App::core()->help()->context('pings_post', __DIR__ . '/help/pings_post.html');
