<?php
/**
 * @class Dotclear\Plugin\Akismet\Common\FilterAkismet
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginAkismet
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Akismet\Common;

use Dotclear\Database\Record;
Use Dotclear\Html\Form;
use Dotclear\Html\Html;
use Dotclear\Network\Http;
use Dotclear\Plugin\Akismet\Common\Akismet;
use Dotclear\Plugin\Antispam\Common\Spamfilter;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

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

    private function akInit()
    {
        if (!dotclear()->blog()->settings()->akismet->ak_key) {
            return false;
        }

        return new Akismet($blog->url, dotclear()->blog()->settings()->akismet->ak_key);
    }

    public function isSpam(string $type, string $author, string $email, string $site, string $ip, string $content, int $post_id, ?int &$status): ?bool
    {
        if (($ak = $this->akInit()) === false) {
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
        } # If http or akismet is dead, we don't need to know it

        return null;
    }

    public function trainFilter(string $status, string $filter, string $type, string $author, string $email, string $site, string $ip, string $content, Record $rs): void
    {
        # We handle only false positive from akismet
        if ($status == 'spam' && $filter != 'dcFilterAkismet') {
            return;
        }

        $f = $status == 'spam' ? 'submit_spam' : 'submit_ham';

        if (($ak = $this->akInit()) === false) {
            return;
        }

        try {
            if ($ak->verify()) {
                $ak->{$f}($rs->getPostURL(), $type, $author, $email, $site, $content);
            }
        } catch (\Exception) {
        } # If http or akismet is dead, we don't need to know it
    }

    public function gui(string $url): string
    {
        dotclear()->blog()->settings()->addNamespace('akismet');
        $ak_key      = dotclear()->blog()->settings()->akismet->ak_key;
        $ak_verified = null;

        if (isset($_POST['ak_key'])) {
            try {
                $ak_key = $_POST['ak_key'];

                dotclear()->blog()->settings()->akismet->put('ak_key', $ak_key, 'string');

                dotclear()->notice()->addSuccessNotice(__('Filter configuration have been successfully saved.'));
                Http::redirect($url);
            } catch (\Exception $e) {
                dotclear()->error()->add($e->getMessage());
            }
        }

        if (dotclear()->blog()->settings()->akismet->ak_key) {
            try {
                $ak          = new Akismet(dotclear()->blog()->url, dotclear()->blog()->settings()->akismet->ak_key);
                $ak_verified = $ak->verify();
            } catch (\Exception $e) {
                dotclear()->error()->add($e->getMessage());
            }
        }

        $res = '<form action="' . Html::escapeURL($url) . '" method="post" class="fieldset">' .
        '<p><label for="ak_key" class="classic">' . __('Akismet API key:') . '</label> ' .
        Form::field('ak_key', 12, 128, $ak_key);

        if ($ak_verified !== null) {
            if ($ak_verified) {
                $res .= ' <img src="?df=images/check-on.png" alt="" /> ' . __('API key verified');
            } else {
                $res .= ' <img src="?df=images/check-off.png" alt="" /> ' . __('API key not verified');
            }
        }

        $res .= '</p>';

        $res .= '<p><a href="https://akismet.com/">' . __('Get your own API key') . '</a></p>' .
        '<p><input type="submit" value="' . __('Save') . '" />' .
        dotclear()->nonce()->form() . '</p>' .
            '</form>';

        return $res;
    }
}
