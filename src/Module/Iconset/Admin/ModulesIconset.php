<?php
/**
 * @class Dotclear\Module\Iconset\Admin\ModulesIconset
 *
 * @package Dotclear
 * @subpackage Module
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module\Iconset\Admin;

use Dotclear\Module\AbstractModules;
use Dotclear\Module\TraitModulesAdmin;
use Dotclear\Module\Iconset\TraitModulesIconset;

class ModulesIconset extends AbstractModules
{
    use TraitModulesAdmin, TraitModulesIconset;

    public function getModulesURL(array $param = []): string
    {
        return $this->core->adminurl->get('admin.iconset', $param);
    }

    public function getModuleURL(string $id, array $param = []): string
    {
        return $this->core->adminurl->get('admin.iconset.' . $id, $param);
    }
/*
    public function displayModules(array $cols = ['name', 'version', 'desc'], array $actions = [], bool $nav_limit = false): AbstractModules
    {

        return $this;
    }
*/
}
