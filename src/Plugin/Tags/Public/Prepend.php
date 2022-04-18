<?php
/**
 * @note Dotclear\Plugin\Tags\Public\Prepend
 * @brief Dotclear Plugins class
 *
 * @ingroup  PluginTags
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

class Prepend extends AbstractPrepend
{
    use TraitPrependPublic;

    public function loadModule(): void
    {
        // Localized string we find in template
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
