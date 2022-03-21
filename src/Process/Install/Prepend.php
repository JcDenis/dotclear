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

namespace Dotclear\Process\Install;

use Dotclear\Core\Core;
use Dotclear\Helper\File\Files;
use Dotclear\Helper\File\Path;
use Dotclear\Process\Admin\Resource\Resource;
use Dotclear\Process\Admin\Favorite\Favorite;
use Dotclear\Process\Install\Install;
use Dotclear\Process\Install\Wizard;

class Prepend extends Core
{
    /** @var    Favorite   Favorite instance */
    private $favorite;

    /** @var    Resource   Resource instance */
    private $resource;

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
     * Get resource instance
     *
     * @return  Resource   Resource instance
     */
    public function resource(): Resource
    {
        if (!($this->resource instanceof Resource)) {
            $this->resource = new Resource();
        }

        return $this->resource;
    }

    protected function process(): void
    {
        /* Serve a file (css, png, ...) */
        if (!empty($_GET['df'])) {
            Files::serveFile($_GET['df'], [Path::implodeRoot('Process', 'Admin', 'resources')]);
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
