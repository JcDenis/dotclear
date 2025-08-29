<?php

declare(strict_types=1);

namespace Dotclear\Tests\Helper\Html\Form;

use PHPUnit\Framework\TestCase;

class OptgroupTest extends TestCase
{
    public function test(): void
    {
        $component = new \Dotclear\Helper\Html\Form\Optgroup('My Group');
        $rendered  = $component->render();

        $this->assertMatchesRegularExpression(
            '/<optgroup.*?>(?:.*?\n*)?<\/optgroup>/',
            $rendered
        );
    }

    public function testItemsText(): void
    {
        $component = new \Dotclear\Helper\Html\Form\Optgroup('My Group');
        // @phpstan-ignore argument.type
        $component->items([
            'one' => 1,
            'two' => '0',
            'three',
        ]);
        $rendered = $component->render();

        $this->assertStringContainsString(
            '<option value="1">one</option>',
            $rendered
        );
        $this->assertStringContainsString(
            '<option value="0">two</option>',
            $rendered
        );
        $this->assertStringContainsString(
            '<option value="three">0</option>',
            $rendered
        );
    }

    public function testItemsOption(): void
    {
        $component = new \Dotclear\Helper\Html\Form\Optgroup('My Group');
        $component->items([
            new \Dotclear\Helper\Html\Form\Option('One', '1'),
            new \Dotclear\Helper\Html\Form\None(),
        ]);
        $rendered = $component->render();

        $this->assertStringContainsString(
            '<option value="1">One</option>',
            $rendered
        );
    }

    public function testItemsOptgroup(): void
    {
        $component = new \Dotclear\Helper\Html\Form\Optgroup('My Group');
        $component->items([
            (new \Dotclear\Helper\Html\Form\Optgroup('First'))->items([
                new \Dotclear\Helper\Html\Form\Option('One', '1'),
            ]),
        ]);
        $rendered = $component->render();

        $this->assertStringContainsString(
            '<optgroup label="First">',
            $rendered
        );
        $this->assertStringContainsString(
            '<option value="1">One</option>',
            $rendered
        );
        $this->assertStringContainsString(
            '</optgroup>' . "\n" . '</optgroup>',
            $rendered
        );
    }

    public function testItemsArray(): void
    {
        $component = new \Dotclear\Helper\Html\Form\Optgroup('My Group');
        // @phpstan-ignore argument.type
        $component->items([
            'First' => [
                'one' => 1,
                'two' => '0',
                'three',
            ],
        ]);
        $rendered = $component->render();

        $this->assertStringContainsString(
            '<optgroup label="First">',
            $rendered
        );
        $this->assertStringContainsString(
            '<option value="1">one</option>',
            $rendered
        );
        $this->assertStringContainsString(
            '<option value="0">two</option>',
            $rendered
        );
        $this->assertStringContainsString(
            '<option value="three">0</option>',
            $rendered
        );
        $this->assertStringContainsString(
            '</optgroup>' . "\n" . '</optgroup>',
            $rendered
        );
    }

    public function testEmptyItems(): void
    {
        $component = new \Dotclear\Helper\Html\Form\Optgroup('My Group');
        $component->items([]);
        $rendered = $component->render();

        $this->assertStringNotContainsString(
            '<option',
            $rendered
        );
    }

    public function testGetDefaultElement(): void
    {
        $component = new \Dotclear\Helper\Html\Form\Optgroup('My Group');

        $this->assertEquals(
            'optgroup',
            $component->getDefaultElement()
        );
    }

    public function testGetType(): void
    {
        $component = new \Dotclear\Helper\Html\Form\Optgroup('My Group');

        $this->assertEquals(
            'Dotclear\Helper\Html\Form\Optgroup',
            $component->getType()
        );
        $this->assertEquals(
            \Dotclear\Helper\Html\Form\Optgroup::class,
            $component->getType()
        );
    }

    public function testGetElement(): void
    {
        $component = new \Dotclear\Helper\Html\Form\Optgroup('My Group');

        $this->assertEquals(
            'optgroup',
            $component->getElement()
        );
    }

    public function testGetElementWithOtherElement(): void
    {
        $component = new \Dotclear\Helper\Html\Form\Optgroup('My Group', 'span');

        $this->assertEquals(
            'span',
            $component->getElement()
        );
    }
}
