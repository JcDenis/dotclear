<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Service;

// Dotclear\Process\Admin\Service\Updater
use Dotclear\App;
use Dotclear\Exception\AdminException;
use Dotclear\Helper\File\Files;
use Dotclear\Helper\File\Zip\Unzip;
use Dotclear\Helper\File\Zip\Zip;
use Dotclear\Helper\Network\Http;
use Dotclear\Helper\Network\NetHttp\NetHttp;
use SimpleXMLElement;
use Exception;

/**
 * Update methods.
 *
 * @ingroup  Admin
 */
class Updater
{
    public const ERR_FILES_CHANGED    = 101;
    public const ERR_FILES_UNREADABLE = 102;
    public const ERR_FILES_UNWRITALBE = 103;

    protected $cache_file;

    protected $version_info = [
        'version'  => null,
        'href'     => null,
        'checksum' => null,
        'info'     => null,
        'php'      => '7.4',
        'notify'   => true,
    ];

    protected $cache_ttl    = '-6 hours';
    protected $forced_files = [];

    /**
     * @var array<int,string> $errors
     *                        List of errors during update
     */
    public static $errors = [];

    /**
     * Constructor.
     *
     * @param string $url       Versions file URL
     * @param string $subject   Subject to check
     * @param string $version   Version type
     * @param string $cache_dir Directory cache path
     */
    public function __construct(protected string $url, protected string $subject, protected string $version, string $cache_dir)
    {
        $this->cache_file = $cache_dir . '/' . $subject . '-' . $version;
    }

    /**
     * Checks for Dotclear updates.
     * Returns latest version if available or false.
     *
     * @param string $version Current version to compare
     * @param bool   $nocache Force checking
     *
     * @return string Latest version if available
     */
    public function check(string $version, bool $nocache = false): string
    {
        $this->getVersionInfo($nocache);
        $v = $this->getVersion();

        return ($v && version_compare($version, $v, '<')) ? $v : '';
    }

    public function getVersionInfo(bool $nocache = false): void
    {
        // Check cached file
        if (is_readable($this->cache_file) && filemtime($this->cache_file) > strtotime($this->cache_ttl) && !$nocache) {
            $c = @file_get_contents($this->cache_file);
            $c = @unserialize($c);
            if (is_array($c)) {
                $this->version_info = $c;

                return;
            }
        }

        $cache_dir = dirname($this->cache_file);
        $can_write = (!is_dir($cache_dir)   && is_writable(dirname($cache_dir)))
        || (!file_exists($this->cache_file) && is_writable($cache_dir))
        || is_writable($this->cache_file);

        // If we can't write file, don't bug host with queries
        if (!$can_write) {
            return;
        }

        if (!is_dir($cache_dir)) {
            try {
                Files::makeDir($cache_dir);
            } catch (\Exception) {
                return;
            }
        }

        // Try to get latest version number
        try {
            $path   = '';
            $status = 0;

            $http_get = function ($http_url) use (&$status, $path) {
                $client = NetHttp::initClient($http_url, $path);
                if (false !== $client) {
                    $client->setTimeout(App::core()->config()->get('query_timeout'));
                    $client->setUserAgent($_SERVER['HTTP_USER_AGENT']);
                    $client->get($path);
                    $status = (int) $client->getStatus();
                }

                return $client;
            };

            $client = $http_get($this->url);
            if (400 <= $status) {
                // If original URL uses HTTPS, try with HTTP
                $url_parts = parse_url($client->getRequestURL());
                if (isset($url_parts['scheme']) && 'https' == $url_parts['scheme']) {
                    // Replace https by http in url
                    $this->url = preg_replace('/^https(?=:\/\/)/i', 'http', $this->url);
                    $client    = $http_get($this->url);
                }
            }
            if (!$status || 400 <= $status) {
                throw new AdminException();
            }
            $this->readVersion($client->getContent());
        } catch (\Exception) {
            return;
        }

        // Create cache
        file_put_contents($this->cache_file, serialize($this->version_info));
    }

    public function getVersion(): string
    {
        return $this->version_info['version'];
    }

    public function getFileURL(): string
    {
        return $this->version_info['href'];
    }

    public function getInfoURL(): string
    {
        return $this->version_info['info'];
    }

    public function getChecksum(): string
    {
        return $this->version_info['checksum'];
    }

    public function getPHPVersion(): string
    {
        return $this->version_info['php'];
    }

    public function getNotify(): bool
    {
        return $this->version_info['notify'];
    }

    public function getForcedFiles(): array
    {
        return $this->forced_files;
    }

    public function setForcedFiles(...$args): void
    {
        $this->forced_files = $args;
    }

    /**
     * Sets notification flag.
     */
    public function setNotify(mixed $n): void
    {
        if (!is_writable($this->cache_file)) {
            return;
        }

        $this->version_info['notify'] = (bool) $n;
        file_put_contents($this->cache_file, serialize($this->version_info));
    }

    public function checkIntegrity(string $digests_file, string $root): bool
    {
        if (!$digests_file) {
            throw new AdminException(__('Digests file not found.'));
        }

        $changes = $this->md5sum($root, $digests_file);

        if (!empty($changes)) {
            self::$errors = $changes;

            throw new AdminException('Some files have changed.', self::ERR_FILES_CHANGED);
        }

        return true;
    }

    /**
     * Downloads new version.
     *
     * @param string $dest The destination path
     */
    public function download(string $dest): void
    {
        $url = $this->getFileURL();

        if (!$url) {
            throw new AdminException(__('No file to download'));
        }

        if (!is_writable(dirname($dest))) {
            throw new AdminException(__('Root directory is not writable.'));
        }

        try {
            $path   = '';
            $status = 0;

            $http_get = function ($http_url) use (&$status, $dest, $path) {
                $client = NetHttp::initClient($http_url, $path);
                if (false !== $client) {
                    $client->setTimeout(App::core()->config()->get('query_timeout'));
                    $client->setUserAgent($_SERVER['HTTP_USER_AGENT']);
                    $client->useGzip(false);
                    $client->setPersistReferers(false);
                    $client->setOutput($dest);
                    $client->get($path);
                    $status = (int) $client->getStatus();
                }

                return $client;
            };

            $client = $http_get($url);
            if (400 <= $status) {
                // If original URL uses HTTPS, try with HTTP
                $url_parts = parse_url($client->getRequestURL());
                if (isset($url_parts['scheme']) && 'https' == $url_parts['scheme']) {
                    // Replace https by http in url
                    $url    = preg_replace('/^https(?=:\/\/)/i', 'http', $url);
                    $client = $http_get($url);
                }
            }
            if (200 != $status) {
                @unlink($dest);

                throw new AdminException();
            }
        } catch (\Exception) {
            throw new AdminException(__('An error occurred while downloading archive.'));
        }
    }

    /**
     * Checks if archive was successfully downloaded.
     */
    public function checkDownload(string $zip): bool
    {
        $cs = $this->getChecksum();

        return $cs && is_readable($zip) && md5_file($zip) == $cs;
    }

    /**
     * Backups changed files before an update.
     */
    public function backup(string $zip_file, string $zip_digests, string $root, string $root_digests, string $dest): bool
    {
        if (!is_readable($zip_file)) {
            throw new AdminException(__('Archive not found.'));
        }

        if (!is_readable($root_digests)) {
            @unlink($zip_file);

            throw new AdminException(__('Unable to read current digests file.'));
        }

        // Stop everything if a backup already exists and can not be overrided
        if (!is_writable(dirname($dest)) && !file_exists($dest)) {
            throw new AdminException(__('Root directory is not writable.'));
        }

        if (file_exists($dest) && !is_writable($dest)) {
            return false;
        }

        $b_fp = @fopen($dest, 'wb');
        if (false === $b_fp) {
            return false;
        }

        $zip   = new Unzip($zip_file);
        $b_zip = new Zip($b_fp);

        if (!$zip->hasFile($zip_digests)) {
            @unlink($zip_file);

            throw new AdminException(__('Downloaded file does not seem to be a valid archive.'));
        }

        $opts        = FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES;
        $cur_digests = file($root_digests, $opts);
        $new_digests = explode("\n", $zip->unzip($zip_digests));
        $new_files   = $this->getNewFiles($cur_digests, $new_digests);
        $zip->close();
        unset($opts, $cur_digests, $new_digests, $zip);

        $not_readable = [];

        if (!empty($this->forced_files)) {
            $new_files = array_merge($new_files, $this->forced_files);
        }

        foreach ($new_files as $file) {
            if (!$file || !file_exists($root . '/' . $file)) {
                continue;
            }

            try {
                $b_zip->addFile($root . '/' . $file, $file);
            } catch (\Exception) {
                $not_readable[] = $file;
            }
        }

        // If only one file is not readable, stop everything now
        if (!empty($not_readable)) {
            self::$errors = $not_readable;

            throw new AdminException('Some files are not readable.', self::ERR_FILES_UNREADABLE);
        }

        $b_zip->write();
        fclose($b_fp);
        $b_zip->close();

        return true;
    }

    /**
     * Upgrade process.
     */
    public function performUpgrade(string $zip_file, string $zip_digests, string $zip_root, string $root, string $root_digests): void
    {
        if (!is_readable($zip_file)) {
            throw new AdminException(__('Archive not found.'));
        }

        if (!is_readable($root_digests)) {
            @unlink($zip_file);

            throw new AdminException(__('Unable to read current digests file.'));
        }

        $zip = new Unzip($zip_file);

        if (!$zip->hasFile($zip_digests)) {
            @unlink($zip_file);

            throw new AdminException(__('Downloaded file does not seem to be a valid archive.'));
        }

        $opts        = FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES;
        $cur_digests = file($root_digests, $opts);
        $new_digests = explode("\n", $zip->unzip($zip_digests));
        $new_files   = self::getNewFiles($cur_digests, $new_digests);

        if (!empty($this->forced_files)) {
            $new_files = array_merge($new_files, $this->forced_files);
        }

        $zip_files    = [];
        $not_writable = [];

        foreach ($new_files as $file) {
            if (!$file) {
                continue;
            }

            if (!$zip->hasFile($zip_root . '/' . $file)) {
                @unlink($zip_file);

                throw new AdminException(__('Incomplete archive.'));
            }

            $dest = $dest_dir = $root . '/' . $file;
            while (!is_dir($dest_dir = dirname($dest_dir)));

            if ((file_exists($dest) && !is_writable($dest)) || (!file_exists($dest) && !is_writable($dest_dir))) {
                $not_writable[] = $file;

                continue;
            }

            $zip_files[] = $file;
        }

        // If only one file is not writable, stop everything now
        if (!empty($not_writable)) {
            self::$errors = $not_writable;

            throw new AdminException('Some files are not writable', self::ERR_FILES_UNWRITALBE);
        }

        // Everything's fine, we can write files, then do it now
        $can_touch = function_exists('touch');
        foreach ($zip_files as $file) {
            $zip->unzip($zip_root . '/' . $file, $root . '/' . $file);
            if ($can_touch) {
                @touch($root . '/' . $file);
            }
        }
        @unlink($zip_file);
    }

    protected function getNewFiles(array $cur_digests, array $new_digests): array
    {
        $cur_md5 = $cur_path = $cur_digests;
        $new_md5 = $new_path = $new_digests;

        array_walk($cur_md5, [$this, 'parseLine'], 1);
        array_walk($cur_path, [$this, 'parseLine'], 2);
        array_walk($new_md5, [$this, 'parseLine'], 1);
        array_walk($new_path, [$this, 'parseLine'], 2);

        $cur = array_combine($cur_md5, $cur_path);
        $new = array_combine($new_md5, $new_path);

        return array_values(array_diff_key($new, $cur));
    }

    protected function readVersion(string $str): void
    {
        try {
            $xml = new SimpleXMLElement($str, LIBXML_NOERROR);
            $r   = $xml->xpath("/versions/subject[@name='" . $this->subject . "']/release[@name='" . $this->version . "']");

            if (!empty($r) && is_array($r)) {
                $r                              = $r[0];
                $this->version_info['version']  = isset($r['version']) ? (string) $r['version'] : null;
                $this->version_info['href']     = isset($r['href']) ? (string) $r['href'] : null;
                $this->version_info['checksum'] = isset($r['checksum']) ? (string) $r['checksum'] : null;
                $this->version_info['info']     = isset($r['info']) ? (string) $r['info'] : null;
                $this->version_info['php']      = isset($r['php']) ? (string) $r['php'] : null;
            }
        } catch (Exception $e) {
            throw $e;
        }
    }

    protected function md5sum(string $root, string $digests_file): array
    {
        if (!is_readable($digests_file)) {
            throw new AdminException(__('Unable to read digests file.'));
        }

        $opts     = FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES;
        $contents = file($digests_file, $opts);

        $changes = [];

        foreach ($contents as $digest) {
            if (!preg_match('#^([\da-f]{32})\s+(.+?)$#', $digest, $m)) {
                continue;
            }

            $md5      = $m[1];
            $filename = $root . '/' . $m[2];

            // Invalid checksum
            if (!is_readable($filename) || !$this->md5_check($filename, $md5)) {
                $changes[] = substr($m[2], 2);
            }
        }

        // No checksum found in digests file
        if (empty($md5)) {
            throw new AdminException(__('Invalid digests file.'));
        }

        return $changes;
    }

    protected function parseLine(&$v, $k, $n)
    {
        if (!preg_match('#^([\da-f]{32})\s+(.+?)$#', $v, $m)) {
            return;
        }

        $v = 1 == $n ? md5($m[2] . $m[1]) : substr($m[2], 2);
    }

    protected function md5_check(string $filename, string $md5): bool
    {
        if (md5_file($filename) == $md5) {
            return true;
        }
        $filecontent = file_get_contents($filename);
        $filecontent = str_replace("\r\n", "\n", $filecontent);
        $filecontent = str_replace("\r", "\n", $filecontent);
        if (md5($filecontent) == $md5) {
            return true;
        }

        return false;
    }
}
