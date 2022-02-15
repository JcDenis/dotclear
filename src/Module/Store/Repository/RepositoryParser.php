<?php
/**
 * @class Dotclear\Module\Store\Repository\RepositoryParser
 * @brief Repository modules XML feed parse
 *
 * Provides an object to parse XML feed of modules from repository.
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module\Store\Repository;

use SimpleXMLElement;

use Dotclear\Core\Utils;
use Dotclear\Exception\CoreException;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class RepositoryParser
{
    /** @var    SimpleXMLElement    XML object of feed contents */
    protected $xml;

    /** @var    array    Array of feed contents */
    protected $items;

    /** @var    string    XML bloc tag */
    protected static $bloc = 'http://dotaddict.org/da/';

    /**
     * Constructor.
     *
     * @param    string|null    $data        Feed content
     */
    public function __construct(?string $data)
    {
        if (!is_string($data)) {
            throw new CoreException(__('Failed to read data feed'));
        }

        try {
            $this->xml   = simplexml_load_string($data);
        } catch(\Exception) {
            $this->xml = false;
        }
        $this->items = [];

        if ($this->xml === false) {
            throw new CoreException(__('Wrong data feed'));
        }

        $this->_parse();

        unset($data, $this->xml);
    }

    /**
     * Parse XML into array
     */
    protected function _parse(): void
    {
        if (empty($this->xml->module)) {
            return;
        }

        foreach ($this->xml->module as $i) {
            $attrs = $i->attributes();

            $item = [];

            # DC/DA shared markers
            $item['id']             = (string) $attrs['id'];
            $item['file']           = (string) $i->file;
            $item['name']           = (string) $i->name;
            $item['version']        = (string) $i->version;
            $item['author']         = (string) $i->author;
            $item['description']    = (string) $i->desc;

            # DA specific markers
            $item['dc_min']     = (string) $i->children(self::$bloc)->dcmin;
            $item['details']    = (string) $i->children(self::$bloc)->details;
            $item['section']    = (string) $i->children(self::$bloc)->section;
            $item['support']    = (string) $i->children(self::$bloc)->support;
            $item['screenshot'] = (string) $i->children(self::$bloc)->sshot;

            $tags = [];
            foreach ($i->children(self::$bloc)->tags as $t) {
                $tags[] = (string) $t->tag;
            }
            $item['tags'] = $tags;

            # First filter right now. If level is DEVELOPMENT, all modules are parse
            if (dotclear()->config()->run_level >= DOTCLEAR_RUN_DEVELOPMENT
                || Utils::versionsCompare(dotclear()->config()->core_version, $item['dc_min'], '>=', false)
                && Utils::versionsCompare(dotclear()->config()->core_version_break, $item['dc_min'], '<=', false)
            ) {
                $this->items[$item['id']] = $item;
            }
        }
    }

    /**
     * Get modules.
     *
     * @return  array   Modules list
     */
    public function getModules(): array
    {
        return $this->items;
    }
}
