<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Akismet\Admin;

// Dotclear\Plugin\Akismet\Admin\Prepend
use Dotclear\Modules\ModulePrepend;
use Dotclear\Plugin\Akismet\Common\AkismetBehavior;

/**
 * Admin prepend for plugin Akismet.
 *
 * @ingroup  Plugin Akismet
 */
class Prepend extends ModulePrepend
{
    public function loadModule(): void
    {
        new AkismetBehavior();
    }
}
