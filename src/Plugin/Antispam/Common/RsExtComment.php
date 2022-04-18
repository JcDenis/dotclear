<?php
/**
 * @note Dotclear\Core\RsExt\RsExtComment
 * @brief Dotclear comment record helpers.
 *
 * This class adds new methods to database comment results.
 * You can call them on every record comming from dcBlog::getComments and similar
 * methods.
 *
 * @warning You should not give the first argument (usualy $rs) of every described
 *
 * @ingroup  PluginAntispam
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Antispam\Common;

use Dotclear\Core\RsExt\RsExtend;
use Dotclear\Database\Statement\SelectStatement;

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

    private function spamField(string $field): ?string
    {
        $rs = SelectStatement::init(__METHOD__)->column($field)->from(dotclear()->prefix . 'comment')->where('comment_id = ' . $this->rs->fInt('comment_id'))->limit(1)->select();

        return $rs->isEmpty() ? null : $rs->f($field);
    }
}
