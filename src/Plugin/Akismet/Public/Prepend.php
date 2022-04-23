<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Akismet\Public;

// Dotclear\Plugin\Akismet\Public\Prepend
use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependPublic;
use Dotclear\Plugin\Akismet\Common\AkismetBehavior;

/**
 * Public prepend for plugin Akismet.
 *
 * @ingroup  Plugin Akismet
 */
class Prepend extends AbstractPrepend
{
    use TraitPrependPublic;

    public function loadModule(): void
    {
        new AkismetBehavior();
    }
}
