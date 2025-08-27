<?php

declare(strict_types=1);

namespace Dotclear\Tests\Helper\Html\Form;

use PHPUnit\Framework\TestCase;

class NoneTest extends TestCase
{
    public function test(): void
    {
        $component = new \Dotclear\Helper\Html\Form\None();
        $rendered  = $component->render();

        $this->assertEquals(
            '',
            $rendered
        );
    }

    public function testGetDefaultElement(): void
    {
        $component = new \Dotclear\Helper\Html\Form\None();

        $this->assertEquals(
            '',
            $component->getDefaultElement()
        );
    }

    public function testGetType(): void
    {
        $component = new \Dotclear\Helper\Html\Form\None();

        $this->assertEquals(
            'Dotclear\Helper\Html\Form\None',
            $component->getType()
        );
        $this->assertEquals(
            \Dotclear\Helper\Html\Form\None::class,
            $component->getType()
        );
    }
}
