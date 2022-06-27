<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\User\Preferences;

// Dotclear\Core\User\Preferences\RteFlags
use Dotclear\App;

/**
 * rte flags helper.
 *
 * @ingroup  Core User Preference Stack
 */
class RteFlags
{
    /**
     * @var array<string,array> $flags
     */
    private $flags = [];

    public function __construct()
    {
        $this->setFlag('blog_descr', __('Blog description (in blog parameters)'), true);
        $this->setFlag('cat_descr', __('Category description'), true);

        App::core()->behavior('adminAfterSetRteFlags')->call($this);
    }

    public function setFlag(string $id, string $label, bool $flag)
    {
        $this->flags[$id] = [$flag, $label];
    }

    public function getFlags(): array
    {
        return $this->flags;
    }
}
