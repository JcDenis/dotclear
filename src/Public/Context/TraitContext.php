<?php
/**
 * @class Dotclear\Public\Context\TraitContext
 * @brief Dotclear trait public context
 *
 * @package Dotclear
 * @subpackage Instance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Public\Context;

use Dotclear\Public\Context\Context;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

trait TraitContext
{
    /** @var    Context   Context instance */
    private $context;

    /**
     * Get instance
     *
     * @return  Context   Context instance
     */
    public function context(): Context
    {
        if (!($this->context instanceof Context)) {
            $this->context = new Context();
        }

        return $this->context;
    }
}
