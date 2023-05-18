<?php
/**
 * @brief Plugin My module class.
 * 
 * A plugin My class must extend this class.
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 *
 * @since 2.27
 */
declare(strict_types=1);

namespace Dotclear\Module;

use Dotclear\Helper\Text;

/**
 * Define strict type hinting.
 * 
 * "XxxStrict" class are immutable and returns single type by var.
 */
final class DefineStrict
{
    public readonly string $id;

    public readonly int $state;
    public readonly string $root;
    public readonly string $namespace;
    public readonly bool $root_writable;
    public readonly bool $distributed;

    public readonly string $name;
    public readonly string $desc;
    public readonly string $author;
    public readonly string $version;
    public readonly string $type;

    public readonly string $permissions;
    public readonly int $priority;
    public readonly bool $standalone_config;
    /** @var    array<int,array<int,string>> */
    public readonly array $requires;
    /** @var    array<string,string|false> */
    public readonly array $settings;

    public readonly string $label;
    public readonly string $support;
    public readonly string $details;
    public readonly string $repository;

    public readonly string $parent;
    public readonly string $tplset;

    public readonly string $file;
    public readonly string $current_version;

    public readonly string $section;
    /** @var    array<int,string> */
    public readonly array $tags;
    public readonly string $sshot;
    public readonly int $score;
    public readonly string $dc_min;

    public readonly string $sid;
    public readonly string $sname;

    public readonly string $widgetcontainerformat;
    public readonly string $widgettitleformat;
    public readonly string $widgetsubtitleformat;

    public readonly bool $defined;
    public readonly bool $enabled;
    /** @var    array<int,string> */
    public readonly array $implies;
    /** @var    array<string,string> */
    public readonly array $missing;
    /** @var    array<int,string> */
    public readonly array $using;

    /**
     * Constructor sets properties.
     * 
     * @param   Define  $define     The module define
     */
    public function __construct(Define $define) {

        $sanitize = fn (string $str): string => (string) preg_replace('/[^A-Za-z0-9\@\#+_-]/', '', strtolower($str));

        $this->id            = $define->id;

        // set by dc
        $this->state         = is_numeric($define->property('state')) ? (int) $define->property('state') : Define::STATE_INIT_DISABLED;
        $this->root          = is_string($define->property('root')) ? $define->property('root') : '';
        $this->namespace     = is_string($define->property('namespace')) ? $define->property('namespace') : '';
        $this->root_writable = !empty($define->property('root_writable'));
        $this->distributed   = !empty($define->property('distributed'));

        // required
        $this->name    = is_string($define->property('name')) ? __($define->property('name')) : $this->id;
        $this->desc    = is_string($define->property('desc')) ? __($define->property('desc')) : '';
        $this->author  = is_string($define->property('author')) ? $define->property('author') : 'unknown';
        $this->version = is_string($define->property('version')) ? $define->property('version') : '0';
        $this->type    = is_string($define->property('type')) ? $define->property('type') : Define::DEFAULT_TYPE;

        // optionnal
        $this->permissions       = is_string($define->property('permissions')) ? $define->property('permissions') : '';
        $this->priority          = is_numeric($define->property('priority')) ? (int) $define->property('priority') : Define::DEFAULT_PRIORITY;
        $this->standalone_config = !empty($define->property('standalone_config'));
        $requires                = is_array($define->property('requires')) ? $define->property('requires') : [];
        foreach ($requires as $k => $dep) {
            if (!is_array($dep)) {
                $dep = [$dep];
            }
            if (is_string($dep[0]) && (!isset($dep[1]) || is_string($dep[1]))) {
                $requires[$k] = $dep;
            } else {
                unset($requires[$k]);
            }
        }
        $this->requires = array_values($requires);
        $settings       = is_array($define->property('settings')) ? $define->property('settings') : [];
        foreach ($settings as $k => $v) {
            if (is_string($k) && (is_string($v) || false === $v)) {
                $settings[$k] = $v;
            } else {
                unset($settings[$k]);
            }
        }
        $this->settings = $settings;

        // optionnal++
        $this->label      = is_string($define->property('label')) ? $define->property('label') : $this->name;
        $this->support    = is_string($define->property('support')) ? $define->property('support') : '';
        $this->details    = is_string($define->property('details')) ? $define->property('details') : '';
        $this->repository = is_string($define->property('repository')) ? $define->property('repository') : '';

        // theme specifics
        $this->parent = is_string($define->property('parent')) ? $define->property('parent') : '';
        $this->tplset = is_string($define->property('tplset')) ? $define->property('tplset') : (defined('DC_DEFAULT_TPLSET') ? DC_DEFAULT_TPLSET : '');

        // store specifics
        $this->file            = is_string($define->property('file')) ? $define->property('file') : '';
        $this->current_version = is_string($define->property('current_version')) ? $define->property('current_version') : '0';

        // DA specifics
        $this->section = is_string($define->property('version')) ? $define->property('version') : '0';
        $this->tags    = is_string($define->property('tags')) ? explode(',', $define->property('tags')) : [];
        $this->sshot   = is_string($define->property('sshot')) ? $define->property('sshot') : '';
        $this->score   = is_numeric($define->property('score')) ? (int) $define->property('score') : 0;
        $this->dc_min  = is_string($define->property('dc_min')) ? $define->property('dc_min') : '2.0';

        // modules list specifics
        $this->sid   = $sanitize($define->id);
        $this->sname = $sanitize(strtolower(Text::removeDiacritics($this->name)));

        // special modules
        $this->widgetcontainerformat = is_string($define->property('widgetcontainerformat')) ? $define->property('widgetcontainerformat') : '';
        $this->widgettitleformat     = is_string($define->property('widgettitleformat')) ? $define->property('widgettitleformat') : '';
        $this->widgetsubtitleformat  = is_string($define->property('widgetsubtitleformat')) ? $define->property('widgetsubtitleformat') : '';

        // out of properties
        $this->defined = $this->name != Define::DEFAULT_NAME;
        $this->enabled = $this->state == Define::STATE_ENABLED;
        $this->implies = $define->getImplies();
        $this->missing = $define->getMissing();
        $this->using   = $define->getUsing();
    }

    /**
     * Catch overloaded properties.
     *
     * @return  null
     */
    public function __get(string $property): null
    {
        return null;
    }

    /**
     * Test if a property exists
     *
     * @param      string  $property  The property
     *
     * @return     bool     True on exists
     */
    public function __isset(string $property): bool
    {
        return property_exists($this, $property);
    }

    /**
     * Get an array copy of all properties.
     *
     * @return  array<string,mixed>     The properties
     */
    public function dump(): array
    {
        return get_object_vars($this);
    }
}
