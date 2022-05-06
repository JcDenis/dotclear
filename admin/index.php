<?php
/**
 * Run admin process.
 *
 * @file \admin\index.php
 *
 * This file is the admin (backend) access point
 * of the blogs plateform.
 *
 * If you move admin access point (this file),
 * require path below must match src\App.php file path.
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
require implode(DIRECTORY_SEPARATOR, [__DIR__, '..', 'src', 'App.php']);

Dotclear\App::run('admin');
