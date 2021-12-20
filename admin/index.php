<?php
/**
 * @brief Dotclear process example
 *
 * This file is the admin (backend) access point
 * of the blogs plateform.
 *
 * If you move admin access point,
 * constant DOTCLEAR_ADMIN_URL must be modified
 * according to your new admin URL.
 *
 * @package Dotclear
 * @subpackage Process
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
require implode(DIRECTORY_SEPARATOR, [dirname(__FILE__), '..', 'src', 'Process.php']);

new Dotclear\Process('admin');
