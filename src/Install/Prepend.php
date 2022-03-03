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

use Dotclear\Admin\Filer;
use Dotclear\Admin\Favorite\Favorite;
use Dotclear\Core\Core;
use Dotclear\File\Files;
use Dotclear\Install\Install;
use Dotclear\Install\Wizard;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

class Prepend extends Core
{
    /** @var    Favorite   Favorite instance */
    private $favorite;

    /** @var    Filer   Filer instance */
    private $filer;

    protected $process = 'Install';

    /**
     * Get favorite instance
     *
     * @return  Favorite   Favorite instance
     */
    public function favorite(): Favorite
    {
        if (!($this->favorite instanceof Favorite)) {
            $this->favorite = new Favorite();
        }

        return $this->favorite;
    }

    /**
     * Get filer instance
     *
     * @return  Filer   Filer instance
     */
    public function filer(): Filer
    {
        if (!($this->filer instanceof Filer)) {
            $this->filer = new Filer();
        }

        return $this->filer;
    }

    protected function process(): void
    {
        /* Serve a file (css, png, ...) */
        if (!empty($_GET['df'])) {
            Files::serveFile($_GET['df'], [root_path('Admin', 'files')], dotclear()->config()->file_sever_type);
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
