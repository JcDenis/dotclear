<?php
/**
 * @class Dotclear\Core\Instance\TraitXmlrpc
 * @brief Dotclear trait Xmlrpc
 *
 * @package Dotclear
 * @subpackage Instance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Instance;

use Dotclear\Core\Instance\Xmlrpc;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

trait TraitXmlrpc
{
    /** @var    Xmlrpc   Xmlrpc instance */
    private $xmlrpc;

    /**
     * Get instance
     *
     * @param   string|null     $blog_id    The blog Id
     *
     * @return  Xmlrpc                      Xmlrpc instance
     */
    protected function xmlrpc(?string $blog_id = null): Xmlrpc
    {
        if ($blog_id !== null) {
            $this->xmlrpc = new Xmlrpc($blog_id);
        }

        return $this->xmlrpc;
    }
}
