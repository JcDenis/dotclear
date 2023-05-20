<?php
/**
 * @brief Repository modules XML feed parser
 *
 * Provides an object to parse XML feed of modules from a repository.
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 *
 * @since 2.6
 */
use Dotclear\Module\Define;

class dcStoreParser
{
    /**
     * XML object of feed contents
     *
     * @var    false|SimpleXMLElement
     */
    protected $xml;

    /**
     * Array of feed contents
     *
     * @var    array
     */
    protected $items = [];

    /**
     * Array of Define instances of feed contents
     *
     * @var    array<int,Define>
     */
    protected $defines = [];

    /**
     * XML bloc tag
     *
     * @var    string
     */
    protected static $bloc = 'http://dotaddict.org/da/';

    /**
     * Constructor.
     *
     * @param    string    $data        Feed content
     */
    public function __construct(string $data)
    {
        $this->xml = simplexml_load_string($data);

        if ($this->xml === false) {
            throw new Exception(__('Wrong data feed'));
        }

        $this->_parse();

        $this->xml = false;
        unset($data);
    }

    /**
     * Parse XML into array
     */
    protected function _parse()
    {
        if (empty($this->xml->module)) {
            return;
        }

        foreach ($this->xml->module as $i) {
            $attrs = $i->attributes();
            if (!isset($attrs['id'])) {
                continue;
            }

            $define = new Define((string) $attrs['id']);

            # DC/DA shared markers
            $define->set('file', (string) $i->file);
            $define->set('label', (string) $i->name); // deprecated
            $define->set('name', (string) $i->name);
            $define->set('version', (string) $i->version);
            $define->set('author', (string) $i->author);
            $define->set('desc', (string) $i->desc);

            # DA specific markers
            $define->set('dc_min', (string) $i->children(self::$bloc)->dcmin);
            $define->set('details', (string) $i->children(self::$bloc)->details);
            $define->set('section', (string) $i->children(self::$bloc)->section);
            $define->set('support', (string) $i->children(self::$bloc)->support);
            $define->set('sshot', (string) $i->children(self::$bloc)->sshot);

            $tags = [];
            foreach ($i->children(self::$bloc)->tags as $t) {
                $tags[] = (string) $t->tag;
            }
            $define->set('tags', implode(', ', $tags));

            # First filter right now. If DC_DEV is set all modules are parse
            if (defined('DC_DEV') && DC_DEV === true || dcUtils::versionsCompare(DC_VERSION, $define->dc_min, '>=', false)) {
                $this->defines[] = $define;
            }
        }
    }

    /**
     * Get modules Defines.
     *
     * @return    array        Modules Define list
     */
    public function getDefines(): array
    {
        return $this->defines;
    }

    /**
     * Get modules.
     *
     * @deprecated since 2.26 Use self::getDefines()
     *
     * @return    array        Modules list
     */
    public function getModules(): array
    {
        dcDeprecated::set('dcStoreParser::getDefines()', '2.26');

        // fill property once on demand
        if (empty($this->items) && !empty($this->defines)) {
            foreach ($this->defines as $define) {
                $this->items[$define->id] = $define->dump();
            }
        }

        return $this->items;
    }
}
