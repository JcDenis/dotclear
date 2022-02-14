<?php
/**
 * @class Dotclear\Core\Instance\TraitVersion
 * @brief Dotclear trait core versions
 *
 * @package Dotclear
 * @subpackage Instance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Instance;

use Dotclear\Core\Instance\Version;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

trait TraitVersion
{
    /** @var    Log   Log instance */
    private $version;

    /**
     * Get instance
     *
     * @return  Log   Log instance
     */
    public function version(): Version
    {
        if (!($this->version instanceof Version)) {
            $this->version = new Version();
        }

        return $this->version;
    }
}
