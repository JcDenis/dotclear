<?php
/**
 * @package Dotclear
 * @subpackage Plugins
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */

$this->context('antispam', __DIR__ . '/help/help.html');
$this->context(['antispam-filters', __DIR__ . '/help/filters.html');
$this->context(['ip-filter', __DIR__ . '/help/ip.html');
$this->context('iplookup-filter', __DIR__ . '/help/iplookup.html');
$this->context('words-filter', __DIR__ . '/help/words.html');
$this->context('antispam_comments', __DIR__ . '/help/comments.html');
