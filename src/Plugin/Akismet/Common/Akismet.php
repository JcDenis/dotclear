<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Akismet\Common;

// Dotclear\Plugin\Akismet\Common\Akismet
use Dotclear\Helper\Network\NetHttp\NetHttp;
use Dotclear\Helper\Network\Http;
use Exception;

/**
 * Akismet management.
 *
 * @ingroup  Plugin Akismet
 */
class Akismet extends NetHttp
{
    protected $base_host  = 'rest.akismet.com';
    protected $ak_host    = '';
    protected $ak_version = '1.1';
    protected $ak_path    = '/%s/%s';

    public function __construct(protected string $blog_url, protected string $ak_key)
    {
        $this->ak_path = sprintf($this->ak_path, $this->ak_version, '%s');
        $this->ak_host = $this->ak_key . '.' . $this->base_host;

        parent::__construct($this->ak_host, 80, dotclear()->config()->get('query_timeout'));
    }

    public function verify(): bool
    {
        $this->host = $this->base_host;
        $path       = sprintf($this->ak_path, 'verify-key');

        $data = [
            'key'  => $this->ak_key,
            'blog' => $this->blog_url,
        ];

        if ($this->post($path, $data, 'UTF-8')) {
            return 'valid' == $this->getContent();
        }

        return false;
    }

    public function comment_check(string $permalink, string $type, string $author, string $email, string $url, string $content): mixed
    {
        $info_ignore = ['HTTP_COOKIE'];
        $info        = [];

        foreach ($_SERVER as $k => $v) {
            if (str_starts_with($k, 'HTTP_') && !in_array($k, $info_ignore)) {
                $info[$k] = $v;
            }
        }

        return $this->callFunc('comment-check', $permalink, $type, $author, $email, $url, $content, $info);
    }

    public function submit_spam(string $permalink, string $type, string $author, string $email, string $url, string $content): bool
    {
        $this->callFunc('submit-spam', $permalink, $type, $author, $email, $url, $content);

        return true;
    }

    public function submit_ham(string $permalink, string $type, string $author, string $email, string $url, string $content): bool
    {
        $this->callFunc('submit-ham', $permalink, $type, $author, $email, $url, $content);

        return true;
    }

    protected function callFunc(string $function, string $permalink, string $type, string $author, string $email, string $url, string $content, array $info = []): bool
    {
        $ua      = $info['HTTP_USER_AGENT'] ?? '';
        $referer = $info['HTTP_REFERER']    ?? '';

        // Prepare comment data
        $data = [
            'blog'                 => $this->blog_url,
            'user_ip'              => Http::realIP(),
            'user_agent'           => $ua,
            'referrer'             => $referer,
            'permalink'            => $permalink,
            'comment_type'         => $type,
            'comment_author'       => $author,
            'comment_author_email' => $email,
            'comment_author_url'   => $url,
            'comment_content'      => $content,
        ];

        $data = array_merge($data, $info);

        $this->host = $this->ak_host;
        $path       = sprintf($this->ak_path, $function);

        if (!$this->post($path, $data, 'UTF-8')) {
            throw new Exception('HTTP error: ' . $this->getError());    // @phpstan-ignore-line
        }

        return 'true' == $this->getContent();
    }
}
