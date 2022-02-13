<?php
/**
 * @class Dotclear\Core\Instance\TraitMedia
 * @brief Dotclear trait Media
 *
 * @package Dotclear
 * @subpackage Instance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Instance;

use Dotclear\Core\Instance\Media;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

trait TraitMedia
{
    /** @var    Media   Media instance */
    private $media;

    /**
     * Get instance
     *
     * @return  Media   Media instance
     */
    public function media(bool $reload = null): Media
    {
        if (!($this->media instanceof Media) || $reload) {
            $this->media = new Media();
        }

        return $this->media;
    }
}
