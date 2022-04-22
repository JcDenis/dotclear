<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module\Iconset;

// Dotclear\Module\Iconset\DefineIconset
use Dotclear\Module\AbstractDefine;

/**
 * Iconset module definition.
 *
 * @ingroup  Module Iconset
 */
class DefineIconset extends AbstractDefine
{
    protected $type = 'Iconset';

    /** This disabled permissions on Iconset */
    public function permissions(): ?string
    {
        return null;
    }

    /** This forced priority on Iconset */
    public function priority(): int
    {
        return 1000;
    }
}
