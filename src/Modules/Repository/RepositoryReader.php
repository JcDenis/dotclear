<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Modules\Repository;

// Dotclear\Modules\Repository\RepositoryReader
use Dotclear\App;
use Dotclear\Helper\Clock;
use Dotclear\Helper\File\Files;
use Dotclear\Helper\Network\NetHttp\NetHttp;

/**
 * Repository modules XML feed reader.
 *
 * Provides an object to parse XML feed of modules from repository.
 *
 * @ingroup  Module Store
 */
class RepositoryReader extends NetHttp
{
    /**
     * @var string $user_agent
     *             User agent used to query repository
     */
    protected $user_agent = 'DotClear.org RepoBrowser/0.1';

    /**
     * @var array<string,mixed> $validators
     *                          HTTP Cache validators
     */
    protected $validators;

    /**
     * @var mixed $cache_dir
     *            Cache temporary directory
     */
    protected $cache_dir;

    /**
     * @var string $cache_file_prefix
     *             Cache file prefix
     */
    protected $cache_file_prefix = 'dcrepo';

    /**
     * @var string $cache_ttl
     *             Cache TTL
     */
    protected $cache_ttl = '-1440 minutes';

    /**
     * @var bool $cache_touch_on_fail
     *           'Cache' TTL on server failed
     */
    protected $cache_touch_on_fail = true;

    /**
     * @var bool $force
     *           Force query server
     */
    protected $force = false;

    /**
     * Constructor.
     *
     * Bypass first argument of clearbricks netHttp constructor.
     */
    public function __construct()
    {
        parent::__construct('');
        $this->setUserAgent(sprintf('Dotclear/%s', App::core()->config()->get('core_version')));
        $this->setTimeout(App::core()->config()->get('query_timeout'));
    }

    /**
     * Parse modules feed.
     *
     * @param string $url XML feed URL
     *
     * @return false|RepositoryParser RepositoryParser instance
     */
    public function parse(string $url): RepositoryParser|false
    {
        $this->validators = [];

        if ($this->cache_dir) {
            return $this->withCache($url);
        }
        if (!$this->getModulesXML($url) || $this->getStatus() != '200') {
            return false;
        }

        return new RepositoryParser($this->getContent());
    }

    /**
     * Quick parse modules feed.
     *
     * @param string $url       XML feed URL
     * @param string $cache_dir Cache directoy or null for no cache
     * @param bool   $force     Force query repository
     *
     * @return false|RepositoryParser RepositoryParser instance
     */
    public static function quickParse(string $url, ?string $cache_dir = null, bool $force = false): RepositoryParser|false
    {
        $parser = new self();
        if ($cache_dir) {
            $parser->setCacheDir($cache_dir);
        }
        if ($force) {
            $parser->setForce($force);
        }

        return $parser->parse($url);
    }

    /**
     * Set cache directory.
     *
     * @param string $dir Cache directory
     *
     * @return bool True if cache dierctory is useable
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
     * Set cache TTL.
     *
     * @param string $str Cache TTL
     */
    public function setCacheTTL(string $str): void
    {
        $str = trim($str);

        if (!empty($str)) {
            $this->cache_ttl = substr($str, 0, 1) == '-' ? $str : '-' . $str;
        }
    }

    /**
     * Set force query repository.
     *
     * @param bool $force True to force query
     */
    public function setForce(bool $force): void
    {
        $this->force = $force;
    }

    /**
     * Get repository XML feed URL content.
     *
     * send content to ouput.
     *
     * @param string $url XML feed URL
     *
     * @return bool Success
     */
    protected function getModulesXML(string $url): bool
    {
        if (!self::readURL($url, $ssl, $host, $port, $path, $user, $pass)) {
            return false;
        }
        $this->setHost($host, $port);
        $this->useSSL($ssl);
        $this->setAuthorization($user, $pass);

        try {
            return $this->get($path);
        } catch (\Exception) {
            // ! @todo Log error when repository query fail
            return false;
        }
    }

    /**
     * Get repository modules list using cache.
     *
     * @param string $url XML feed URL
     *
     * @return false|RepositoryParser Feed content or False on fail
     */
    protected function withCache(string $url): RepositoryParser|false
    {
        $url_md5     = md5($url);
        $cached_file = sprintf(
            '%s/%s/%s/%s/%s.ser',
            $this->cache_dir,
            $this->cache_file_prefix,
            substr($url_md5, 0, 2),
            substr($url_md5, 2, 2),
            $url_md5
        );

        $may_use_cached = false;

        // Use cache file ?
        if (@file_exists($cached_file) && !$this->force) {
            $may_use_cached = true;
            $ts             = @filemtime($cached_file);
            if (Clock::ts(date: $this->cache_ttl) < $ts) {
                // Direct cache
                return unserialize(file_get_contents($cached_file));
            }
            $this->setValidator('IfModifiedSince', $ts);
        }

        // Query repository
        if (!$this->getModulesXML($url)) {
            if ($may_use_cached) {
                // Touch cache TTL even if query failed ?
                if ($this->cache_touch_on_fail) {
                    @Files::touch($cached_file);
                }
                // Connection failed - fetched from cache
                return unserialize(file_get_contents($cached_file));
            }

            return false;
        }

        // Parse response
        switch ($this->getStatus()) {
            // Not modified, use cache
            case '304':
                @Files::touch($cached_file);

                return unserialize(file_get_contents($cached_file));
            // Ok, parse feed
            case '200':
                $modules = new RepositoryParser($this->getContent());

                try {
                    Files::makeDir(dirname($cached_file), true);
                } catch (\Exception) {
                    return $modules;
                }

                if (($fp = @fopen($cached_file, 'wb'))) {
                    fwrite($fp, serialize($modules));
                    fclose($fp);
                    Files::inheritChmod($cached_file);
                }

                return $modules;
        }

        return false;
    }

    /**
     * Prepare query.
     *
     * @return array Query headers
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

    /**
     * Tweak query cache validator.
     *
     * @param string $key   Validator key
     * @param mixed  $value Validator value
     */
    private function setValidator(string $key, mixed $value): void
    {
        if ('IfModifiedSince' == $key) {
            $value = Clock::format(format: 'D, d M Y H:i:s', date: $value, to: 'UTC') . ' GMT';
        }

        $this->validators[$key] = $value;
    }
}
