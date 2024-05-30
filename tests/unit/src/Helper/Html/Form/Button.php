<?php
/**
 * Unit tests
 *
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace tests\unit\Dotclear\Helper\Html\Form;

require_once implode(DIRECTORY_SEPARATOR, [__DIR__, '..', '..', '..', '..', 'bootstrap.php']);

use atoum;

class Button extends atoum
{
    public function test()
    {
        $component = new \Dotclear\Helper\Html\Form\Button('my', 'value');

        $this
            ->string($component->render())
            ->match('/<input type="button".*?>/')
            ->contains('name="my"')
            ->contains('id="my"')
            ->contains('value="value"')
        ;
    }

    public function testWithoutValue()
    {
        $component = new \Dotclear\Helper\Html\Form\Button('my');

        $this
            ->string($component->render())
            ->match('/<input type="button".*?>/')
            ->contains('name="my"')
            ->contains('id="my"')
            ->notContains('value="value"')
        ;
    }

    public function testWithoutNameOrId()
    {
        $component = new \Dotclear\Helper\Html\Form\Button();

        $this
            ->string($component->render())
            ->isEmpty()
        ;
    }

    public function testWithoutNameOrIdAndWithAValue()
    {
        $component = new \Dotclear\Helper\Html\Form\Button(null, 'value');

        $this
            ->string($component->render())
            ->isEmpty()
        ;
    }
}
