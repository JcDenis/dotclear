<?php
/**
 * @brief Dotclear process example
 *
 * @package Dotclear
 * @subpackage Process
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
require implode(DIRECTORY_SEPARATOR, [dirname(__FILE__), '..', 'src', 'Process.php']);

new Dotclear\Process('admin');
