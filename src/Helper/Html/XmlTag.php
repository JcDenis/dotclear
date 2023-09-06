<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Helper\Html;

/**
 * @class XmlTag
 * @brief XML Tree
 */
class XmlTag
{
    /**
     * XML tag name
     */
    private ?string $_name = null;

    /**
     * XML tag attributes
     */
    private array $_attr = [];

    /**
     * XML tag nodes (childs)
     *
     * @var        array
     */
    private $_nodes = [];

    /**
     * Constructor
     *
     * Creates the root XML tag named <var>$name</var>. If content is given,
     * it will be appended to root tag with {@link insertNode()}
     *
     * @param string        $name           Tag name
     * @param mixed         $content        Tag content
     */
    public function __construct(?string $name = null, $content = null)
    {
        $this->_name = $name;

        if ($content !== null) {
            $this->insertNode($content);
        }
    }

    /**
     * Add Attribute
     *
     * Magic __set method to add an attribute.
     *
     * @param string    $name        Attribute name
     * @param mixed     $value       Attribute value
     *
     * @see insertAttr()
     */
    public function __set(string $name, $value): void
    {
        $this->insertAttr($name, $value);
    }

    /**
     * Add a tag
     *
     * This magic __call method appends a tag to XML tree.
     *
     * @param string    $name        Tag name
     * @param array     $args        Function arguments, the first one would be tag content
     */
    public function __call(string $name, array $args)
    {
        if (!preg_match('#^[a-z_]#', $name)) {
            return false;
        }

        if (!isset($args[0])) {
            $args[0] = null;
        }

        $this->insertNode(new self($name, $args[0]));
    }

    /**
     * Add CDATA
     *
     * Appends CDATA to current tag.
     *
     * @param string    $value        Tag CDATA content
     */
    public function CDATA(string $value): void
    {
        $this->insertNode($value);
    }

    /**
     * Add Attribute
     *
     * This method adds an attribute to current tag.
     *
     * @param string    $name         Attribute name
     * @param mixed     $value        Attribute value
     *
     * @see insertAttr()
     */
    public function insertAttr(string $name, $value): void
    {
        $this->_attr[$name] = $value;
    }

    /**
     * Insert Node
     *
     * This method adds a new XML node. Node could be a instance of XmlTag, an
     * array of valid values, a boolean or a string.
     *
     * @param XmlTag|array|bool|string    $node    Node value
     */
    public function insertNode($node = null): void
    {
        if ($node instanceof self) {
            $this->_nodes[] = $node;
        } elseif (is_array($node)) {
            $child = new self(null);
            foreach ($node as $tag => $n) {
                $child->insertNode(new self($tag, $n));
            }
            $this->_nodes[] = $child;
        } elseif (is_bool($node)) {
            $this->_nodes[] = $node ? '1' : '0';
        } else {
            $this->_nodes[] = (string) $node;
        }
    }

    /**
     * XML Result
     *
     * Returns a string with XML content.
     *
     * @param bool      $prolog          Append prolog to result
     * @param string    $encoding        Result charset
     *
     * @return string
     */
    public function toXML(bool $prolog = false, string $encoding = 'UTF-8'): string
    {
        if ($this->_name && count($this->_nodes) > 0) {
            $format = '<%1$s%2$s>%3$s</%1$s>';
        } elseif ($this->_name && count($this->_nodes) === 0) {
            $format = '<%1$s%2$s/>';
        } else {
            $format = '%3$s';
        }

        $res = $attr = $content = '';

        foreach ($this->_attr as $k => $v) {
            $attr .= ' ' . $k . '="' . htmlspecialchars((string) $v, ENT_QUOTES, $encoding) . '"';
        }

        foreach ($this->_nodes as $node) {
            if ($node instanceof self) {
                $content .= $node->toXML();
            } else {
                $content .= htmlspecialchars((string) $node, ENT_QUOTES, $encoding);
            }
        }

        $res = sprintf($format, $this->_name, $attr, $content);

        if ($prolog && $this->_name) {
            $res = '<?xml version="1.0" encoding="' . $encoding . '" ?>' . "\n" . $res;
        }

        return $res;
    }
}
