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
use Dotclear\App;

/**
 * Comments record public helpers.
 *
 * @ingroup  Core Public Comment Record
 */
class RsExtCommentPublic extends RsExtComment
{
    public function getContent(bool $absolute_urls = false): string
    {
        if (App::core()->context() && App::core()->blog()->settings()->getGroup('system')->getSetting('use_smilies')) {
            $c = parent::getContent($absolute_urls);

            App::core()->context()->getSmilies();

            return App::core()->context()->addSmilies($c);
        }

        return parent::getContent($absolute_urls);
    }
}
