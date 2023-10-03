<?php
/**
 * @package     Dotclear
 *
 * @copyright   Olivier Meunier & Association Dotclear
 * @copyright   GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\attachments;

use ArrayObject;

/**
 * @brief   The module frontend behvaiors.
 * @ingroup attachments
 */
class FrontendBehaviors
{
    /**
     * Extends tpl:EntryIf attributes.
     *
     * attributes:
     *
     *      has_attachment  (0|1)   Entry has an one or several attachments (if 1), or not (if 0)
     *
     * @param   string                      $tag        The current tag
     * @param   ArrayObject<string, mixed>  $attr       The attributes
     * @param   string                      $content    The content
     * @param   ArrayObject<string>         $if         The conditions stack
     */
    public static function tplIfConditions(string $tag, ArrayObject $attr, string $content, ArrayObject $if): void
    {
        if ($tag == 'EntryIf' && isset($attr['has_attachment'])) {
            $sign = (bool) $attr['has_attachment'] ? '' : '!';
            $if[] = $sign . 'App::frontend()->ctx->posts->countMedia(\'attachment\')';
        }
    }
}
