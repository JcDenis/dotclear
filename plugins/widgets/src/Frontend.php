<?php
/**
 * @package     Dotclear
 *
 * @copyright   Olivier Meunier & Association Dotclear
 * @copyright   AGPL-3.0
 */
declare(strict_types=1);

namespace Dotclear\Plugin\widgets;

use Dotclear\App;
use Dotclear\Helper\Process\TraitProcess;

/**
 * @brief   The module frontend process.
 * @ingroup widgets
 */
class Frontend
{
    use TraitProcess;

    public static function init(): bool
    {
        return self::status(My::checkContext(My::FRONTEND));
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        Widgets::init();

        App::frontend()->template()->addValue('Widgets', FrontendTemplate::tplWidgets(...));
        App::frontend()->template()->addBlock('Widget', FrontendTemplate::tplWidget(...));
        App::frontend()->template()->addBlock('IfWidgets', FrontendTemplate::tplIfWidgets(...));

        return true;
    }
}
