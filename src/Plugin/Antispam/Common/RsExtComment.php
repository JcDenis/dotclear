<?php
/**
 * @class Dotclear\Core\RsExt\RsExtComment
 * @brief Dotclear comment record helpers.

 * This class adds new methods to database comment results.
 * You can call them on every record comming from dcBlog::getComments and similar
 * methods.

 * @warning You should not give the first argument (usualy $rs) of every described
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Antispam\Common;

use Dotclear\Core\RsExt\RsExtend;
use Dotclear\Database\Record;

class RsExtComment extends RsExtend
{
    public function spamFilter(): ?string
    {
        return self::spamField('comment_spam_filter');
    }

    public function spamStatus(): ?string
    {
        return self::spamField('comment_spam_status');
    }

    private function spamField(string $field): ? string
    {
        $rspam = dotclear()->con()->select('SELECT ' . $field . ' FROM ' . dotclear()->prefix . "comment WHERE comment_id = " . $this->rs->fInt('comment_id') . " LIMIT 1 ");

        return $rspam->isEmpty() ? null : $rspam->{$field};
    }
}
