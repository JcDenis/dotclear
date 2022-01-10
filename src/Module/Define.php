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

use Dotclear\Html\Html;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class Define
{
    /** @var    errors  Errors */
    private $errors = [];

    /** @var    string  Module id */
    private $id;

    /** @var    string  Module name */
    private $name = '';

    /** @var    string  Module description */
    private $description = '';

    /** @var    string  Module author */
    private $author = '';

    /** @var    string  Module version */
    private $version = '';

    /** @var    string|null     Module permissions */
    private $permissions = null;

    /** @var    int     Module priotiry */
    private $priority = 1000;

    /** @var    bool    Module standalone configuration */
    private $standalone_config = false;

    /** @var    string  Module type */
    private $type = 'Plugin';

    /** @var    array   List of allowed type */
    private $types_allowed = ['Plugin', 'Theme'];

    /** @var    array   Module requirements */
    private $requires = [];

    /** @var    array   Module settings places */
    private $settings = [];

    /** @var    string|null     Module repository */
    private $repository = null;

    /**
     * Constructor
     *
     * Requires a file that content calls to "set" methods.
     *
     * @param   string  $file   Module Define file path
     * @param   string  $id     Module id
     */
    public function __construct(string $file, string $id, array $types_allowed = [])
    {
        $this->id = $id;

        if (!empty($types_allowed)) {
            $this->types_allowed = $types_allowed;
        }

        if (file_exists($file)) {
            require $file;
        } else {
            $this->errors[] = sprintf(
                __('Failed to open define file "%s" for module "%s".'),
                '<strong>' . html::escapeHTML($file) . '</strong>',
                '<strong>' . html::escapeHTML($id) . '</strong>'
            );
        }
    }

    /**
     * Set module name
     *
     * It can use l10n feature.
     *
     * @param   string  The module name
     *
     * @return  Define  Self instance
     */
    protected function setName(string $name): Define
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Set module descritpion
     *
     * It can use l10n feature.
     * Use only few words to describe module.
     *
     * @param   string  The module description
     *
     * @return  Define  Self instance
     */
    protected function setDescription(string $description): Define
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Set module author
     *
     * @return  string  The module author
     */
    protected function setAuthor(string $author): Define
    {
        $this->author = $author;

        return $this;
    }

    /**
     * Set module version
     *
     * @param   string  The module version
     *
     * @return  Define  Self instance
     */
    protected function setVersion(string $version): Define
    {
        $this->version = $version;

        return $this;
    }

    /**
     * Get module permissions
     *
     * Use a comma separated list of permissions to use module
     * or null for super admin.
     *
     * @param   string|null     The module name
     *
     * @return  Define  Self instance
     */
    protected function setPermissions(?string $permissions): Define
    {
        $this->permissions = $permissions;

        return $this;
    }

    /**
     * Set module priority
     *
     * @param   int     The module priority
     *
     * @return  Define  Self instance
     */
    protected function setPriority(int $priority): Define
    {
        $this->priority = $priority;

        return $this;
    }

    /**
     * Set module standalone configuration usage
     *
     * True if module has its own configuration.
     *
     * @param   bool    Use of standalone config
     *
     * @return  Define  Self instance
     */
    protected function setStandaloneConfig(bool $standalone_config): Define
    {
        $this->standalone_config = $standalone_config;

        return $this;
    }

    /**
     * Set module type
     *
     * For now can be 'Plugin' or 'Theme'.
     *
     * @param   string  The module type
     *
     * @return  Define  Self instance
     */
    protected function setType(string $type): Define
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Set module requirements
     *
     * Array of [module, version]. For exemple:
     * if module requires Dotclear 3.0 and AboutConfig 2.0.1
     * method must returns [['core', '3.0'], ['AboutConfig', '2.0.1']]
     *
     * @param   array   The module requirements
     *
     * @return  Define  Self instance
     */
    protected function setRequires(array $requires): Define
    {
        $this->requires = $requires;

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
     * @return  Define  Self instance
     */
    protected function setSettings(array $settings): Define
    {
        $this->settings = $settings;

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
     * @return  Define  Self instance
     */
    protected function setRepository(string $repository): Define
    {
        $this->repository = $repository;

        return $this;
    }

    /**
     * Check if Define has error
     *
     * @return  bool     Has error
     */
    public function hasError(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Get errors
     *
     * @return  array   The errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get module properties
     *
     * @return  array   The module properties
     */
    public function getProperties(): array
    {
        return [
            'name'              => $this->getName(),
            'desc'              => $this->getDescription(),
            'author'            => $this->GetAuthor(),
            'version'           => $this->getVersion(),
            'permissions'       => $this->getPermissions(),
            'priority'          => $this->getPriority(),
            'standalone_config' => $this->getStandaloneConfig(),
            'type'              => $this->getType(),
            'requires'          => $this->getRequires(),
            'settings'          => $this->getSettings(),
            'repository'        => $this->getRepository(),
        ];
    }

    public function getName(): string
    {
        if (empty($this->name)) {
            $this->errors[] = sprintf(
                __('No module name provided for module "%s".'),
                '<strong>' . html::escapeHTML($this->id) . '</strong>'
            );
        }

        return $this->name;
    }

    public function getDescription(): string
    {
        if (empty($this->description)) {
            $this->errors[] = sprintf(
                __('No module description provided for module "%s".'),
                '<strong>' . html::escapeHTML($this->id) . '</strong>'
            );
        }

        return $this->description;
    }

    public function getAuthor(): string
    {
        if (empty($this->author)) {
            $this->errors[] = sprintf(
                __('No module author provided for module "%s".'),
                '<strong>' . html::escapeHTML($this->id) . '</strong>'
            );
        }

        return $this->author;
    }

    public function getVersion(): string
    {
        if (empty($this->version)) {
            $this->errors[] = sprintf(
                __('No module vesoin provided for module "%s".'),
                '<strong>' . html::escapeHTML($this->id) . '</strong>'
            );
        }

        return $this->version;
    }

    public function getPermissions(): ?string
    {
        return $this->permissions;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function getStandaloneConfig(): bool
    {
        return $this->standalone_config;
    }

    public function getType(): string
    {
        $this->type = ucfirst(strtolower($this->type));

        if (!in_array($this->type, $this->types_allowed)) {
            $this->errors[] = sprintf(
                __('Unsupported module type "%s" provided for module "%s".'),
                '<strong>' . html::escapeHTML($this->type) . '</strong>',
                '<strong>' . html::escapeHTML($this->id) . '</strong>'
            );
        }

        return $this->type;
    }

    public function getRequires(): array
    {
        foreach($this->requires as $k => $v) {
            if (!is_array($v)) {
                unset($this->requires[$k]);
            }
        }

        return $this->requires;
    }

    public function getSettings(): array
    {
        return $this->settings;
    }

    public function getRepository(): ?string
    {
        return $this->repository;
    }
}
