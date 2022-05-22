<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Notice;

// Dotclear\Process\Admin\Notice\NoticeParam
use Dotclear\Database\Param;

/**
 * Notice query parameter helper.
 *
 * @ingroup  Core Notice Param
 */
final class NoticeParam extends Param
{
    /**
     * Get notices belonging to given sessions id.
     *
     * @param string $default The default session id
     *
     * @return string The session id
     */
    public function ses_id(string $default = ''): string
    {
        return $this->getCleanedValue('ses_id', 'string', $default);
    }

    /**
     * Get notices belonging to given notice id.
     *
     * @return array<int,int> The notice(s) id(s)
     */
    public function notice_id(): array
    {
        return $this->getCleanedValues('notice_id', 'int');
    }

    /**
     * Get notices belonging to given notice type(s).
     *
     * @return array<int,string> The notices type(s)
     */
    public function notice_type(): array
    {
        return $this->getCleanedValues('notice_type', 'string');
    }

    /**
     * Get notices belonging to given notice format(s).
     *
     * @return array<int,string> The notices format(s)
     */
    public function notice_format(): array
    {
        $res = $values = [];
        if (is_array($this->get('notice_format'))) {
            $values = $this->get('notice_format');
        } else {
            $values[] = $this->get('notice_format');
        }

        foreach ($values as $value) {
            if (is_string($value) && in_array($value, ['text', 'html'])) {
                $values[] = $value;
            }
        }

        return $res;
    }
}
