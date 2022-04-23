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
use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependAdmin;
use Dotclear\Plugin\Akismet\Common\AkismetBehavior;

/**
 * Admin prepend for plugin Akismet.
 *
 * @ingroup  Plugin Akismet
 */
class Prepend extends AbstractPrepend
{
    use TraitPrependAdmin;

    public function loadModule(): void
    {
        new AkismetBehavior();
    }
}
