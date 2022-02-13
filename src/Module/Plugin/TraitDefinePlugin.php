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

use Dotclear\Network\Http;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

trait TraitDefinePlugin
{
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

        $this->properties['priority']          = (int) $this->properties['priority'];
        $this->properties['standalone_config'] = (bool) $this->properties['standalone_config'];

        $this->repository = trim($this->properties['repository']);
        if (!empty($this->properties['repository'])) {
            $this->properties['repositiory'] = substr($this->properties['repository'], -12, 12) == '/dcstore.xml' ?
                $this->properties['repository'] :
                Http::concatURL($this->properties['repository'], 'dcstore.xml');
        }

    }
}
