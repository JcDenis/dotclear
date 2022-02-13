<?php
/**
 * @class Dotclear\Core\Instance\TraitMeta
 * @brief Dotclear trait meta
 *
 * @package Dotclear
 * @subpackage Instance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Instance;

use Dotclear\Core\Instance\Meta;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

trait TraitMeta
{
    /** @var    Meta   Meta instance */
    private $meta;

    /**
     * Get instance
     *
     * @return  Meta   Meta instance
     */
    public function meta(): Meta
    {
        if (!($this->meta instanceof Meta)) {
            $this->meta = new Meta();
        }

        return $this->meta;
    }
}
