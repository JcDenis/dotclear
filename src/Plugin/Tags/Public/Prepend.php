<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Tags\Public;

// Dotclear\Plugin\Tags\Public\Prepend
use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependPublic;
use Dotclear\Plugin\Tags\Common\TagsUrl;
use Dotclear\Plugin\Tags\Common\TagsXmlrpc;
use Dotclear\Plugin\Tags\Common\TagsWidgets;

/**
 * Public prepend for plugin Tags.
 *
 * @ingroup  Plugin Tags
 */
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
