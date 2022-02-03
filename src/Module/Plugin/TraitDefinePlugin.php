<?php
/**
 * @class Dotclear\Module\Plugin\TraitDefinePlugin
 *
 * @package Dotclear
 * @subpackage Module
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module\Plugin;

use Dotclear\Module\AbstractDefine;
use Dotclear\Module\Store\TraitDefineStore;

use Dotclear\Network\Http;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

trait TraitDefinePlugin
{
    use TraitDefineStore;

    /**
     * Get module permissions
     *
     * Use a comma separated list of permissions to use module
     * or null for super admin.
     *
     * @param   string|null     The module name
     *
     * @return  AbstractDefine  Self instance
     */
    protected function setPermissions(?string $permissions): AbstractDefine
    {
        $this->properties['permissions'] = $permissions;

        return $this;
    }

    /**
     * Set module priority
     *
     * @param   int     The module priority
     *
     * @return  AbstractDefine  Self instance
     */
    protected function setPriority(int $priority): AbstractDefine
    {
        $this->properties['priority'] = $priority;

        return $this;
    }

    /**
     * Set module standalone configuration usage
     *
     * True if module has its own configuration.
     *
     * @param   bool    Use of standalone config
     *
     * @return  AbstractDefine  Self instance
     */
    protected function setStandaloneConfig(bool $standalone_config): AbstractDefine
    {
        $this->properties['standalone_config'] = $standalone_config;

        return $this;
    }

    /**
     * Set module settings endpoints
     *
     * Array [endpoint => suffix]. For exemple:
     * if module has its own configuration Page and
     * some UserPref configuration, method must returns
     * ['self' => '', pref' => '#user-options.user_options_edition']
     *
     * @param   array   The module settings places
     *
     * @return  AbstractDefine  Self instance
     */
    protected function setSettings(array $settings): AbstractDefine
    {
        $this->properties['settings'] = $settings;

        return $this;
    }

    /**
     * Set module repository URL
     *
     * If module has a third party repositiory,
     * provide URL to its dcstore.xml file here.
     *
     * @param   string     The module repository
     *
     * @return  AbstractDefine  Self instance
     */
    protected function setRepository(string $repository): AbstractDefine
    {
        $this->properties['repository'] = $repository;

        return $this;
    }

    public function permissions(): ?string
    {
        return $this->properties['permissions'] ?? null;
    }

    public function priority(): int
    {
        return $this->properties['priority'] ?: 1000;
    }

    public function standaloneConfig(): bool
    {
        return $this->properties['standalone_config'] ?: false;
    }

    public function settings(): array
    {
        return $this->properties['settings'] ?: [];
    }

    public function repository(): string
    {
        return $this->properties['repository'] ?: '';
    }

    protected function checkDefinePlugin(): void
    {
        $this->properties = array_merge(
            [
                'permissions'       => null,
                'priority'          => 1000,
                'standalone_config' => false,
                'settings'          => [],
                'repository'        => '',
            ],
            $this->properties
        );

        if ($this->properties['permissions'] == 'NULL') {
            $this->properties['permissions'] = null;
        }

        $this->properties['priority'] = (int) $this->properties['priority'];

        $this->repository = trim($this->properties['repository']);
        if (!empty($this->properties['repository'])) {
            $this->properties['repositiory'] = substr($this->properties['repository'], -12, 12) == '/dcstore.xml' ?
                $this->properties['repository'] :
                Http::concatURL($this->properties['repository'], 'dcstore.xml');
        }

        $this->checkDefineStore();
    }
}
