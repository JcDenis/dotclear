<?php
/**
 * @class Dotclear\UtCore\Instanceils\TraitBehavior
 * @brief Dotclear trait Behavior
 *
 * @package Dotclear
 * @subpackage Instance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Instance;

use Dotclear\Core\Instance\Behavior;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

trait TraitBehavior
{
    /** @var    Behavior    Behavior instance */
    private $behavior;

    /** @var array              top behaviors */
    protected static $top_behaviors = [];

    /**
     * Get instance
     *
     * @return  Behavior    Behavior instance
     */
    public function behavior(): Behavior
    {
        if (!($this->behavior instanceof Behavior)) {
            $this->behavior = new Behavior();
        }

        return $this->behavior;
    }

    /**
     * Add Top Behavior statically before class instanciate
     *
     * ::addTopBehavior('MyBehavior', 'MyFunction');
     * also work from other child class.
     * Do not add top behavior on trait class but on class that
     * contains trait.
     *
     * @param  string           $behavior   The behavior
     * @param  string|array     $callback   The function
     */
    public static function addTopBehavior(string $behavior, string|array $callback): void
    {
        array_push(self::$top_behaviors, [$behavior, $callback]);
    }

    /**
     * Register Top Behaviors into class instance behaviors
     */
    protected function registerTopBehaviors(): void
    {
        foreach (self::$top_behaviors as $behavior) {
            $this->behavior()->add($behavior[0], $behavior[1]);
        }
    }
}
