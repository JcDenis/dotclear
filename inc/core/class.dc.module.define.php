<?php
/**
 * @brief Modules defined properties.
 *
 * Provides an object to handle modules properties (themes or plugins).
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 *
 * @since 2.25
 */

use Dotclear\Module\DefineStrict;

class dcModuleDefine
{
    /**
     * Disabled state.
     *
     * @var        int
     */
    public const STATE_ENABLED       = 0;
    public const STATE_INIT_DISABLED = 1;
    public const STATE_SOFT_DISABLED = 2;
    public const STATE_HARD_DISABLED = 4;

    /**
     * Undefined module's name.
     *
     * @var        string
     */
    public const DEFAULT_NAME = 'undefined';

    /**
     * Undefined module's type.
     *
     * @var        string
     */
    public const DEFAULT_TYPE = 'undefined';

    /**
     * Default module's priority.
     *
     * @var        int
     */
    public const DEFAULT_PRIORITY = 1000;

    /**
     * Module id, must be module's root path.
     *
     * @var     string
     */
    private string $id;

    /**
     * Dependencies.
     *
     * @var     array<int|string,string>
     */
    private array $implies = [];
    private array $missing = [];
    private array $using   = [];

    /**
     * Module properties.
     *
     * @var     array
     */
    private array $properties = [];

    /**
     * Module default properties.
     *
     * @var     array<string,mixed>
     */
    private array $default = [
        // set by dc
        'state'         => self::STATE_INIT_DISABLED,
        'root'          => null,
        'namespace'     => null,
        'root_writable' => false,
        'distributed'   => false,

        // required
        'name'    => self::DEFAULT_NAME,
        'desc'    => '',
        'author'  => '',
        'version' => '0',
        'type'    => self::DEFAULT_TYPE,

        // optionnal
        'permissions'       => null,
        'priority'          => self::DEFAULT_PRIORITY,
        'standalone_config' => false,
        'requires'          => [],
        'settings'          => [],

        // optionnal++
        'label'      => '',
        'support'    => '',
        'details'    => '',
        'repository' => '',

        // theme specifics
        'parent' => null,
        'tplset' => DC_DEFAULT_TPLSET,

        // store specifics
        'file'            => '',
        'current_version' => '0',

        // DA specifics
        'section' => '',
        'tags'    => '',
        'sshot'   => '',
        'score'   => 0,
        'dc_min'  => '',

        // modules list specifics
        'sid'   => '',
        'sname' => '',
    ];

    /**
     * Strict module properties representation.
     *
     * @var     DefineStrict
     */
    private DefineStrict $strict;

    /**
     * Create a module definition.
     *
     * @param   string  $id The module identifier (root path)
     */
    final public function __construct(string $id)
    {
        $this->id = $id;
        $this->init();

        $this->renewStrict();
    }

    /**
     * Get strict module properties representation.
     *
     * @return  DefineStrict    The strict properties
     */
    final public function strict(): DefineStrict
    {
        return $this->strict;
    }

    /**
     * Reload strict properties (after a change).
     */
    private function renewStrict(): void
    {
        $this->strict = new DefineStrict($this);
    }

    /**
     * Initialize module's properties.
     *
     * Module's define class must use this to set
     * their properties.
     */
    protected function init(): void
    {
    }

    /**
     * Check if module is defined.
     *
     * @return  bool    True if module is defined
     */
    public function isDefined(): bool
    {
        return $this->get('name') != self::DEFAULT_NAME;
    }

    public function addImplies(string $dep): void
    {
        $this->implies[] = $dep;
        $this->renewStrict();
    }

    public function getImplies(): array
    {
        return $this->implies;
    }

    public function addMissing(string $dep, string $reason): void
    {
        $this->missing[$dep] = $reason;
        $this->renewStrict();
    }

    public function getMissing(): array
    {
        return $this->missing;
    }

    public function addUsing(string $dep): void
    {
        $this->using[] = $dep;
        $this->renewStrict();
    }

    public function getUsing(): array
    {
        return $this->using;
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Gets array of properties.
     *
     * Mainly used for backward compatibility.
     *
     * @return array The properties
     */
    public function dump(): array
    {
        return array_merge($this->default, $this->properties, [
            'id'             => $this->id,
            'enabled'        => $this->state == self::STATE_ENABLED,
            'implies'        => $this->implies,
            'cannot_enable'  => $this->missing,
            'cannot_disable' => $this->using,
        ]);
    }

    /**
     * Store a property and its value
     *
     * @param      string  $identifier  The identifier
     * @param      mixed   $value       The value
     */
    public function set(string $identifier, $value = null): dcModuleDefine
    {
        if (array_key_exists($identifier, $this->default)) {
            $this->properties[$identifier] = $value;
            $this->renewStrict();
        }

        return $this;
    }

    /**
     * Magic function, store a property and its value
     *
     * @param      string  $identifier  The identifier
     * @param      mixed   $value       The value
     */
    public function __set(string $identifier, $value = null)
    {
        $this->set($identifier, $value);
    }

    /**
     * Gets the specified property value (null if does not exist).
     *
     * @param      string  $identifier  The identifier
     *
     * @return     mixed
     */
    public function get(string $identifier)
    {
        if ($identifier == 'id') {
            return $this->id;
        }

        if (array_key_exists($identifier, $this->properties)) {
            return $this->properties[$identifier];
        }

        return array_key_exists($identifier, $this->default) ? $this->default[$identifier] : null;
    }

    /**
     * Gets the specified property value (null if does not exist).
     *
     * @param      string  $identifier  The identifier
     *
     * @return     mixed
     */
    public function __get(string $identifier)
    {
        return $this->get($identifier);
    }

    /**
     * Test if a property exists
     *
     * @param      string  $identifier  The identifier
     *
     * @return     bool
     */
    public function __isset(string $identifier): bool
    {
        return isset($this->properties[$identifier]);
    }

    /**
     * Unset a property
     *
     * @param      string  $identifier  The identifier
     */
    public function __unset(string $identifier)
    {
        if (array_key_exists($identifier, $this->default)) {
            $this->properties[$identifier] = $this->default[$identifier];
            $this->renewStrict();
        }
    }
}
