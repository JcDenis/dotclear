<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Action;

// Dotclear\Process\Admin\Action\ActionDescriptor
use Dotclear\Exception\MissingOrEmptyValue;

/**
 * Actions descriptor.
 *
 * @ingroup  Admin Action
 */
final class ActionDescriptor
{
    /**
     * Constructor.
     *
     * We can not check here if callback is callable as
     * we want callable methods to be protected inside Action class.
     *
     * @param array<string,string> $actions  The actions [name => id]
     * @param callable             $callback The action callback
     * @param string               $group    The group of actions
     * @param bool                 $hidden   Is hidden action
     */
    public function __construct(
        public readonly array $actions,
        public readonly mixed $callback,
        public readonly string $group = '',
        public readonly bool $hidden = false,
    ) {
        if (empty($this->actions)) {
            throw new MissingOrEmptyValue(__('No actions given.'));
        }
    }
}
