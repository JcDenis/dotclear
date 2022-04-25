<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\ImportExport\Admin\Lib;

// Dotclear\Plugin\ImportExport\Admin\Lib\Module
use Dotclear\Exception\ModuleException;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;

/**
 * Generic module for plugin ImportExport.
 *
 * @ingroup  Plugin ImportExport
 */
abstract class Module
{
    /**
     * @var string $type
     *             The module type (import or export)
     */
    public $type;

    /**
     * @var string $id
     *             The module id
     */
    public $id;

    /**
     * @var string $name
     *             The module name
     */
    public $name;

    /**
     * @var string $description
     *             The module description
     */
    public $description;

    /**
     * @var string $url
     *             GUI URL
     */
    protected $url;

    /**
     * Constructor.
     */
    final public function __construct()
    {
        $this->setInfo();

        if (!in_array($this->type, ['import', 'export'])) {
            throw new ModuleException(sprintf('Unknown type for module %s', get_class($this)));
        }

        if (!$this->name) {
            $this->name = get_class($this);
        }

        $this->id  = get_class($this); // join('', array_slice(explode('\\', get_class($this)), -1));;
        $this->url = dotclear()->adminurl()->get('admin.plugin.ImportExport', ['type' => $this->type, 'module' => $this->id], '&');
    }

    /**
     * Set module info on class construction.
     */
    abstract protected function setInfo(): void;

    /**
     * Initialize additional module stuff on demand.
     *
     * This is called on admin page of plugin ImportExport.
     */
    public function init(): void
    {
    }

    final public function getURL(bool $escape = false): string
    {
        return $escape ? Html::escapeHTML($this->url) : $this->url;
    }

    abstract public function process(string $do): void;

    abstract public function gui(): void;

    protected function progressBar(int $percent): string
    {
        $percent = ceil($percent);
        if (100 < $percent) {
            $percent = 100;
        }

        return '<div class="ie-progress"><div style="width:' . $percent . '%">' . $percent . ' %</div></div>';
    }

    protected function autoSubmit(): string
    {
        return Form::hidden(['autosubmit'], 1);
    }

    protected function congratMessage(): string
    {
        return
        '<h3>' . __('Congratulation!') . '</h3>' .
        '<p class="success">' . __('Your blog has been successfully imported. Welcome on Dotclear 2!') . '</p>' .
        '<ul><li><strong><a href="' . dotclear()->adminurl()->get('admin.post') . '">' . __('Why don\'t you blog this now?') . '</a></strong></li>' .
        '<li>' . __('or') . ' <a href="' . dotclear()->adminurl()->get('admin.home') . '">' . __('visit your dashboard') . '</a></li></ul>';
    }
}
