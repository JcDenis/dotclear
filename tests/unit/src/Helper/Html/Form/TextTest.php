<?php

declare(strict_types=1);

namespace Dotclear\Tests\Helper\Html\Form;

use PHPUnit\Framework\TestCase;

class TextTest extends TestCase
{
    public function test(): void
    {
        $component = new \Dotclear\Helper\Html\Form\Text(null, 'TEXT');
        $rendered  = $component->render();

        $this->assertEquals(
            'TEXT',
            $rendered
        );
    }

    public function testWithACommonAttribute(): void
    {
        $component = new \Dotclear\Helper\Html\Form\Text(null, 'TEXT');
        $component->setIdentifier('myid');
        $rendered = $component->render();

        $this->assertMatchesRegularExpression(
            '/<span.*?>(?:.*?\n*)?<\/span>/',
            $rendered
        );
        $this->assertStringContainsString(
            'name="myid"',
            $rendered
        );
        $this->assertStringContainsString(
            'id="myid"',
            $rendered
        );
    }

    public function testGetDefaultElement(): void
    {
        $component = new \Dotclear\Helper\Html\Form\Text();

        $this->assertEquals(
            '',
            $component->getDefaultElement()
        );
    }

    public function testGetType(): void
    {
        $component = new \Dotclear\Helper\Html\Form\Text();

        $this->assertEquals(
            'Dotclear\Helper\Html\Form\Text',
            $component->getType()
        );
        $this->assertEquals(
            \Dotclear\Helper\Html\Form\Text::class,
            $component->getType()
        );
    }

    public function testGetElement(): void
    {
        $component = new \Dotclear\Helper\Html\Form\Text();

        $this->assertEquals(
            '',
            $component->getElement()
        );
    }

    public function testGetElementWithOtherElement(): void
    {
        $component = new \Dotclear\Helper\Html\Form\Text('span');

        $this->assertEquals(
            'span',
            $component->getElement()
        );
    }

    public function testWithItems(): void
    {
        $component = new \Dotclear\Helper\Html\Form\Text(null, 'TEXT');
        $component
            ->separator(' - ')
            ->items([
                (new \Dotclear\Helper\Html\Form\Span('FIRST')),
                (new \Dotclear\Helper\Html\Form\Span('SECOND')),
            ]);
        $rendered = $component->render();

        $this->assertEquals(
            'TEXT<span>FIRST</span> - <span>SECOND</span>',
            $rendered
        );
    }

    public function testWithItemsAndOtherElement(): void
    {
        $component = new \Dotclear\Helper\Html\Form\Text('var', 'TEXT');
        $component
            ->separator(' - ')
            ->items([
                (new \Dotclear\Helper\Html\Form\Span('FIRST')),
                (new \Dotclear\Helper\Html\Form\Span('SECOND')),
            ]);
        $rendered = $component->render();

        $this->assertEquals(
            '<var>TEXT<span>FIRST</span> - <span>SECOND</span></var>',
            $rendered
        );
    }
}
