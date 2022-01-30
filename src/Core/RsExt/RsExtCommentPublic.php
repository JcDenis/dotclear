<?php
/**
 * @class Dotclear\Core\RsExt\rsExtCommentPublic
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

class rsExtCommentPublic extends rsExtComment
{
    public static function getContent($rs, $absolute_urls = false)
    {
        if (isset(dcCore()->context) && dcCore()->blog->settings->system->use_smilies) {
            $c = parent::getContent($rs, $absolute_urls);

            if (!isset($GLOBALS['__smilies'])) {
                $GLOBALS['__smilies'] = dcCore()->context::getSmilies(dcCore()->blog);
            }

            return dcCore()->context::addSmilies($c);
        }

        return parent::getContent($rs, $absolute_urls);
    }
}
