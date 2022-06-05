<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Modules\Repository;

// Dotclear\Modules\Repository\RepositoryParser
use SimpleXMLElement;
use Dotclear\App;
use Dotclear\Exception\CoreException;

/**
 * Repository modules XML feed parser.
 *
 * Provides an object to parse XML feed of modules from repository.
 *
 * @ingroup  Module Store
 */
class RepositoryParser
{
    /**
     * @var false|SimpleXMLElement $xml
     *                             XML object of feed contents
     */
    protected $xml = false;

    /**
     * @var array<string,array> $items
     *                          Array of feed contents
     */
    protected $items = [];

    /**
     * @var string $bloc
     *             XML bloc tag
     */
    protected static $bloc = 'http://dotaddict.org/da/';

    /**
     * Constructor.
     *
     * @param null|string $data Feed content
     */
    public function __construct(?string $data)
    {
        if (!is_string($data)) {
            throw new CoreException(__('Failed to read data feed'));
        }

        try {
            $this->xml = simplexml_load_string($data);
        } catch (\Exception) {
            $this->xml = false;
        }

        if (false === $this->xml) {
            throw new CoreException(__('Wrong data feed'));
        }

        $this->_parse();

        unset($data, $this->xml);
    }

    /**
     * Parse XML into array.
     */
    protected function _parse(): void
    {
        if (empty($this->xml->module)) {
            return;
        }

        foreach ($this->xml->module as $i) {
            $attrs = $i->attributes();

            $item = [];

            // DC/DA shared markers
            $item['id']          = (string) $attrs['id'];
            $item['file']        = (string) $i->file;
            $item['name']        = (string) $i->name;
            $item['version']     = (string) $i->version;
            $item['author']      = (string) $i->author;
            $item['description'] = (string) $i->desc;

            // DA specific markers
            $item['requires']['core'] = (string) $i->children(self::$bloc)->dcmin;
            $item['details']          = (string) $i->children(self::$bloc)->details;
            $item['section']          = (string) $i->children(self::$bloc)->section;
            $item['support']          = (string) $i->children(self::$bloc)->support;
            $item['screenshot']       = (string) $i->children(self::$bloc)->sshot;

            $tags = [];
            foreach ($i->children(self::$bloc)->tags as $t) {
                $tags[] = (string) $t->tag;
            }
            $item['tags'] = $tags;

            // First filter right now. If level is DEVELOPMENT, all modules are parse
            if (!App::core()->production()
                || App::core()->version()->compareMajorVersions(current: App::core()->config()->get('core_version'), required: $item['requires']['core'], operator: '>=')
                && App::core()->version()->compareMajorVersoins(current: App::core()->config()->get('core_version_break'), required: $item['requires']['core'], operator: '<=')
            ) {
                $this->items[$item['id']] = $item;
            }
        }
    }

    /**
     * Get modules.
     *
     * @return array Modules list
     */
    public function getModules(): array
    {
        return $this->items;
    }
}
