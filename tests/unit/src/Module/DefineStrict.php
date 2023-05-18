<?php
/**
 * Unit tests
 *
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */

// This statement may broke class mocking system:
// declare(strict_types=1);

namespace tests\unit\Dotclear\Module;

require_once implode(DIRECTORY_SEPARATOR, [__DIR__, '..', '..', 'bootstrap.php']);

use atoum;

/*
 * @tags DefineStrict
 */
class DefineStrict extends atoum
{
    private function getDefine()
    {
        return new \Dotclear\Module\Define('test');
    }

    /**
     * Test magic __constructor.
     *
     * Should always has class \Dotclear\Module\DefineStrict ?!
     */
    public function test()
    {
        $this
            ->given($define = new \Dotclear\Module\Define('test'))
            ->and($strict = new \Dotclear\Module\DefineStrict($define))
            ->object($strict)
            ->isNotNull()
            ->class('\Dotclear\Module\DefineStrict')
        ;
    }

    /**
     * Test magic __get.
     *
     * Undefined class properties should always return null
     */
    public function testGet()
    {
        $define = self::getDefine();
        $strict = $define->strict();

        $this
            ->variable($strict->__get('defined'))
            ->isEqualTo(null)
        ;

        $this
            ->variable($strict->__get('undefined'))
            ->isEqualTo(null)
        ;
    }

    /**
     * Test magic __isset.
     *
     * Should return true on existing class property, else false
     */
    public function testIsset()
    {
        $define = self::getDefine();
        $strict = $define->strict();

        $this
            ->variable($strict->__isset('defined'))
            ->isEqualTo(true)
        ;

        $this
            ->variable($strict->__isset('undefined'))
            ->isEqualTo(false)
        ;
    }

    /**
     * Test dump of class properties.
     *
     * Class properties should be in returned array
     * Update of Define class property should update its DefineStrict class property
     */
    public function testDump()
    {
        $define = self::getDefine();
        $dump   = $define->strict()->dump();

        $this
            ->variable($dump['defined'])
            ->isEqualTo(false)
        ;

        $define->set('name', 'test');
        $dump = $define->strict()->dump();

        $this
            ->variable($dump['defined'])
            ->isEqualTo(true)
        ;
    }
}