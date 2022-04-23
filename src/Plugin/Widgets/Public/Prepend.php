<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Widgets\Public;

// Dotclear\Plugin\Widgets\Public\Prepend
use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependPublic;
use Dotclear\Plugin\Widgets\Common\WidgetsStack;

/**
 * Public prepend for plugin Widgets.
 *
 * @ingroup  Plugin Widgets
 */
class Prepend extends AbstractPrepend
{
    use TraitPrependPublic;

    public function loadModule(): void
    {
        // Load Widgets stack on public prepend
        dotclear()->behavior()->add('publicPrepend', fn () => new WidgetsStack());
    }
}
