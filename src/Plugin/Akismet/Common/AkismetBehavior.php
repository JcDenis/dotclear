<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Akismet\Common;

// Dotclear\Plugin\Akismet\Common\AkismetBehavior
use Dotclear\App;
use ArrayObject;

/**
 * Akismet behaviors.
 *
 * @ingroup  Plugin Akismet Behavior
 */
class AkismetBehavior
{
    public function __construct()
    {
        App::core()->behavior('antispamInitFilters')->add(function (ArrayObject $spamfilters): void {
            $spamfilters[] = __NAMESPACE__ . '\\FilterAkismet';
        });
    }
}
