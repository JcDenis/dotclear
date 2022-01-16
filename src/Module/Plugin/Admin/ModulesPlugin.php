<?php
/**
 * @class Dotclear\Module\Plugin\Admin\ModulesPlugin
 *
 * @package Dotclear
 * @subpackage Module
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module\Plugin\Admin;

use Dotclear\Module\AbstractModules;
use Dotclear\Module\TraitModulesAdmin;
use Dotclear\Module\Plugin\TraitModulesPlugin;

class ModulesPlugin extends AbstractModules
{
    use TraitModulesAdmin, TraitModulesPlugin;

    public function getModulesURL(array $param = []): string
    {
        return $this->core->adminurl->get('admin.plugins', $param);
    }

    public function getModuleURL(string $id, array $param = []): string
    {
        return $this->core->adminurl->get('admin.plugin.' . $id, $param);
    }
}
