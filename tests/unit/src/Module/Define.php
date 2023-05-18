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
 * @tags Define
 */
class Define extends atoum
{
    private function getDefine()
    {
        return new \Dotclear\Module\Define('test');
    }

    /**
     * Test magic __constructor.
     *
     * Should always has class \Dotclear\Module\Define ?!
     */
    public function test()
    {
        $this
            ->given($define = new \Dotclear\Module\Define('test'))
            ->object($define)
            ->isNotNull()
            ->class('\Dotclear\Module\Define')
        ;
    }

    /**
     * Test property.
     *
     * This method should the property value or null on undefined property
     */
    public function testProperty()
    {
        $define = self::getDefine();
        $define->set('priority', 10000);

        $this
            ->variable($define->property('name'))
            ->isEqualTo(\Dotclear\Module\Define::DEFAULT_NAME)

            ->variable($define->property('distributed'))
            ->isEqualTo(false)

            ->variable($define->property('undefined'))
            ->isEqualTo(null)

            ->variable($define->property('priority'))
            ->isEqualTo(10000)
        ;
    }

    public function testImplies()
    {
        $define = self::getDefine();

        $this
            ->variable($define->getImplies())
            ->isEqualTo([])

            ->and($define->addImplies('imply'))
            ->variable($define->getImplies())
            ->isEqualTo(['imply'])

            ->and($define->addImplies('bis'))
            ->variable($define->getImplies())
            ->isEqualTo(['imply', 'bis'])
        ;
    }

    public function testMissing()
    {
        $define = self::getDefine();

        $this
            ->array($define->getMissing())
            ->isEqualTo([])

            ->and($define->addMissing('miss', 'because'))
            ->variable($define->getMissing())
            ->isEqualTo(['miss' => 'because'])

            ->and($define->addMissing('bis', 'biscause'))
            ->variable($define->getMissing())
            ->isEqualTo(['miss' => 'because', 'bis' => 'biscause'])
        ;
    }

    public function testUsing()
    {
        $define = self::getDefine();

        $this
            ->variable($define->getUsing())
            ->isEqualTo([])

            ->and($define->addUsing('use'))
            ->variable($define->getUsing())
            ->isEqualTo(['use'])

            ->and($define->addUsing('bis'))
            ->variable($define->getUsing())
            ->isEqualTo(['use', 'bis'])
        ;
    }

    public function testIsDefined()
    {
        $define = self::getDefine();

        $this
            ->variable($define->isDefined())
            ->isEqualTo(false)

            ->and($define->set('name', 'good'))
            ->variable($define->isDefined())
            ->isEqualTo(true)
        ;
    }

    public function testGetId()
    {
        $define = self::getDefine();

        $this
            ->variable($define->getId())
            ->isEqualTo('test')
        ;
    }

    public function testGetSet()
    {
        $define = self::getDefine();

        $this
            ->and($define->set('name', 'myname'))
            ->variable($define->get('name'))
            ->isEqualTo('myname')

            ->and($define->name  = 'myothername')
            ->variable($define->name)
            ->isEqualTo('myothername')
        ;
    }

    public function testIssetUnset()
    {
        $define = self::getDefine();

        $this
            ->variable($define->__isset('name'))
            ->isEqualTo(false)

            ->and($define->set('name', 'myname'))
            ->variable($define->__isset('name'))
            ->isEqualTo(true)

            ->and($define->__unset('name'))
            ->variable($define->__isset('name'))
            ->isEqualTo($define::DEFAULT_NAME)
        ;
    }

    public function testDump()
    {
        $define = self::getDefine();

        $this
            ->given($dump = $define->dump())
            ->variable($dump['id'])
            ->isEqualTo('test')
            // as dump come from DefineStrict, root should not be null but empty string
            ->variable($dump['root'])
            ->isEqualTo('')
            // as dump come from DefineStrict, tags should not be string but array
            ->variable($dump['tags'])
            ->isEqualTo([0 => ''])
            // as dump come from DefineStrict, defined should exist
            ->variable($dump['defined'])
            ->isEqualTo(false)
        ;
    }
}