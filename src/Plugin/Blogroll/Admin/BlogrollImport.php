<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Blogroll\Admin;

use StdClass;
use Dotclear\Exception\ModuleException;

/**
 * Import methods for plugin Blogroll.
 *
 * \Dotclear\Plugin\Blogroll\Admin\BlogrollImport
 *
 * @ingroup  Plugin Blogroll
 */
class BlogrollImport
{
    /** @var array<int, string>        Imported entries */
    protected $entries = [];

    public static function loadFile(string $file): array
    {
        if (file_exists($file) && is_readable($file)) {
            $importer = new BlogrollImport();
            $importer->parse(file_get_contents($file));

            return $importer->getAll();
        }

        return [];
    }

    public function parse(string $data): void
    {
        if (preg_match('!<xbel(\s+version)?!', $data)) {
            $this->_parseXBEL($data);
        } elseif (preg_match('!<opml(\s+version)?!', $data)) {
            $this->_parseOPML($data);
        } else {
            throw new ModuleException(__('You need to provide a XBEL or OPML file.'));
        }
    }

    protected function _parseOPML(string $data): void
    {
        $xml = @simplexml_load_string($data);
        if (!$xml) {
            throw new ModuleException(__('File is not in XML format.'));
        }

        $outlines = $xml->xpath('//outline');

        $this->entries = [];
        foreach ($outlines as $outline) {
            if (isset($outline['htmlUrl'])) {
                $link = $outline['htmlUrl'];
            } elseif (isset($outline['url'])) {
                $link = $outline['url'];
            } else {
                continue;
            }

            $entry        = new StdClass();
            $entry->link  = $link;
            $entry->title = (!empty($outline['title'])) ? $outline['title'] : '';
            if (empty($entry->title)) {
                $entry->title = (!empty($outline['text'])) ? $outline['text'] : $entry->link;
            }
            $entry->desc     = (!empty($outline['description'])) ? $outline['description'] : '';
            $this->entries[] = $entry;
        }
    }

    protected function _parseXBEL(string $data): void
    {
        $xml = @simplexml_load_string($data);
        if (!$xml) {
            throw new ModuleException(__('File is not in XML format.'));
        }

        $outlines = $xml->xpath('//bookmark');

        $this->entries = [];
        foreach ($outlines as $outline) {
            if (!isset($outline['href'])) {
                continue;
            }

            $entry        = new StdClass();
            $entry->link  = $outline['href'];
            $entry->title = (!empty($outline->title)) ? $outline->title : '';
            if (empty($entry->title)) {
                $entry->title = $entry->link;
            }
            $entry->desc     = (!empty($outline->desc)) ? $outline->desc : '';
            $this->entries[] = $entry;
        }
    }

    public function getAll(): array
    {
        return $this->entries;
    }
}
