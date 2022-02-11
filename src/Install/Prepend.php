<?php
/**
 * @brief Dotclear install core prepend class
 *
 * @package Dotclear
 * @subpackage Install
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Install;

use Dotclear\Core\Core;

use Dotclear\Install\Install;
use Dotclear\Install\Wizard;

use Dotclear\File\Files;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

class Prepend extends Core
{
    protected $process = 'Install';

    public function process()
    {
        /* Serve a file (css, png, ...) */
        if (!empty($_GET['df'])) {
            Files::serveFile([static::root('Admin', 'files')], 'df');
            exit;
        }

        /* Load parent (or part of) to get some constants */
        if (!defined('DOTCLEAR_CONFIG_PATH')) {
            parent::process();
        }

        /* No configuration ? start installalation process */
        if (!is_file(DOTCLEAR_CONFIG_PATH)) {
            new Wizard();
        } else {
            new Install();
        }
    }
}
