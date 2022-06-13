<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Antispam\Common;

// Dotclear\Core\RsExt\RsExtComment
use Dotclear\App;
use Dotclear\Core\RsExt\RsExtend;
use Dotclear\Database\Statement\SelectStatement;

/**
 * Comment Record extension of plugin Antispam.
 *
 * This class adds new methods to database comment results.
 * You can call them on every record comming from dcBlog::getComments and similar
 * methods.
 *
 * @ingroup  Plugin Antispam Record
 */
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
        $sql = new SelectStatement();
        $sql->column($field);
        $sql->from(App::core()->prefix() . 'comment');
        $sql->where('comment_id = ' . $this->rs->integer('comment_id'));
        $sql->limit(1);
        $record = $sql->select();

        return $record->isEmpty() ? null : $record->field($field);
    }
}
