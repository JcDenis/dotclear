<?php
/**
 * @class Dotclear\Core\Instance\TraitUrl
 * @brief Dotclear trait error
 *
 * @package Dotclear
 * @subpackage Instance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Instance;

use Dotclear\Core\Instance\Url;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

trait TraitUrl
{
    /** @var    Url   Url instance */
    private $url;

    /**
     * Get instance
     *
     * @return  Url   Url instance
     */
    public function url(): Url
    {
        if (!($this->url instanceof Url)) {
            $this->url = new Url();
        }

        return $this->url;
    }
}
