<?php
/**
 * @class Dotclear\Plugin\Akismet\Common\AkismetBehavior
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginAkismet
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Akismet\Common;

use ArrayObject;

class AkismetBehavior
{
    public function __construct()
    {
        dotclear()->behavior()->add('antispamInitFilters', function(ArrayObject $spamfilters): void {
            $spamfilters[] = __NAMESPACE__ . '\\FilterAkismet';
        });
    }
}
