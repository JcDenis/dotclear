<?php
/**
 * @class Dotclear\Core\Module\AbstractDefine
 * @brief Dotclear Module abstract Define
 *
 * @package Dotclear
 * @subpackage Module
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

abstract class AbstractDefine
{
    abstract public static function getName(): string;
    abstract public static function getDescription(): string;
    abstract public static function getAuthor(): string;
    abstract public static function getVersion(): string;

    public static function getPermissions(): ?string
    {
        return null;
    }

    public static function getPriority(): int
    {
        return 1000;
    }

    public static function getStandeloneConfig(): bool
    {
        return false;
    }

    public static function getType(): string
    {
        return 'Plugin';
    }

    public static function getRequires(): array
    {
        return [];
    }

    public static function getSettings(): array
    {
        return [];
    }

    public static function getRepository(): ?string
    {
        return null;
    }

    final public static function getProperties(): array
    {
        return [
            'name'              => static::getName(),
            'desc'              => static::getDescription(),
            'author'            => static::GetAuthor(),
            'version'           => static::getVersion(),
            'permissions'       => static::getPermissions(),
            'priority'          => static::getPriority(),
            'standalone_config' => static::getStandeloneConfig(),
            'type'              => static::getType(),
            'requires'          => static::getRequires(),
            'settings'          => static::getSettings(),
            'repository'        => static::getRepository(),
        ];
    }
}
