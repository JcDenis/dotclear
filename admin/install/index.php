<?php
/**
 * @brief Dotclear install process
 *
 * @package Dotclear
 * @subpackage Process
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
require implode(DIRECTORY_SEPARATOR, [__DIR__, '..', '..', 'src', 'functions.php']);

dotclear_run('install');
