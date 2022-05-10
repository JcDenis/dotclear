<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Helper\Network\Feed;

// Dotclear\Helper\Network\Feed\Reader
use Dotclear\Helper\Clock;
use Dotclear\Helper\File\Files;
use Dotclear\Helper\Network\NetHttp\NetHttp;

/**
 * Feed Reader.
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * Features:
 *
 * - Reads RSS 1.0 (rdf), RSS 2.0 and Atom feeds.
 * - HTTP cache negociation support
 * - Cache TTL.
 *
 * @ingroup  Helper Network Feed
 */
class Reader extends NetHttp
{
    /**
     * @var string $user_agent
     *             User agent
     */
    protected $user_agent = 'Clearbricks Feed Reader/0.2';

    /**
     * @var int $timeout
     *          Query timeout
     */
    protected $timeout = 5;

    /**
     * @var null|array $validators
     *                 HTTP Cache validators
     */
    protected $validators;

    /**
     * @var null|string $cache_dir
     *                  Cache directory path
     */
    protected $cache_dir;

    /**
     * @var string $cache_file_prefix
     *             Cache file prefix
     */
    protected $cache_file_prefix = 'cbfeed';

    /**
     * @var string $cache_ttl
     *             Cache time to live
     */
    protected $cache_ttl = '-30 minutes';

    /**
     * Constructor.
     *
     * Does nothing. See {@link parse()} method for URL handling.
     */
    public function __construct()
    {
        parent::__construct('');
    }

    /**
     * Parse Feed.
     *
     * Returns a new Parser instance for given URL or false if source URL is
     * not a valid feed.
     *
     * @param string $url Feed URL
     */
    public function parse(string $url): Parser|false
    {
        $this->validators = [];
        if ($this->cache_dir) {
            return $this->withCache($url);
        }
        if (!$this->getFeed($url)) {
            return false;
        }

        if ($this->getStatus() != '200') {
            return false;
        }

        return new Parser($this->getContent());
    }

    /**
     * Quick Parse.
     *
     * This static method returns a new {@link Parser} instance for given URL. If a
     * <var>$cache_dir</var> is specified, cache will be activated.
     *
     * @param string      $url       Feed URL
     * @param null|string $cache_dir Cache directory
     */
    public static function quickParse(string $url, ?string $cache_dir = null): Parser|false
    {
        $parser = new self();
        if ($cache_dir) {
            $parser->setCacheDir($cache_dir);
        }

        return $parser->parse($url);
    }

    /**
     * Set Cache Directory.
     *
     * Returns true and sets {@link $cache_dir} property if <var>$dir</var> is
     * a writable directory. Otherwise, returns false.
     *
     * @param string $dir Cache directory
     */
    public function setCacheDir(string $dir): bool
    {
        $this->cache_dir = null;

        if (!empty($dir) && is_dir($dir) && is_writeable($dir)) {
            $this->cache_dir = $dir;

            return true;
        }

        return false;
    }

    /**
     * Set Cache TTL.
     *
     * Sets cache TTL. <var>$str</var> is a interval readable by strtotime
     * (-3 minutes, -2 hours, etc.)
     *
     * @param string $str TTL
     */
    public function setCacheTTL(string $str)
    {
        $str = trim((string) $str);
        if (!empty($str)) {
            if (substr($str, 0, 1) != '-') {
                $str = '-' . $str;
            }
            $this->cache_ttl = $str;
        }
    }

    /**
     * Feed Content.
     *
     * Returns feed content for given URL.
     *
     * @param string $url Feed URL
     */
    protected function getFeed(string $url): string|bool
    {
        if (!self::readURL($url, $ssl, $host, $port, $path, $user, $pass)) {
            return false;
        }
        $this->setHost($host, $port);
        $this->useSSL($ssl);
        $this->setAuthorization($user, $pass);

        return $this->get($path);
    }

    /**
     * Cache content.
     *
     * Returns Parser object from cache if present or write it to cache and
     * returns result.
     *
     * @param string $url Feed URL
     */
    protected function withCache(string $url): Parser|false
    {
        $url_md5     = md5($url);
        $cached_file = sprintf(
            '%s/%s/%s/%s/%s.php',
            $this->cache_dir,
            $this->cache_file_prefix,
            substr($url_md5, 0, 2),
            substr($url_md5, 2, 2),
            $url_md5
        );

        $may_use_cached = false;

        if (@file_exists($cached_file)) {
            $may_use_cached = true;
            $ts             = @filemtime($cached_file);
            if (Clock::ts(date: $this->cache_ttl) < $ts) {
                // Direct cache
                return unserialize(file_get_contents($cached_file));
            }
            $this->setValidator('IfModifiedSince', $ts);
        }

        if (!$this->getFeed($url)) {
            if ($may_use_cached) {
                // connection failed - fetched from cache
                return unserialize(file_get_contents($cached_file));
            }

            return false;
        }

        switch ($this->getStatus()) {
            case '304':
                @Files::touch($cached_file);

                return unserialize(file_get_contents($cached_file));

            case '200':
                $feed = new Parser($this->getContent());

                try {
                    Files::makeDir(dirname($cached_file), true);
                    if (Files::putContent($cached_file, serialize($feed))) {
                        Files::inheritChmod($cached_file);
                    }
                } catch (\Exception) {
                }

                return $feed;
        }

        return false;
    }

    /**
     * Build request.
     *
     * Adds HTTP cache headers to common headers.
     *
     * {@inheritdoc}
     */
    protected function buildRequest(): array
    {
        $headers = parent::buildRequest();

        // Cache validators
        if (!empty($this->validators)) {
            if (isset($this->validators['IfModifiedSince'])) {
                $headers[] = 'If-Modified-Since: ' . $this->validators['IfModifiedSince'];
            }
            if (isset($this->validators['IfNoneMatch'])) {
                if (is_array($this->validators['IfNoneMatch'])) {
                    $etags = implode(',', $this->validators['IfNoneMatch']);
                } else {
                    $etags = $this->validators['IfNoneMatch'];
                }
                $headers[] = '';
            }
        }

        return $headers;
    }

    private function setValidator(string $key, int $value): void
    {
        if ('IfModifiedSince' == $key) {
            $value = Clock::format(format: 'D, d M Y H:i:s', date: $value) . ' GMT';
        }

        $this->validators[$key] = $value;
    }
}
