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
use Dotclear\Plugin\Tags\Common\TagsUrl;
use Dotclear\Plugin\Tags\Common\TagsXmlrpc;
use Dotclear\Plugin\Tags\Common\TagsWidgets;
use Dotclear\Plugin\Tags\Public\TagsBehavior;
use Dotclear\Plugin\Tags\Public\TagsTemplate;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class Prepend extends AbstractPrepend
{
    use TraitPrependPublic;

    public function loadModule(): void
    {
        # Localized string we find in template
        __("This tag's comments Atom feed");
        __("This tag's entries Atom feed");

        $this->addTemplatePath();
        new TagsUrl();
        new TagsTemplate();
        new TagsBehavior();
        new TagsXmlrpc();
        new TagsWidgets();
    }
}
