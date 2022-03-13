<?php
/**
 * @class Dotclear\Plugin\ImportExport\Admin\Lib\Module
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginImportExport
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\ImportExport\Admin\Lib;

use Dotclear\Exception\ModuleException;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

abstract class Module
{
    public $type;
    public $id;
    public $name;
    public $description;

    protected $import_url;
    protected $export_url;
    protected $url;

    public function __construct()
    {
        $this->setInfo();

        if (!in_array($this->type, ['import', 'export'])) {
            throw new ModuleException(sprintf('Unknown type for module %s', get_class($this)));
        }

        if (!$this->name) {
            $this->name = get_class($this);
        }

        $this->id  = get_class($this); //join('', array_slice(explode('\\', get_class($this)), -1));;
        $this->url = dotclear()->adminurl()->get('admin.plugin.ImportExport', ['type' => $this->type, 'module' => $this->id], '&');
    }

    public function init()
    {
    }

    abstract protected function setInfo();

    final public function getURL($escape = false)
    {
        return $escape ? Html::escapeHTML($this->url) : $this->url;
    }

    abstract public function process($do);

    abstract public function gui();

    protected function progressBar($percent)
    {
        $percent = ceil($percent);
        if ($percent > 100) {
            $percent = 100;
        }

        return '<div class="ie-progress"><div style="width:' . $percent . '%">' . $percent . ' %</div></div>';
    }

    protected function autoSubmit()
    {
        return Form::hidden(['autosubmit'], 1);
    }

    protected function congratMessage()
    {
        return
        '<h3>' . __('Congratulation!') . '</h3>' .
        '<p class="success">' . __('Your blog has been successfully imported. Welcome on Dotclear 2!') . '</p>' .
        '<ul><li><strong><a href="' . dotclear()->adminurl()->get('admin.post') . '">' . __('Why don\'t you blog this now?') . '</a></strong></li>' .
        '<li>' . __('or') . ' <a href="' . dotclear()->adminurl()->get('admin.home') . '">' . __('visit your dashboard') . '</a></li></ul>';
    }
}
