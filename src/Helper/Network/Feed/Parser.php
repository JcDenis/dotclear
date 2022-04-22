<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Helper\Network\Feed;

// Dotclear\Helper\Network\Feed\Parser
use stdClass;
use SimpleXMLElement;

/**
 * Feed parser.
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * This class can read RSS 1.0, RSS 2.0, Atom 0.3 and Atom 1.0 feeds. Works with
 * {@link Reader}
 *
 * @ingroup  Helper Network Feed
 */
class Parser
{
    /**
     * @var string $feed_type
     *             Feed type
     */
    public $feed_type;

    /**
     * @var string $title
     *             Feed title
     */
    public $title;

    /**
     * @var string $link
     *             Feed link
     */
    public $link;

    /**
     * @var string $description
     *             Feed description
     */
    public $description;

    /**
     * @var string $pubdate
     *             Feed publication date
     */
    public $pubdate;

    /**
     * @var string $generator
     *             Feed generator
     */
    public $generator;

    /**
     * @var array $items
     *            Feed items
     */
    public $items = [];

    /**
     * @var SimpleXMLElement $xml
     *                       Feed XML content
     */
    protected $xml;

    /**
     * Constructor.
     *
     * Takes some <var>$data</var> as input. Returns false if data is
     * not a valid XML stream. If everything's fine, feed is parsed and items
     * are in {@link $items} property.
     *
     * @param string $data XML stream
     */
    public function __construct(string $data)
    {
        $this->xml = @simplexml_load_string($data);

        if (!$this->xml) {
            return;
        }

        if (preg_match('/<rdf:RDF/', (string) $data)) {
            $this->parseRSSRDF();
        } elseif (preg_match('/<rss/', (string) $data)) {
            $this->parseRSS();
        } elseif (preg_match('!www.w3.org/2005/Atom!', (string) $data)) {
            $this->parseAtom10();
        } else {
            $this->parseAtom03();
        }

        unset($data, $this->xml);
    }

    /**
     * RSS 1.0 parser.
     */
    protected function parseRSSRDF(): void
    {
        $this->feed_type = 'rss 1.0 (rdf)';

        $this->title       = (string) $this->xml->channel->title;
        $this->link        = (string) $this->xml->channel->link;
        $this->description = (string) $this->xml->channel->description;
        $this->pubdate     = (string) $this->xml->channel->children('http://purl.org/dc/elements/1.1/')->date;

        // Feed generator agent
        $g = $this->xml->channel->children('http://webns.net/mvcb/')->generatorAgent;
        if ($g) {
            $g               = $g->attributes('http://www.w3.org/1999/02/22-rdf-syntax-ns#');
            $this->generator = (string) $g['resource'];
        }

        if (empty($this->xml->item)) {
            return;
        }

        foreach ($this->xml->item as $i) {
            $item              = new stdClass();
            $item->title       = (string) $i->title;
            $item->link        = (string) $i->link;
            $item->creator     = (string) $i->children('http://purl.org/dc/elements/1.1/')->creator;
            $item->description = (string) $i->description;
            $item->content     = (string) $i->children('http://purl.org/rss/1.0/modules/content/')->encoded;
            $item->subject     = $this->nodes2array($i->children('http://purl.org/dc/elements/1.1/')->subject);
            $item->pubdate     = (string) $i->children('http://purl.org/dc/elements/1.1/')->date;
            $item->TS          = strtotime($item->pubdate);

            $item->guid = (string) $item->link;
            if (!empty($i->attributes('http://www.w3.org/1999/02/22-rdf-syntax-ns#')->about)) {
                $item->guid = (string) $i->attributes('http://www.w3.org/1999/02/22-rdf-syntax-ns#')->about;
            }

            $this->items[] = $item;
        }
    }

    /**
     * RSS 2.0 parser.
     */
    protected function parseRSS(): void
    {
        $this->feed_type = 'rss ' . $this->xml['version'];

        $this->title       = (string) $this->xml->channel->title;
        $this->link        = (string) $this->xml->channel->link;
        $this->description = (string) $this->xml->channel->description;
        $this->pubdate     = (string) $this->xml->channel->pubDate;

        $this->generator = (string) $this->xml->channel->generator;

        if (empty($this->xml->channel->item)) {
            return;
        }

        foreach ($this->xml->channel->item as $i) {
            $item              = new stdClass();
            $item->title       = (string) $i->title;
            $item->link        = (string) $i->link;
            $item->creator     = (string) $i->children('http://purl.org/dc/elements/1.1/')->creator;
            $item->description = (string) $i->description;
            $item->content     = (string) $i->children('http://purl.org/rss/1.0/modules/content/')->encoded;

            $item->subject = array_merge(
                $this->nodes2array($i->children('http://purl.org/dc/elements/1.1/')->subject),
                $this->nodes2array($i->category)
            );

            $item->pubdate = (string) $i->pubDate;
            if (!$item->pubdate && !empty($i->children('http://purl.org/dc/elements/1.1/')->date)) {
                $item->pubdate = (string) $i->children('http://purl.org/dc/elements/1.1/')->date;
            }

            $item->TS = strtotime($item->pubdate);

            $item->guid = (string) $item->link;
            if (!empty($i->guid)) {
                $item->guid = (string) $i->guid;
            }

            $this->items[] = $item;
        }
    }

    /**
     * Atom 0.3 parser.
     */
    protected function parseAtom03(): void
    {
        $this->feed_type = 'atom 0.3';

        $this->title       = (string) $this->xml->title;
        $this->description = (string) $this->xml->subtitle;
        $this->pubdate     = (string) $this->xml->modified;

        $this->generator = (string) $this->xml->generator;

        foreach ($this->xml->link as $link) {
            if ('alternate' == $link['rel'] && ('text/html' == $link['type'] || 'application/xhtml+xml' == $link['type'])) {
                $this->link = (string) $link['href'];

                break;
            }
        }

        if (empty($this->xml->entry)) {
            return;
        }

        foreach ($this->xml->entry as $i) {
            $item = new stdClass();

            foreach ($i->link as $link) {
                if ('alternate' == $link['rel'] && ('text/html' == $link['type'] || 'application/xhtml+xml' == $link['type'])) {
                    $item->link = (string) $link['href'];

                    break;
                }

                $item->link = (string) $link['href'];
            }

            $item->title       = (string) $i->title;
            $item->creator     = (string) $i->author->name;
            $item->description = (string) $i->summary;
            $item->content     = (string) $i->content;
            $item->subject     = $this->nodes2array($i->children('http://purl.org/dc/elements/1.1/')->subject);
            $item->pubdate     = (string) $i->modified;
            $item->TS          = strtotime($item->pubdate);

            $this->items[] = $item;
        }
    }

    /**
     * Atom 1.0 parser.
     */
    protected function parseAtom10(): void
    {
        $this->feed_type = 'atom 1.0';

        $this->title       = (string) $this->xml->title;
        $this->description = (string) $this->xml->subtitle;
        $this->pubdate     = (string) $this->xml->updated;

        $this->generator = (string) $this->xml->generator;

        foreach ($this->xml->link as $link) {
            if ('alternate' == $link['rel'] && ('text/html' == $link['type'] || 'application/xhtml+xml' == $link['type'])) {
                $this->link = (string) $link['href'];

                break;
            }
        }

        if (empty($this->xml->entry)) {
            return;
        }

        foreach ($this->xml->entry as $i) {
            $item = new stdClass();

            foreach ($i->link as $link) {
                if ('alternate' == $link['rel'] && ('text/html' == $link['type'] || 'application/xhtml+xml' == $link['type'])) {
                    $item->link = (string) $link['href'];

                    break;
                }

                $item->link = (string) $link['href'];
            }

            $item->title       = (string) $i->title;
            $item->creator     = (string) $i->author->name;
            $item->description = (string) $i->summary;
            $item->content     = (string) $i->content;
            $item->subject     = $this->nodes2array($i->children('http://purl.org/dc/elements/1.1/')->subject);
            $item->pubdate     = !empty($i->published) ? (string) $i->published : (string) $i->updated;
            $item->TS          = strtotime($item->pubdate);

            $this->items[] = $item;
        }
    }

    /**
     * SimpleXML to array.
     *
     * Converts a SimpleXMLElement to an array.
     *
     * @param SimpleXMLElement $node SimpleXML Node
     */
    protected function nodes2array(SimpleXMLElement &$node): array
    {
        if (empty($node)) {
            return [];
        }

        $res = [];
        foreach ($node as $v) {
            $res[] = (string) $v;
        }

        return $res;
    }
}
