<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Install;

// Dotclear\Process\Install\Prepend
use Dotclear\Core\Core;
use Dotclear\Helper\File\Files;
use Dotclear\Helper\File\Path;
use Dotclear\Helper\GPC\GPC;
use Dotclear\Process\Admin\Resource\Resource;
use Dotclear\Process\Admin\Favorite\Favorite;

/**
 * Install process.
 *
 * @ingroup  Install
 */
final class Prepend extends Core
{
    /**
     * @var Favorite $favorite
     *               Favorite instance
     */
    private $favorite;

    /**
     * @var resource $resource
     *               Resource instance
     */
    private $resource;

    protected $process = 'Install';

    /**
     * Get favorite instance.
     *
     * @return Favorite Favorite instance
     */
    public function favorite(): Favorite
    {
        if (!($this->favorite instanceof Favorite)) {
            $this->favorite = new Favorite();
        }

        return $this->favorite;
    }

    /**
     * Get resource instance.
     *
     * @return resource Resource instance
     */
    public function resource(): Resource
    {
        if (!($this->resource instanceof Resource)) {
            $this->resource = new Resource();
        }

        return $this->resource;
    }

    protected function process(string $_ = null): void
    {
        // Serve a file (css, png, ...)
        if (!GPC::get()->empty('df')) {
            Files::serveFile(GPC::get()->string('df'), [Path::implodeSrc('Process', 'Admin', 'resources')]);

            exit;
        }

        // Load parent (or part of) to get some constants
        if (null === $this->config_path) {
            parent::process();
        }

        // No configuration ? start installalation process
        if (!is_file($this->config_path)) {
            new Wizard($this->config_path);
        } else {
            new Install();
        }
    }
}
