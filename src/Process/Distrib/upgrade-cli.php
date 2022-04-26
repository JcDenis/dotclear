#!/usr/bin/env php
<?php
/**
 * Run upgrade procedure (CLI).
 *
 * @file \src\Process\Distrib\upgrade-cli.php
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
try {
    require_once implode(DIRECTORY_SEPARATOR, [__DIR__, '..', '..', 'App.php']);
    Dotclear\App::run('Distrib');

    exit(0);
} catch (\Exception $e) {
    echo $e->getMessage() . "\n";

    exit(1);
}
