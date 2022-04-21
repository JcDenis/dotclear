<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\RsExt;

// Dotclear\Core\RsExt\RsExtCommentPublic

/**
 * Comments record public helpers.
 *
 * @ingroup  Core Public Comment Record
 */
class RsExtCommentPublic extends RsExtComment
{
    public function getContent(bool $absolute_urls = false): string
    {
        if (dotclear()->context() && dotclear()->blog()->settings()->get('system')->get('use_smilies')) {
            $c = parent::getContent($absolute_urls);

            dotclear()->context()->getSmilies();

            return dotclear()->context()->addSmilies($c);
        }

        return parent::getContent($absolute_urls);
    }
}
