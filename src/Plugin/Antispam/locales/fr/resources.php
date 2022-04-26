<?php
/**
 * @ingroup  PluginAntispam
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
use Dotclear\App;

App::core()->help()->context('antispam', __DIR__ . '/help/help.html');
App::core()->help()->context('antispam-filters', __DIR__ . '/help/filters.html');
App::core()->help()->context('ip-filter', __DIR__ . '/help/ip.html');
App::core()->help()->context('iplookup-filter', __DIR__ . '/help/iplookup.html');
App::core()->help()->context('words-filter', __DIR__ . '/help/words.html');
App::core()->help()->context('antispam_comments', __DIR__ . '/help/comments.html');
