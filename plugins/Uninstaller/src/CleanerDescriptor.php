<?php
/**
 * @package     Dotclear
 *
 * @copyright   Olivier Meunier & Association Dotclear
 * @copyright   GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Uninstaller;

/**
 * @brief   Cleaner descriptor.
 * @ingroup Uninstaller
 */
class CleanerDescriptor
{
    /**
     * The actions descriptions.
     *
     * @var     array<string,ActionDescriptor>  $actions
     */
    public readonly array $actions;

    /**
     * Contructor populate descriptor properties.
     *
     * @param   string                          $id         The cleaner ID
     * @param   string                          $name       The cleaner name
     * @param   string                          $desc       The cleaner description
     * @param   array<int,ActionDescriptor>     $actions    The actions descriptions
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $desc,
        array $actions
    ) {
        $valid = [];
        foreach ($actions as $action) {
            if (is_a($action, ActionDescriptor::class) && $action->id != 'undefined') {
                $valid[$action->id] = $action;
            }
        }
        $this->actions = $valid;
    }

    /**
     * Get descriptor properties.
     *
     * @return  array<string,mixed>     The properties
     */
    public function dump(): array
    {
        return get_object_vars($this);
    }
}
