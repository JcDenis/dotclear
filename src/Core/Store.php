<?php
/**
 * @class Dotclear\Core\Store
 * @brief Repository modules manager
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core;

use Dotclear\Exception;
use Dotclear\Exception\CoreException;

use Dotclear\Core\Core;
use Dotclear\Core\Modules;
use Dotclear\Core\StoreReader;

use Dotclear\Network\Http;
use Dotclear\Network\NetHttp\NetHttp;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class Store
{
    /** @var    Core        Core instance */
    public $core;

    /** @var    Modules     Modules instance */
    public $modules;

    /** @var    array    Modules fields to search on and their weighting */
    public static $weighting = [
        'id'     => 10,
        'name'   => 8,
        'tags'   => 6,
        'desc'   => 4,
        'author' => 2,
    ];

    /** @var    string    User agent used to query repository */
    protected $user_agent = 'DotClear.org RepoBrowser/0.1';

    /** @var    string    XML feed URL */
    protected $xml_url;

    /** @var    array    Array of new/update modules from repository */
    protected $data;

    /**
     * Constructor.
     *
     * @param   Modules     $modules    Modules instance
     * @param   string      $xml_url    XML feed URL
     * @param   bool        $force      Force query repository
     */
    public function __construct(Modules $modules, string $xml_url, bool $force = false)
    {
        $this->core       = $modules->core;
        $this->modules    = $modules;
        $this->xml_url    = $xml_url;
        $this->user_agent = sprintf('Dotclear/%s)', DOTCLEAR_VERSION);

        $this->check($force);
    }

    /**
     * Check repository.
     *
     * @param   bool        $force      Force query repository
     *
     * @return  bool                    True if get feed or cache
     */
    public function check(bool $force = false): bool
    {
        if (!$this->xml_url) {
            return false;
        }

        try {
            /* @phpstan-ignore-next-line */
            $parser = DOTCLEAR_STORE_NOT_UPDATE ? false : StoreReader::quickParse($this->xml_url, DOTCLEAR_CACHE_DIR, $force);
        } catch (Exception $e) {
            return false;
        }

        $raw_datas = !$parser ? [] : $parser->getModules(); // @phpstan-ignore-line

        uasort($raw_datas, ['self', 'sort']);

        $skipped = array_keys($this->modules->getDisabledModules());
        foreach ($skipped as $p_id) {
            if (isset($raw_datas[$p_id])) {
                unset($raw_datas[$p_id]);
            }
        }

        $updates = [];
        $current = $this->modules->getModules();
        foreach ($current as $p_id => $p_infos) {
            # non privileged user has no info
            if (!is_array($p_infos)) {
                continue;
            }
            # main repository
            if (isset($raw_datas[$p_id])) {
                if (self::compare($raw_datas[$p_id]['version'], $p_infos['version'], '>')) {
                    $updates[$p_id]                    = $raw_datas[$p_id];
                    $updates[$p_id]['root']            = $p_infos['root'];
                    $updates[$p_id]['root_writable']   = $p_infos['root_writable'];
                    $updates[$p_id]['current_version'] = $p_infos['version'];
                }
                unset($raw_datas[$p_id]);
            }
            # per module third-party repository
            if (!empty($p_infos['repository']) && DOTCLEAR_ALLOW_REPOSITORIES) {  // @phpstan-ignore-line
                try {
                    $dcs_url    = substr($p_infos['repository'], -12, 12) == '/dcstore.xml' ? $p_infos['repository'] : http::concatURL($p_infos['repository'], 'dcstore.xml');
                    $dcs_parser = StoreReader::quickParse($dcs_url, DOTCLEAR_CACHE_DIR, $force);
                    if ($dcs_parser !== false) {
                        $dcs_raw_datas = $dcs_parser->getModules();
                        if (isset($dcs_raw_datas[$p_id]) && self::compare($dcs_raw_datas[$p_id]['version'], $p_infos['version'], '>')) {
                            if (!isset($updates[$p_id]) || self::compare($dcs_raw_datas[$p_id]['version'], $updates[$p_id]['version'], '>')) {
                                $dcs_raw_datas[$p_id]['repository'] = true;
                                $updates[$p_id]                     = $dcs_raw_datas[$p_id];
                                $updates[$p_id]['root']             = $p_infos['root'];
                                $updates[$p_id]['root_writable']    = $p_infos['root_writable'];
                                $updates[$p_id]['current_version']  = $p_infos['version'];
                            }
                        }
                    }
                } catch (Exception $e) {
                }
            }
        }

        $this->data = [
            'new'    => $raw_datas,
            'update' => $updates,
        ];

        return true;
    }

    /**
     * Get a list of modules.
     *
     * @param   bool    $update     True to get update modules, false for new ones
     *
     * @return  array               List of update/new modules
     */
    public function get(bool $update = false): array
    {
        /* @phpstan-ignore-next-line */
        return is_array($this->data) ? $this->data[$update ? 'update' : 'new'] : [];
    }

    /**
     * Search a module.
     *
     * Search string is cleaned, split and compare to split:
     * - module id and clean id,
     * - module name, clean name,
     * - module desccription.
     *
     * Every time a part of query is find on module,
     * result accuracy grow. Result is sorted by accuracy.
     *
     * @param   string  $pattern    String to search
     *
     * @return  array               Match modules
     */
    public function search(string $pattern): array
    {
        $result = [];
        $sorter = [];

        # Split query into small clean words
        if (!($patterns = self::patternize($pattern))) {
            return $result;
        }

        # For each modules
        foreach ($this->data['new'] as $id => $module) {
            $module['id'] = $id;

            # Loop through required module fields
            foreach (self::$weighting as $field => $weight) {

                # Skip fields which not exsist on module
                if (empty($module[$field])) {
                    continue;
                }

                # Split field value into small clean word
                if (!($subjects = self::patternize($module[$field]))) {
                    continue;
                }

                # Check contents
                if (!($nb = preg_match_all('/(' . implode('|', $patterns) . ')/', implode(' ', $subjects), $_))) {
                    continue;
                }

                # Add module to result
                if (!isset($sorter[$id])) {
                    $sorter[$id] = 0;
                    $result[$id] = $module;
                }

                # Increment score by matches count * field weight
                $sorter[$id] += $nb * $weight;
                $result[$id]['score'] = $sorter[$id];
            }
        }
        # Sort response by matches count
        if (!empty($result)) {
            array_multisort($sorter, SORT_DESC, $result);
        }

        return $result;
    }

    /**
     * Quick download and install module.
     *
     * @param   string  $url    Module package URL
     * @param   string  $dest   Path to install module
     *
     * @return  int             1 = installed, 2 = update
     */
    public function process(string $url, string $dest): int
    {
        $this->download($url, $dest);

        return $this->install($dest);
    }

    /**
     * Download a module.
     *
     * @param   string  $url    Module package URL
     * @param   string  $dest   Path to put module package
     */
    public function download(string $url, string $dest): void
    {
        // Check and add default protocol if necessary
        if (!preg_match('%^http[s]?:\/\/%', $url)) {
            $url = 'http://' . $url;
        }
        // Download package
        if ($client = NetHttp::initClient($url, $path)) {
            try {
                $client->setUserAgent($this->user_agent);
                $client->useGzip(false);
                $client->setPersistReferers(false);
                $client->setOutput($dest);
                $client->get($path);
                unset($client);
            } catch (Exception $e) {
                unset($client);

                throw new CoreException(__('An error occurred while downloading the file.'));
            }
        } else {
            throw new CoreException(__('An error occurred while downloading the file.'));
        }
    }

    /**
     * Install a previously downloaded module.
     *
     * @param   string  $path   Path to module package
     *
     * @return  int             1 = installed, 2 = update
     */
    public function install(string $path): int
    {
        return Modules::installPackage($path, $this->modules);
    }

    /**
     * User Agent String.
     *
     * @param   string  $str    User agent string
     */
    public function agent(string $str): void
    {
        $this->user_agent = $str;
    }

    /**
     * Split and clean pattern.
     *
     * @param   string  $str    String to sanitize
     *
     * @return  array           Array of cleaned pieces of string or false if none
     */
    public static function patternize(string $str): array
    {
        $arr = [];

        foreach (explode(' ', $str) as $_) {
            $_ = strtolower(preg_replace('/[^A-Za-z0-9]/', '', $_));
            if (strlen($_) >= 2) {
                $arr[] = $_;
            }
        }

        return empty($arr) ? false : $arr;
    }

    /**
     * Compare version.
     *
     * @param   string  $v1     Version
     * @param   string  $v2     Version
     * @param   string  $op     Comparison operator
     *
     * @return  bool            True is comparison is true, dude!
     */
    private static function compare(string $v1, string $v2, string $op): bool
    {
        return version_compare(
            preg_replace('!-r(\d+)$!', '-p$1', $v1),
            preg_replace('!-r(\d+)$!', '-p$1', $v2),
            $op
        );
    }

    /**
     * Sort modules list.
     *
     * @param   array   $a      A module
     * @param   array   $b      A module
     * @return  int
     */
    private static function sort(array $a, array $b): int
    {
        $c = strtolower($a['id']);
        $d = strtolower($b['id']);
        if ($c == $d) {
            return 0;
        }

        return ($c < $d) ? -1 : 1;
    }
}
