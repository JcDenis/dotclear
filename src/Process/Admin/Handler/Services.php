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
use Dotclear\Process\Admin\Page\AbstractPage;
use Dotclear\Process\Admin\Service\RestMethods;

/**
 * Admin rest service page.
 *
 * @ingroup  Admin Rest Handler
 */
class Services extends AbstractPage
{
    protected function getPermissions(): string|null|false
    {
        return false;
    }

    protected function getPagePrepend(): ?bool
    {
        new RestMethods();
        dotclear()->rest()->serve();

        return null;
    }
}
