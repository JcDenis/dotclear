<?php
/**
 * @class Dotclear\Module\AbstractDefine
 * @brief Dotclear Module abstract Define
 *
 * Module Define class must extends this class.
 * It provides all necessary informations about Module.
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
    /**
     * Get module name
     *
     * It can use l10n feature.
     *
     * @return  string  The module name
     */
    abstract public static function getName(): string;

    /**
     * Get module descritpion
     *
     * It can use l10n feature.
     * Use only few words to describe module.
     *
     * @return  string  The module description
     */
    abstract public static function getDescription(): string;

    /**
     * Get module author
     *
     * @return  string  The module author
     */
    abstract public static function getAuthor(): string;

    /**
     * Get module version
     *
     * @return  string  The module version
     */
    abstract public static function getVersion(): string;

    /**
     * Get module permissions
     *
     * Use a comma separated list of permissions to use module
     * or null for super admin.
     *
     * @return  string|null     The module name
     */
    public static function getPermissions(): ?string
    {
        return null;
    }

    /**
     * Get module priority
     *
     * @return  int     The module priority
     */
    public static function getPriority(): int
    {
        return 1000;
    }

    /**
     * Get module standalone configuration usage
     *
     * Return true if module has its own configuration.
     *
     * @return  bool    Use of standalone config
     */
    public static function getStandeloneConfig(): bool
    {
        return false;
    }

    /**
     * Get module type
     *
     * For now can be 'Plugin' or 'Theme'.
     *
     * @return  string  The module type
     */
    public static function getType(): string
    {
        return 'Plugin';
    }

    /**
     * Get module requirements
     *
     * Return array of [module, version].
     * For exemple,
     * if module requires Dotclear 3.0 and AboutConfig 2.0.1
     * method must returns [['core', '3.0'], ['AboutConfig', '2.0.1']]
     *
     * @return  array   The module requirements
     */
    public static function getRequires(): array
    {
        return [];
    }

    /**
     * Get module settings endpoints
     *
     * Return [endpoint => suffix].
     * For exemple,
     * if module has its own configuration Page and
     * some UserPref configuration, method must returns
     * ['self' => '', pref' => '#user-options.user_options_edition']
     *
     * @return  array   The module name
     */
    public static function getSettings(): array
    {
        return [];
    }

    /**
     * Get module repository URL
     *
     * If module has a third party repositiory,
     * provide URL of its dcstore.xml file here.
     *
     * @return  string|null     The module type
     */
    public static function getRepository(): ?string
    {
        return null;
    }

    /**
     * Get module properties
     *
     * @return  array   The module properties
     */
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
