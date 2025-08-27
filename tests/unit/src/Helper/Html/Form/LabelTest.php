<?php

declare(strict_types=1);

namespace Dotclear\Tests\Helper\Html\Form;

use PHPUnit\Framework\TestCase;

class LabelTest extends TestCase
{
    public function test(): void
    {
        $component = new \Dotclear\Helper\Html\Form\Label('My Label');
        $rendered  = $component->render();

        $this->assertMatchesRegularExpression(
            '/<label.*?>(?:.*?\n*)?<\/label>/',
            $rendered
        );
        $this->assertStringContainsString(
            '>My Label',
            $rendered
        );
    }

    public function testInsideTextBefore(): void
    {
        $component = new \Dotclear\Helper\Html\Form\Label('My Label', \Dotclear\Helper\Html\Form\Label::INSIDE_TEXT_BEFORE, 'myid');
        $rendered  = $component->render('<slot id="myid"></slot>');

        $this->assertStringContainsString(
            '<label>My Label <slot id="myid"></slot></label>',
            $rendered
        );
    }

    public function testInsideTextAfter(): void
    {
        $component = new \Dotclear\Helper\Html\Form\Label('My Label', \Dotclear\Helper\Html\Form\Label::INSIDE_TEXT_AFTER, 'myid');
        $rendered  = $component->render('<slot id="myid"></slot>');

        $this->assertStringContainsString(
            '<label><slot id="myid"></slot> My Label</label>',
            $rendered
        );
    }

    public function testOutsideLabelBefore(): void
    {
        $component = new \Dotclear\Helper\Html\Form\Label('My Label', \Dotclear\Helper\Html\Form\Label::OUTSIDE_LABEL_BEFORE, 'myid');
        $rendered  = $component->render('<slot id="myid"></slot>');

        $this->assertStringContainsString(
            '<label for="myid">My Label</label> <slot id="myid"></slot>',
            $rendered
        );
    }

    public function testOutsideLabelAfter(): void
    {
        $component = new \Dotclear\Helper\Html\Form\Label('My Label', \Dotclear\Helper\Html\Form\Label::OUTSIDE_LABEL_AFTER, 'myid');
        $rendered  = $component->render('<slot id="myid"></slot>');

        $this->assertStringContainsString(
            '<slot id="myid"></slot> <label for="myid">My Label</label>',
            $rendered
        );
    }

    public function testOutsideLabelBeforeWithoutId(): void
    {
        $component = new \Dotclear\Helper\Html\Form\Label('My Label', \Dotclear\Helper\Html\Form\Label::OUTSIDE_LABEL_BEFORE);
        $rendered  = $component->render('<slot id="myid"></slot>');

        $this->assertStringContainsString(
            '<label>My Label</label> <slot id="myid"></slot>',
            $rendered
        );
    }

    public function testOutsideLabelAfterWithoutId(): void
    {
        $component = new \Dotclear\Helper\Html\Form\Label('My Label', \Dotclear\Helper\Html\Form\Label::OUTSIDE_LABEL_AFTER);
        $rendered  = $component->render('<slot id="myid"></slot>');

        $this->assertStringContainsString(
            '<slot id="myid"></slot> <label>My Label</label>',
            $rendered
        );
    }

    public function testFalsyPosition(): void
    {
        $component = new \Dotclear\Helper\Html\Form\Label('My Label', 99, 'myid');
        $rendered  = $component->render('<slot id="myid"></slot>');

        $this->assertStringContainsString(
            '<label>My Label <slot id="myid"></slot></label>',
            $rendered
        );
    }

    public function testGetDefaultElement(): void
    {
        $component = new \Dotclear\Helper\Html\Form\Label('My Label');

        $this->assertEquals(
            'label',
            $component->getDefaultElement()
        );
    }

    public function testGetType(): void
    {
        $component = new \Dotclear\Helper\Html\Form\Label('My Label');

        $this->assertEquals(
            'Dotclear\Helper\Html\Form\Label',
            $component->getType()
        );
        $this->assertEquals(
            \Dotclear\Helper\Html\Form\Label::class,
            $component->getType()
        );
    }

    public function testGetPosition(): void
    {
        $component = new \Dotclear\Helper\Html\Form\Label('My Label');

        $this->assertEquals(
            \Dotclear\Helper\Html\Form\Label::INSIDE_TEXT_BEFORE,
            $component->getPosition()
        );
    }

    public function testSetPosition(): void
    {
        $component = new \Dotclear\Helper\Html\Form\Label('My Label');
        $component->setPosition(\Dotclear\Helper\Html\Form\Label::INSIDE_TEXT_BEFORE);

        $this->assertEquals(
            \Dotclear\Helper\Html\Form\Label::INSIDE_TEXT_BEFORE,
            $component->getPosition()
        );
    }

    public function testSetFalsyPosition(): void
    {
        $component = new \Dotclear\Helper\Html\Form\Label('My Label');
        $component->setPosition(99);

        $this->assertEquals(
            \Dotclear\Helper\Html\Form\Label::INSIDE_TEXT_BEFORE,
            $component->getPosition()
        );
    }
}
