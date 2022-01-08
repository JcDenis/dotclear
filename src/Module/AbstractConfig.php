<?php
/**
 * @class Dotclear\Core\Module\AbstractConfig
 * @brief Dotclear Module abstract Config
 *
 * @package Dotclear
 * @subpackage Module
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module;

use Dotclear\Core\Core;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

abstract class AbstractConfig
{
    protected $core;

    public function __construct(Core $core)
    {
        $this->core = $core;
    }

    public static function getPermissions(): ?string
    {
        return null;
    }

    abstract public function setConfiguration(array $post, string $redir): void;

    abstract public function getConfiguration(): void;
}
