#!/usr/bin/env php
<?php
/**
 * @brief Dotclear upgrade procedure (CLI)
 *
 * @ingroup  Distrib
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
try {
    require_once implode(DIRECTORY_SEPARATOR, [__DIR__, '..', '..', 'functions.php']);
    dotclear_run('Distrib');

    exit(0);
} catch (\Exception $e) {
    echo $e->getMessage() . "\n";

    exit(1);
}
