<?php

declare(strict_types=1);

namespace Dotclear\Tests\Helper\Html\Form;

use PHPUnit\Framework\TestCase;

class TheadTest extends TestCase
{
    public function test(): void
    {
        $component = new \Dotclear\Helper\Html\Form\Thead();
        $rendered  = $component->render();

        $this->assertMatchesRegularExpression(
            '/<thead.*?>(?:.*?\n*)?<\/thead>/',
            $rendered
        );
    }

    public function testWithEmptyItems(): void
    {
        $component = new \Dotclear\Helper\Html\Form\Thead();
        $component->items([
        ]);
        $rendered = $component->render();

        $this->assertMatchesRegularExpression(
            '/<thead.*?><\/thead>/',
            $rendered
        );
    }

    public function testWithRows(): void
    {
        $component = new \Dotclear\Helper\Html\Form\Thead();
        $component->rows([
            (new \Dotclear\Helper\Html\Form\Tr()),
            (new \Dotclear\Helper\Html\Form\None()),
            (new \Dotclear\Helper\Html\Form\Tr()),
        ]);
        $rendered = $component->render();

        $this->assertMatchesRegularExpression(
            '/<thead.*?><tr><\/tr>\n*<tr><\/tr>\n*<\/thead>/',
            $rendered
        );
    }

    public function testWithItems(): void
    {
        $component = new \Dotclear\Helper\Html\Form\Thead();
        $component->items([
            (new \Dotclear\Helper\Html\Form\Tr()),
            (new \Dotclear\Helper\Html\Form\None()),
            (new \Dotclear\Helper\Html\Form\Tr()),
        ]);
        $rendered = $component->render();

        $this->assertMatchesRegularExpression(
            '/<thead.*?><tr><\/tr>\n*<tr><\/tr>\n*<\/thead>/',
            $rendered
        );
    }

    public function testWithId(): void
    {
        $component = new \Dotclear\Helper\Html\Form\Thead('myid');
        $rendered  = $component->render();

        $this->assertMatchesRegularExpression(
            '/<thead.*?>(?:.*?\n*)?<\/thead>/',
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
        $component = new \Dotclear\Helper\Html\Form\Thead();

        $this->assertEquals(
            'thead',
            $component->getDefaultElement()
        );
    }

    public function testGetType(): void
    {
        $component = new \Dotclear\Helper\Html\Form\Thead();

        $this->assertEquals(
            'Dotclear\Helper\Html\Form\Thead',
            $component->getType()
        );
        $this->assertEquals(
            \Dotclear\Helper\Html\Form\Thead::class,
            $component->getType()
        );
    }

    public function testGetElement(): void
    {
        $component = new \Dotclear\Helper\Html\Form\Thead();

        $this->assertEquals(
            'thead',
            $component->getElement()
        );
    }

    public function testGetElementWithOtherElement(): void
    {
        $component = new \Dotclear\Helper\Html\Form\Thead('my', 'div');

        $this->assertEquals(
            'div',
            $component->getElement()
        );
    }
}
