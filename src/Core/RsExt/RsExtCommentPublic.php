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

use Dotclear\Public\Context;

class rsExtCommentPublic extends rsExtComment
{
    public static function getContent($rs, $absolute_urls = false)
    {
        if ($rs->core->blog->settings->system->use_smilies) {
            $c = parent::getContent($rs, $absolute_urls);

            if (!isset($GLOBALS['__smilies'])) {
                $GLOBALS['__smilies'] = context::getSmilies($rs->core->blog);
            }

            return context::addSmilies($c);
        }

        return parent::getContent($rs, $absolute_urls);
    }
}
