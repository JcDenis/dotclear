<?php

/**
 * Unit tests bootstrap
 *
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright AGPL-3.0
 */
declare(strict_types=1);

use Dotclear\Config;
use Dotclear\Core\Core;
use Dotclear\Helper\Container\Factories;
use Dotclear\Helper\L10n;

// Composer Autoloader
require_once __DIR__ . '/../../vendor/autoload.php';

// Dotclear Autoloader
require_once __DIR__ . '/../../src/Autoloader.php';
$autoloader = new Autoloader('', '', true);
$autoloader->addNamespace('Dotclear', implode(DIRECTORY_SEPARATOR, [__DIR__, '..', '..', 'src']));

// Clearbricks Autoloader (deprecated)
$__autoload = [
    'form'             => implode(DIRECTORY_SEPARATOR, [__DIR__, '..', '..', 'src', 'Helper', 'Html', 'Form', 'Legacy.php']),
    'formSelectOption' => implode(DIRECTORY_SEPARATOR, [__DIR__, '..', '..', 'src', 'Helper', 'Html', 'Form', 'Legacy.php']),
];
spl_autoload_register(function ($name) use ($__autoload) {if (isset($__autoload[$name])) { require_once $__autoload[$name]; }});

// Ensure L10n functions exist
L10n::bootstrap();

// Instanciate Core
new Core(new Config(implode(DIRECTORY_SEPARATOR, [__DIR__, '..', '..'])), Factories::getFactory(Core::CONTAINER_ID));
