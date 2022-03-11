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
    public function getContent(bool $absolute_urls = false): string
    {
        if (dotclear()->context() && dotclear()->blog()->settings()->system->use_smilies) {
            $c = parent::getContent($absolute_urls);

            dotclear()->context()->getSmilies();

            return dotclear()->context()->addSmilies($c);
        }

        return parent::getContent($absolute_urls);
    }
}
