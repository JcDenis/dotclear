<?php
/**
 * @class Dotclear\Core\RsExt\RsExtCommentPublic
 * @brief Dotclear comment record public helpers.
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\RsExt;

use Dotclear\Core\RsExt\RsExtComment;

class RsExtCommentPublic extends RsExtComment
{
    public static function getContent($rs, $absolute_urls = false)
    {
        if (isset(dcCore()->context) && dcCore()->blog->settings->system->use_smilies) {
            $c = parent::getContent($rs, $absolute_urls);

            dcCore()->context->getSmilies();

            return dcCore()->context->addSmilies($c);
        }

        return parent::getContent($rs, $absolute_urls);
    }
}