<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Helper\Network\Xmlrpc;

/**
 * XML-RPC Value.
 *
 * \Dotclear\Helper\Network\Xmlrpc\Value
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * @ingroup  Helper Network Xmlrpc
 */
class Value
{
    /** @var string Data type */
    protected $type;

    /**
     * Constructor.
     *
     * @param mixed       $data Data value
     * @param null|string $type Data type
     */
    public function __construct(protected mixed $data, ?string $type = null)
    {
        if (!$type) {
            $type = $this->calculateType();
        }
        $this->type = $type;
        if ('struct' == $type) {
            // Turn all the values in the array in to new xmlrpcValue objects
            foreach ($this->data as $key => $value) {
                $this->data[$key] = new Value($value);
            }
        }
        if ('array' == $type) {
            for ($i = 0, $j = count($this->data); $i < $j; ++$i) {
                $this->data[$i] = new Value($this->data[$i]);
            }
        }
    }

    /**
     * XML Data.
     *
     * Returns an XML subset of the Value.
     */
    public function getXml(): string
    {
        // Return XML for this value
        switch ($this->type) {
            case 'boolean':
                return '<boolean>' . (($this->data) ? '1' : '0') . '</boolean>';

            case 'int':
                return '<int>' . $this->data . '</int>';

            case 'double':
                return '<double>' . $this->data . '</double>';

            case 'string':
                return '<string>' . htmlspecialchars($this->data) . '</string>';

            case 'array':
                $return = '<array><data>' . "\n";
                foreach ($this->data as $item) {
                    $return .= '  <value>' . $item->getXml() . "</value>\n";
                }
                $return .= '</data></array>';

                return $return;

            case 'struct':
                $return = '<struct>' . "\n";
                foreach ($this->data as $name => $value) {
                    $return .= "  <member><name>{$name}</name><value>";
                    $return .= $value->getXml() . "</value></member>\n";
                }
                $return .= '</struct>';

                return $return;

            case 'date':
            case 'base64':
                return $this->data->getXml();
        }

        return '';
    }

    /**
     * Calculate Type.
     *
     * Returns the type of the value if it was not given in constructor.
     */
    protected function calculateType(): string
    {
        if (true === $this->data || false === $this->data) {
            return 'boolean';
        }
        if (is_integer($this->data)) {
            return 'int';
        }
        if (is_double($this->data)) {
            return 'double';
        }
        // Deal with xmlrpc object types base64 and date
        if (is_object($this->data) && $this->data instanceof Date) {
            return 'date';
        }
        if (is_object($this->data) && $this->data instanceof Base64) {
            return 'base64';
        }
        // If it is a normal PHP object convert it in to a struct
        if (is_object($this->data)) {
            $this->data = get_object_vars($this->data);

            return 'struct';
        }
        if (!is_array($this->data)) {
            return 'string';
        }
        // We have an array - is it an array or a struct ?
        if ($this->isStruct($this->data)) {
            return 'struct';
        }

        return 'array';
    }

    /**
     * Data is struct.
     *
     * Returns true if <var>$array</var> is a Struct and not only an Array.
     *
     * @param array $array Array
     */
    protected function isStruct(array $array): bool
    {
        // Nasty function to check if an array is a struct or not
        $expected = 0;
        foreach ($array as $key => $value) {
            if ((string) $key != (string) $expected) {
                return true;
            }
            ++$expected;
        }

        return false;
    }
}
