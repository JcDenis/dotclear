#!/usr/bin/env php
<?php
/**
 * @brief Dotclear upgrade procedure (CLI)
 *
 * @package Dotclear
 * @subpackage Distrib
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */

try {
    require_once implode(DIRECTORY_SEPARATOR, [dirname(__FILE__), '..', 'Process.php']);
    new Dotclear\Process('Distrib');
    exit(0);
} catch (Exception $e) {
    echo $e->getMessage() . "\n";
    exit(1);
}
?>
