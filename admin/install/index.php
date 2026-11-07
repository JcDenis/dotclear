<?php
declare(strict_types=1);

/**
 * @file
 * @brief       The install endpoint.
 * @ingroup     Endpoint
 *
 * This page serves installation process.
 *
 * @package 	Dotclear
 *
 * @copyright 	Olivier Meunier & Association Dotclear
 * @copyright 	AGPL-3.0
 */
require_once implode(DIRECTORY_SEPARATOR, [__DIR__, '..', '..', 'src', 'App.php']);

// Command line: first argument is the configuration file path, if it is not an option (ex: -n or --dbdriver=xxx).
// It must be set before application configuration is loaded.
if (PHP_SAPI === 'cli' && isset($_SERVER['argv']) && is_array($_SERVER['argv']) && isset($_SERVER['argv'][1]) && is_string($_SERVER['argv'][1]) && !str_starts_with($_SERVER['argv'][1], '-')) {
    $_SERVER['DC_RC_PATH'] = $_SERVER['argv'][1];
}

// no process is required here as utility load it
new Dotclear\App('Install');
