<?php
/**
 * Run install process.
 * 
 * @file \admin\install\index.php
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
require implode(DIRECTORY_SEPARATOR, [__DIR__, '..', '..', 'src', 'App.php']);

Dotclear\App::run('install');
