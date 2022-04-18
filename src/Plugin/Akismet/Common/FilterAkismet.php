<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Akismet\Common;

use Dotclear\Database\Record;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Http;
use Dotclear\Plugin\Antispam\Common\Spamfilter;
use Exception;

/**
 * Akismet antispam filter.
 *
 * \Dotclear\Plugin\Akismet\Common\FilterAkismet
 *
 * @ingroup  Plugin Akismet
 */
class FilterAkismet extends Spamfilter
{
    public $name    = 'Akismet';
    public $has_gui = true;
    public $active  = false;
    public $help    = 'akismet-filter';

    public function __construct()
    {
        parent::__construct();

        if (defined('DC_AKISMET_SUPER') && DC_AKISMET_SUPER && !dotclear()->user()->isSuperAdmin()) {
            $this->has_gui = false;
        }
    }

    protected function setInfo(): void
    {
        $this->description = __('Akismet spam filter');
    }

    public function getStatusMessage(string $status, int $comment_id): string
    {
        return sprintf(__('Filtered by %s.'), $this->guiLink());
    }

    private function akInit(): Akismet|false
    {
        if (!dotclear()->blog()->settings()->get('akismet')->get('ak_key')) {
            return false;
        }

        return new Akismet(dotclear()->blog()->url, dotclear()->blog()->settings()->get('akismet')->get('ak_key'));
    }

    public function isSpam(string $type, string $author, string $email, string $site, string $ip, string $content, int $post_id, ?int &$status): ?bool
    {
        if (false === ($ak = $this->akInit())) {
            return null;
        }

        try {
            if ($ak->verify()) {
                $post = dotclear()->blog()->posts()->getPosts(['post_id' => $post_id]);

                $c = $ak->comment_check(
                    $post->getURL(),
                    $type,
                    $author,
                    $email,
                    $site,
                    $content
                );

                if ($c) {
                    $status = 'Filtered by Akismet';

                    return true;
                }
            }
        } catch (\Exception) {
        } // If http or akismet is dead, we don't need to know it

        return null;
    }

    public function trainFilter(string $status, string $filter, string $type, string $author, string $email, string $site, string $ip, string $content, Record $rs): void
    {
        // We handle only false positive from akismet
        if ('spam' == $status && 'dcFilterAkismet' != $filter) {
            return;
        }

        $f = 'spam' == $status ? 'submit_spam' : 'submit_ham';

        if (false === ($ak = $this->akInit())) {
            return;
        }

        try {
            if ($ak->verify()) {
                $ak->{$f}($rs->call('getPostURL'), $type, $author, $email, $site, $content);
            }
        } catch (\Exception) {
        } // If http or akismet is dead, we don't need to know it
    }

    public function gui(string $url): string
    {
        $ak_key      = dotclear()->blog()->settings()->get('akismet')->get('ak_key');
        $ak_verified = null;

        if (isset($_POST['ak_key'])) {
            try {
                $ak_key = $_POST['ak_key'];

                dotclear()->blog()->settings()->get('akismet')->put('ak_key', $ak_key, 'string');

                dotclear()->notice()->addSuccessNotice(__('Filter configuration have been successfully saved.'));
                Http::redirect($url);
            } catch (Exception $e) {
                dotclear()->error()->add($e->getMessage());
            }
        }

        if (dotclear()->blog()->settings()->get('akismet')->get('ak_key')) {
            try {
                $ak          = new Akismet(dotclear()->blog()->url, dotclear()->blog()->settings()->get('akismet')->get('ak_key'));
                $ak_verified = $ak->verify();
            } catch (Exception $e) {
                dotclear()->error()->add($e->getMessage());
            }
        }

        $res = '<form action="' . Html::escapeURL($url) . '" method="post" class="fieldset">' .
        '<p><label for="ak_key" class="classic">' . __('Akismet API key:') . '</label> ' .
        Form::field('ak_key', 12, 128, $ak_key);

        if (null !== $ak_verified) {
            $res .= $ak_verified ?
                ' <img src="?df=images/check-on.png" alt="" /> ' . __('API key verified') :
                ' <img src="?df=images/check-off.png" alt="" /> ' . __('API key not verified');
        }

        $res .= '</p>' .
        '<p><a href="https://akismet.com/">' . __('Get your own API key') . '</a></p>' .
        '<p><input type="submit" value="' . __('Save') . '" />' .
        dotclear()->nonce()->form() . '</p>' .
            '</form>';

        return $res;
    }
}
