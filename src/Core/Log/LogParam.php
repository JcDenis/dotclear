<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Log;

// Dotclear\Core\Log\LogParam
use Dotclear\Database\Param;

/**
 * Log query parameter helper.
 *
 * Usage of Param instance for log table:
 * $param = new Param();
 * $param->set('blog_id', '*');
 * $result = App:core()->log()->getLogs(param: $param);
 *
 * @ingroup  Core Log Param
 */
final class LogParam extends Param
{
    /**
     * Get logs belonging to given blog ID.
     *
     * @return null|string The blog id
     */
    public function blog_id(): ?string
    {
        return $this->getCleanedValue('blog_id', 'string');
    }

    /**
     * Get logs belonging to given user ID.
     *
     * @return array<int,string> The user(s) id(s)
     */
    public function user_id(): array
    {
        return $this->getCleanedValues('user_id', 'string');
    }

    /**
     * Get logs belonging to given IP address.
     *
     * @return array<int,string> The IP(s) address(es)
     */
    public function log_ip(): array
    {
        return $this->getCleanedValues('log_ip', 'string');
    }

    /**
     * Get logs belonging to given log table.
     *
     * @return array<int,string> The log table(s)
     */
    public function log_table(): array
    {
        return $this->getCleanedValues('log_table', 'string');
    }

    /**
     * Get logs belonging to a given message.
     *
     * @return array<int,string> The log message(s)
     */
    public function log_msg(): array
    {
        return $this->getCleanedValue('log_msg', 'string');
    }
}
