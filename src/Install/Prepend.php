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

use Dotclear\Core\Prepend as BasePrepend;
use Dotclear\Core\Utils;

use Dotclear\Network\Http;
use Dotclear\Utils\L10n;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

class Prepend extends BasePrepend
{
    protected $process = 'Install';

    public function __construct()
    {
        /* Serve a file (css, png, ...) */
        if (!empty($_GET['df'])) {
            Utils::fileServer([static::root('Admin', 'files')], 'df');
            exit;
        }

        /* Load parent (or part of) to get some constants */
        if (!defined('DOTCLEAR_CONFIG_PATH')) {
            parent::__construct();
        }

        /* No configuration ? start installalation process */
        if (!is_file(DOTCLEAR_CONFIG_PATH)) {
            new Wizard($this);
        } else {
            new Install($this);
        }
    }
}
