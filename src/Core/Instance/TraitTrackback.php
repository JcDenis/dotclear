<?php
/**
 * @class Dotclear\Core\Instance\TraitTrackback
 * @brief Dotclear trait Trackback
 *
 * @package Dotclear
 * @subpackage Instance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Instance;

use Dotclear\Core\Instance\Trackback;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

trait TraitTrackback
{
    /** @var    Trackback   Trackback instance */
    private $trackback;

    /**
     * Get instance
     *
     * @return  Trackback   Trackback instance
     */
    public function trackback(): Trackback
    {
        if (!($this->trackback instanceof Trackback)) {
            $this->trackback = new Trackback();
        }

        return $this->trackback;
    }
}
