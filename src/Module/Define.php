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
declare(strict_types=1);

namespace Dotclear\Module;

use Dotclear\Helper\Text;

class Define
{
    /** @var    int     Enabled state. */
    public const STATE_ENABLED       = 0;

    /** @var    int     Default init disabled stat. */
    public const STATE_INIT_DISABLED = 1;

    /** @var    int     Soft disabled state. */
    public const STATE_SOFT_DISABLED = 2;

    /** @var    int     Hard disabled state. */
    public const STATE_HARD_DISABLED = 4;

    /** @var    string  Default (undeifned) module name. */
    public const DEFAULT_NAME = 'undefined';

    /** @var    string  Default (undefined) module type. */
    public const DEFAULT_TYPE = 'undefined';

    /** @var    int     Default module priority. */
    public const DEFAULT_PRIORITY = 1000;

    /** @var    array<int,string>   Forbidden direct modification. */
    public const LOCKED_PROPERTIES = [
        'id',
        'defined',
        'enabled',
        'implies',
        'missing',
        'using',
    ];

    /** @var    string  The module sanitized ID. */
    public readonly string $sid;

    /** @var    int     The module state, according to constants Define::STATE_XXX. */
    public int $state = self::STATE_INIT_DISABLED;

    /** @var string     The module root directory path. */
    public string $root = '';

    /** @var    string  The module namespace (files should by in module\src path). */
    public string $namespace = '';

    /** @var    bool    The module root writable. */
    public bool $root_writable = false;

    /** @var    bool    The module is part of dotclear distribution. */
    public bool $distributed = false;

    /** @var    string  The module translated name. */
    public string $name = self::DEFAULT_NAME;

    /** @var    string  The module sanitized name. */
    public string $sname = self::DEFAULT_NAME;

    /** @var    string  The module translated short decription. */
    public string $desc = '';

    /** @var    string  The module author(s). */
    public string $author = '';

    /** @var    string  The module version. */
    public string $version = '0';

    /** @var    string  The module type (plugin or theme). */
    public string $type = self::DEFAULT_TYPE;

    /** @var    string  The module comma separted list of permissions. */
    public string $permissions = '';

    /** @var    int     The module priority (only absolute integer, lower number is higher priority). */
    public int $priority = self::DEFAULT_PRIORITY;

    /** @var    bool    The module uses standalone configartor. */
    public bool $standalone_config = false;

    /** @var    array<int,array<int,string>> The module requirements (dependencies). */
    public array $requires = [];

    /** @var    array<string,string|false> The modules settings (bakcend configuration pages links). */
    public array $settings = [];

    /** @var    string  The mdole label (deprecated). */
    public string $label = '';

    /** @var    string  The module support URL */
    public string $support = '';

    /** @var    string  The module detail URL. */
    public string $details = '';

    /** @var    string  The module dcstore.xml erpository URL. */
    public string $repository = '';

    /** @var    string  The module (theme) parent theme ID. */
    public string $parent = '';

    /** @var    string  The module (theme) template set. (dotty, mustek) */
    public string $tplset = '';

    /** @var    string  The module store download file URL. */
    public string $file = '';

    /** @var    string  The module current version (when store takes module version setting). */
    public string $current_version = '0';

    /** @var    string  The module section (from store categories). */
    public string $section = '';

    /** @var    string  The module tags (from store tags). */
    public string $tags = '';

    /** @var    string  The module screenshot URL. (Could be deprecated soon). */
    public string $sshot = '';

    /** @var    int     The module score (from searsh or repository). */
    public int $score = 0;

    /** @var    string  The module dotclear minimum version. (from store or requirements). */
    public string $dc_min = '2.0';

    /** @var    string  Widgets specitic container format. */
    public string $widgetcontainerformat = '';

    /** @var    string  Widgets specitic title format. */
    public string $widgettitleformat = '';

    /** @var    string  Widgets specitic subtitle format. */
    public string $widgetsubtitleformat = '';

    /** @var    bool    The module is defined. (unknown module returns false here). */
    private bool $defined = false;

    /** @var    bool    The module is enabled. (state=Define::STAT_ENABLED and defined=true) */
    private bool $enabled = false;

    /** @var    array<int,string>   Modules implied by the module. */
    private array $implies = [];

    /** @var    array<string,string>    Missing module required by the module. */
    private array $missing = [];

    /** @var    array<int,string>   Modules required by the module. */
    private array $using = [];

    /**
     * Create a module definition.
     *
     * @param   string  $id The module identifier (root path)
     */
    final public function __construct(
        public readonly string $id
    ) {
        $this->sid   = $this->sanitizeProperty($this->id);
        $this->init();
        $this->sanitizeProperties();
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
     * @return  bool    True on defined
     */
    public function isDefined(): bool
    {
        return $this->name != self::DEFAULT_NAME;
    }

    /**
     * Check if module is enabled.
     *
     * @return  bool    True on enabled
     */
    public function isEnabled(): bool
    {
        return $this->state == self::STATE_ENABLED;
    }

    /**
     * Add "implies" dependency.
     *
     * @param   string  $id     The module ID
     */
    public function addImplies(string $id): void
    {
        $this->implies[] = $id;
    }

    /**
     * Get "implies" dependencies.
     *
     * @return  array<int,string>   The dependencies
     */
    public function getImplies(): array
    {
        return $this->implies;
    }

    /**
     * Add "missing" dependency.
     *
     * @param   string  $id         The module ID
     * @param   string  $reason     The reason
     */
    public function addMissing(string $id, string $reason): void
    {
        $this->missing[$id] = $reason;
    }

    /**
     * Get "missing" dependencies.
     *
     * @return  array<string,string>   The dependencies
     */
    public function getMissing(): array
    {
        return $this->missing;
    }

    /**
     * Add "using" dependency.
     *
     * @param   string  $id     The module ID
     */
    public function addUsing(string $id): void
    {
        $this->using[] = $id;
    }

    /**
     * Get "using" dependencies.
     *
     * @return  array<int,string>   The dependencies
     */
    public function getUsing(): array
    {
        return $this->using;
    }

    /**
     * Sanitize a property.
     *
     * Only property of type string can be sanitized.
     * This does not affect internal property.
     *
     * @return string   The sanitized property
     */
    public function sanitizeProperty(string $property): string
    {
        return property_exists($this, $property) && gettype($this->{$property}) == 'string' ?
            (string) preg_replace('/[^A-Za-z0-9\@\#+_-]/', '', strtolower(Text::removeDiacritics($this->{$property}))) : '';
    }

    /**
     * Sanitize module properties.
     * 
     * This cleans, transforms, fills some properties.
     */
    public function sanitizeProperties(): void
    {
        if (!$this->isDefined()) {
            return;
        }

        $this->name  = empty($this->name) ? $this->id : __($this->name);
        $this->desc  = __($this->desc);
        $this->sname = $this->sanitizeProperty('name');
        if ($this->priority < 0) {
            $this->priority = 1;
        }
        if (empty($this->label)) {
            $this->label = $this->name;
        }
    }

    /**
     * Gets array of properties.
     *
     * @return  array<string,mixed>     The properties
     */
    public function dump(): array
    {
        $this->sanitizeProperties();

        return get_object_vars($this);
    }

    /**
     * Check if a property exists.
     *
     * @param   string  $property   The property
     *
     * @return  bool    True on exists
     */
    public function has(string $property)
    {
        return property_exists($this, $property);
    }

    /**
     * Gets the specified property value (null if does not exist).
     *
     * @param   string  $property    The property
     *
     * @return  mixed
     */
    public function get(string $property): mixed
    {
        return $this->has($property) ? $this->{$property} : null;
    }

    /**
     * Store a property and its value.
     *
     * @param      string  $property    The property
     * @param      mixed   $value       The value
     */
    public function set(string $property, $value = null): Define
    {
        if (!in_array($property, self::LOCKED_PROPERTIES) && $this->has($property) && gettype($this->{$property}) == gettype($value)) {
            $this->{$property} = $value;
        }

        return $this;
    }

    /**
     * Get module id.
     *
     * @deprecated  2.27    Use public property
     *
     * @return  string  The module ID
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Gets the specified property value (null if does not exist).
     *
     * @deprecated  2.27    Use public property.
     *
     * @param      string  $property    The property
     *
     * @return     mixed
     */
    public function __get(string $property): mixed
    {
        return $this->get($property);
    }
}
