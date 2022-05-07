<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Modules\Repository;

// Dotclear\Modules\Repository\Repository
use Dotclear\App;
use Dotclear\Exception\ModuleException;
use Dotclear\Helper\Network\NetHttp\NetHttp;
use Dotclear\Modules\Modules;
use Dotclear\Modules\ModuleDefine;
use Exception;

/**
 * Repository modules manager.
 *
 * @ingroup  Module Store
 */
class Repository
{
    /**
     * @var array<string,int> $weighting
     *                        Modules fields to search on and their weighting
     */
    public static $weighting = [
        'id'     => 10,
        'name'   => 8,
        'tags'   => 6,
        'desc'   => 4,
        'author' => 2,
    ];

    /**
     * @var string $user_agent
     *             User agent used to query repository
     */
    protected $user_agent = 'DotClear.org RepoBrowser/0.1';

    /**
     * @var array<string,array> $data
     *                          Array of new/update modules from repository
     */
    protected $data = ['new' => [], 'update' => []];

    /**
     * Constructor.
     *
     * @param Modules $modules Modules instance
     * @param string  $xml_url XML feed URL
     * @param bool    $force   Force query repository
     */
    public function __construct(public Modules $modules, protected string $xml_url, bool $force = false)
    {
        $this->user_agent = sprintf('Dotclear/%s)', App::core()->config()->get('core_version'));

        $this->check($force);
    }

    /**
     * Check repository.
     *
     * @param bool $force Force query repository
     *
     * @return bool True if get feed or cache
     */
    public function check(bool $force = false): bool
    {
        if (!$this->xml_url) {
            return false;
        }

        try {
            $parser = App::core()->config()->get('store_update_noauto') ? false : RepositoryReader::quickParse($this->xml_url, App::core()->config()->get('cache_dir'), $force);
        } catch (\Exception) {
            return false;
        }

        $raw_datas = !$parser ? [] : $parser->getModules();

        uasort($raw_datas, [$this, 'sort']);

        $skipped = array_keys($this->modules->getDisabledModules());
        foreach ($skipped as $id) {
            if (isset($raw_datas[$id])) {
                unset($raw_datas[$id]);
            }
        }

        $updates = [];
        foreach ($this->modules->getModules() as $id => $module) {
            /*
                        // non privileged user has no info //! todo: check new perms
                        if (!is_array($module)) {
                            // continue;
                        }
            */
            // main repository
            if (isset($raw_datas[$id])) {
                if ($this->compare($raw_datas[$id]['version'], $module->version(), '>')) {
                    $updates[$id]                    = $raw_datas[$id];
                    $updates[$id]['current_version'] = $module->version();
                    $updates[$id]['repository']      = '';
                }
                unset($raw_datas[$id]);
            }
            // per module third-party repository
            if (!empty($module->repository()) && App::core()->config()->get('store_allow_repo')) {
                try {
                    if (false !== ($dcs_parser = RepositoryReader::quickParse($module->repository(), App::core()->config()->get('cache_dir'), $force))) {
                        $dcs_raw_datas = $dcs_parser->getModules();
                        if (isset($dcs_raw_datas[$id]) && $this->compare($dcs_raw_datas[$id]['version'], $module->version(), '>')) {
                            if (!isset($updates[$id]) || $this->compare($dcs_raw_datas[$id]['version'], $raw_datas[$id]['version']['version'], '>')) {
                                $updates[$id]                    = $dcs_raw_datas[$id];
                                $updates[$id]['current_version'] = $module->version();
                                $updates[$id]['repository']      = $module->repository();
                            }
                        }
                    }
                } catch (Exception $e) {
                }
            }

            if (!empty($updates[$id])) {
                $updates[$id]['type']            = $this->modules->getType();
                $updates[$id]['root']            = $module->root();
                $updates[$id]['root_writable']   = $module->writable();

                $updates[$id] = new ModuleDefine($this->modules->getType(), $id, $updates[$id]);

                if (!empty($updates[$id]->error()->flag())) {
                    unset($updates[$id]);
                }
            }
        }

        // Convert new modules from array to Define object
        foreach ($raw_datas as $id => $properties) {
            $properties['type'] = $this->modules->getType();
            $raw_datas[$id]     = new ModuleDefine($this->modules->getType(), $id, $properties);

            if (!empty($raw_datas[$id]->error()->flag())) {
                unset($raw_datas[$id]);
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
     * @param bool $update True to get update modules, false for new ones
     *
     * @return array List of update/new modules
     */
    public function get(bool $update = false): array
    {
        return $this->data[$update ? 'update' : 'new'];
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
     * @param string $pattern String to search
     *
     * @return array Match modules
     */
    public function search(string $pattern): array
    {
        $result = [];
        $sorter = [];

        // Split query into small clean words
        if (!($patterns = $this->patternize($pattern))) {
            return $result;
        }

        // For each modules
        foreach ($this->data['new'] as $id => $module) {
            $properties = $module->properties();

            // Loop through required module fields
            foreach (self::$weighting as $field => $weight) {
                // Skip fields which not exsist on module
                if (empty($properties[$field])) {
                    continue;
                }

                // Split field value into small clean word
                if (!($subjects = $this->patternize($properties[$field]))) {
                    continue;
                }

                // Check contents
                if (!($nb = preg_match_all('/(' . implode('|', $patterns) . ')/', implode(' ', $subjects), $_))) {
                    continue;
                }

                // Add module to result
                if (!isset($sorter[$id])) {
                    $sorter[$id] = 0;
                    $result[$id] = $module;
                }

                // Increment score by matches count * field weight
                $sorter[$id] += $nb * $weight;
                $result[$id]->setScore($sorter[$id]);
            }
        }
        // Sort response by matches count
        if (!empty($result)) {
            array_multisort($sorter, SORT_DESC, $result);
        }

        return $result;
    }

    /**
     * Quick download and install module.
     *
     * @param string $url  Module package URL
     * @param string $dest Path to install module
     *
     * @return int 1 = installed, 2 = update
     */
    public function process(string $url, string $dest): int
    {
        $this->download($url, $dest);

        return $this->install($dest);
    }

    /**
     * Download a module.
     *
     * @param string $url  Module package URL
     * @param string $dest Path to put module package
     */
    public function download(string $url, string $dest): void
    {
        // Check and add default protocol if necessary
        if (!preg_match('%^http[s]?:\/\/%', $url)) {
            $url = 'http://' . $url;
        }
        $path = '';
        // Download package
        if ($client = NetHttp::initClient($url, $path)) {
            try {
                $client->setUserAgent($this->user_agent);
                $client->useGzip(false);
                $client->setPersistReferers(false);
                $client->setOutput($dest);
                $client->get($path);
                unset($client);
            } catch (\Exception) {
                unset($client);

                throw new ModuleException(__('An error occurred while downloading the file.'));
            }
        } else {
            throw new ModuleException(__('An error occurred while downloading the file.'));
        }
    }

    /**
     * Install a previously downloaded module.
     *
     * @param string $path Path to module package
     *
     * @return int 1 = installed, 2 = update
     */
    public function install(string $path): int
    {
        return $this->modules->installPackage($path);
    }

    /**
     * User Agent String.
     *
     * @param string $str User agent string
     */
    public function agent(string $str): void
    {
        $this->user_agent = $str;
    }

    /**
     * Split and clean pattern.
     *
     * @param array|string $str String to sanitize
     *
     * @return array Array of cleaned pieces of string or false if none
     */
    public function patternize(string|array $str): array
    {
        $arr = [];
        if (!is_array($str)) {
            $str = explode(' ', $str);
        }

        foreach ($str as $word) {
            $word = strtolower(preg_replace('/[^A-Za-z0-9]/', '', $word));
            if (strlen($word) >= 2) {
                $arr[] = $word;
            }
        }

        return empty($arr) ? false : $arr;
    }

    /**
     * Compare version.
     *
     * @param string $v1 Version
     * @param string $v2 Version
     * @param string $op Comparison operator
     *
     * @return bool True is comparison is true, dude!
     */
    private function compare(string $v1, string $v2, string $op): bool
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
     * @param array $a A module
     * @param array $b A module
     */
    private function sort(array $a, array $b): int
    {
        $c = strtolower($a['id']);
        $d = strtolower($b['id']);
        if ($c == $d) {
            return 0;
        }

        return ($c < $d) ? -1 : 1;
    }
}
