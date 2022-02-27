<?php
/**
 * @class Dotclear\Plugin\Tags\Public\Prepend
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginTags
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Tags\Public;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependPublic;
use Dotclear\Plugin\Tags\Lib\TagsPublic;
use Dotclear\Plugin\Tags\Lib\TagsTemplate;
use Dotclear\Plugin\Tags\Lib\TagsUrl;
use Dotclear\Plugin\Tags\Lib\TagsXmlrpc;
use Dotclear\Plugin\Tags\Lib\TagsWidgets;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class Prepend extends AbstractPrepend
{
    use TraitPrependPublic;

    public static function loadModule(): void
    {
        # Localized string we find in template
        __("This tag's comments Atom feed");
        __("This tag's entries Atom feed");

        static::addTemplatePath();
        TagsUrl::initTags();
        TagsTemplate::initTags();
        TagsPublic::initTags();
        TagsXmlrpc::initTags();
        TagsWidgets::initTags();
    }
}
