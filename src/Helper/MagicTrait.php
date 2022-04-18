<?php
/**
 * @note Dotclear\Helper\MagicTrait
 * @brief Dotclear protect class from magic get/set
 *
 * @ingroup  Helper
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Helper;

use Dotclear\Exception\MagicException;

trait MagicTrait
{
    public function __get($_)
    {
        throw new MagicException('Call to magic __get method: ' . $_);
    }

    public function __set($_, $__)
    {
        throw new MagicException('Call to magic __set method: ' . $_);
    }

    public function __isset($_)
    {
        throw new MagicException('Call to magic __isset method: ' . $_);
    }

    public function __call($_, $__)
    {
        throw new MagicException('Call to magic __isset method: ' . $_);
    }
}
