<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Handler;

// Dotclear\Process\Admin\Handler\Services
use Dotclear\App;
use Dotclear\Process\Admin\Page\AbstractPage;
use Dotclear\Process\Admin\Service\RestMethods;

/**
 * Admin rest service page.
 *
 * @ingroup  Admin Rest Handler
 */
class Services extends AbstractPage
{
    protected function getPermissions(): string|bool
    {
        return true;
    }

    protected function getPagePrepend(): ?bool
    {
        new RestMethods();
        App::core()->rest()->serve();

        return null;
    }
}
