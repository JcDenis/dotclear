<?php
/**
 * @package Dotclear
 * @subpackage Plugins
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */

dotclear()->help()->context('antispam', __DIR__ . '/help/help.html');
dotclear()->help()->context('antispam-filters', __DIR__ . '/help/filters.html');
dotclear()->help()->context('ip-filter', __DIR__ . '/help/ip.html');
dotclear()->help()->context('iplookup-filter', __DIR__ . '/help/iplookup.html');
dotclear()->help()->context('words-filter', __DIR__ . '/help/words.html');
dotclear()->help()->context('antispam_comments', __DIR__ . '/help/comments.html');
