<?php
/**
 * @class Dotclear\Plugin\Antispam\Lib\Spamfilter
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginAntispam
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Antispam\Lib;

use Dotclear\Html\Html;
use Dotclear\Database\Record;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class Spamfilter
{
    public $id;
    public $name;
    public $description;
    public $active      = true;
    public $order       = 100;
    public $auto_delete = false;
    public $help        = null;

    protected $has_gui = false;
    protected $gui_url = false;

    /**
     * Constructs a new instance.
     */
    public function __construct()
    {
        $this->setInfo();

        $this->id = join('', array_slice(explode('\\', get_class($this)), -1));
        if (!$this->name) {
            $this->name = $this->id;
        }

        if (DOTCLEAR_PROCESS == 'Admin') {
            $this->gui_url = dotclear()->adminurl()->get('admin.plugin.Antispam', ['f' => $this->id], '&');
        }
    }

    /**
    This method is called by the constructor and allows you to change some
    object properties without overloading object constructor.
     */
    protected function setInfo(): void
    {
        $this->description = __('No description');
    }

    /**
     * This method should return if a comment is a spam or not. If it returns true
     * or false, execution of next filters will be stoped. If should return null
     * to let next filters apply.
     *
     * Your filter should also fill $status variable with its own information if
     * comment is a spam.
     *
     * @param      string  $type     The comment type (comment / trackback)
     * @param      string  $author   The comment author
     * @param      string  $email    The comment author email
     * @param      string  $site     The comment author site
     * @param      string  $ip       The comment author IP
     * @param      string  $content  The comment content
     * @param      integer $post_id  The comment post_id
     * @param      integer $status   The comment status
     *
     * @return  bool    Status
     */
    public function isSpam(string $type, string $author, string $email, string $site, string $ip, string $content, int $post_id, ?int &$status): ?bool
    {
        return null;
    }

    /**
     * { function_description }
     *
     * @param      string  $status   The comment status
     * @param      string  $filter   The filter
     * @param      string  $type     The comment type
     * @param      string  $author   The comment author
     * @param      string  $email    The comment author email
     * @param      string  $site     The comment author site
     * @param      string  $ip       The comment author IP
     * @param      string  $content  The comment content
     * @param      Record  $rs       The comment record
     */
    public function trainFilter(string $status, string $filter, string $type, string $author, string $email, string $site, string $ip, string $content, Record $rs): void
    {
    }

    /**
     * This method returns filter status message. You can overload this method to
     * return a custom message. Message is shown in comment details and in
     * comments list.
     *
     * @param      string  $status      The status
     * @param      integer $comment_id  The comment identifier
     *
     * @return     string  The status message.
     */
    public function getStatusMessage(string $status, int $comment_id): string
    {
        return sprintf(__('Filtered by %1$s (%2$s)'), $this->guiLink(), $status);
    }

    /**
     * This method is called when you enter filter configuration. Your class should
     * have $has_gui property set to "true" to enable GUI.
     *
     * @param      string  $url    The GUI url
     */
    public function gui(string $url): string
    {
        return '';
    }

    public function hasGUI(): bool
    {
        if (!dotclear()->user()->check('admin', dotclear()->blog()->id)) {
            return false;
        }

        if (!$this->has_gui) {
            return false;
        }

        return true;
    }

    public function guiURL(): string|false
    {
        if (!$this->hasGui()) {
            return false;
        }

        return $this->gui_url;
    }

    /**
     * Returns a link to filter GUI if exists or only filter name if has_gui
     * property is false.
     *
     * @return     string
     */
    public function guiLink(): string
    {
        if (($url = $this->guiURL()) !== false) {
            $url  = Html::escapeHTML($url);
            $link = '<a href="%2$s">%1$s</a>';
        } else {
            $link = '%1$s';
        }

        return sprintf($link, $this->name, $url);
    }

    public function guiTab(): ?string
    {
        return null;
    }

    public function help()
    {
    }
}
