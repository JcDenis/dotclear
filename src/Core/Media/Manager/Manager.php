<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Media\Manager;

// Dotclear\Core\Media\Manager\Manager
use Dotclear\Exception\FileException;
use Dotclear\Helper\File\Path;
use Dotclear\Helper\File\Files;

/**
 * File manager tool.
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * @ingroup  Core Media File
 */
class Manager
{
    /**
     * @var string $pwd
     *             Working (current) director
     */
    protected $pwd = '';

    /**
     * @var array $exclude_list
     *            Array of regexps defining excluded items
     */
    protected $exclude_list = [];

    /**
     * @var string $exclude_pattern
     *             Files exclusion regexp pattern
     */
    protected $exclude_pattern = '';

    /**
     * @var array $dir
     *            Current directory content array
     */
    public $dir = ['dirs' => [], 'files' => []];

    /**
     * Constructor.
     *
     * New filemanage istance. Note that filemanage is a jail in given root
     * path. You won't be able to access files outside $root path with
     * the object's methods.
     *
     * @param string $root     Root path
     * @param string $root_url Root URL
     */
    public function __construct(public string $root, public string $root_url = '')
    {
        $this->root = $this->pwd = Path::real($this->root);

        if (!$this->root) {
            throw new FileException('Invalid root directory.');
        }

        if (!preg_match('#/$#', (string) $this->root_url)) {
            $this->root_url = $this->root_url . '/';
        }
    }

    /**
     * Change directory.
     *
     * Changes working directory. $dir is relative to instance
     * $root directory.
     *
     * @param null|string $dir Directory
     */
    public function chdir(?string $dir): void
    {
        $realdir = Path::real($this->root . '/' . Path::clean($dir));
        if (!$realdir || !is_dir($realdir)) {
            throw new FileException('Invalid directory.');
        }

        if ($this->isExclude($realdir)) {
            throw new FileException('Directory is excluded.');
        }

        $this->pwd = $realdir;
    }

    /**
     * Get working directory.
     */
    public function getPwd(): string
    {
        return $this->pwd;
    }

    /**
     * Check if current directory is writable.
     *
     * @return bool True if working directory is writable
     */
    public function writable(): bool
    {
        return !$this->pwd ? false : is_writable($this->pwd);
    }

    /**
     * Add exclusion.
     *
     * Appends an exclusion to exclusions list. $f should be a regexp.
     *
     * @see     self::$exclude_list
     *
     * @param array|string $f Exclusion regexp
     */
    public function addExclusion(array|string $f): void
    {
        if (is_array($f)) {
            foreach ($f as $v) {
                if (false !== ($V = Path::real($v))) {
                    $this->exclude_list[] = $V;
                }
            }
        } elseif (false !== ($F = Path::real($f))) {
            $this->exclude_list[] = $F;
        }
    }

    /**
     * Path is excluded.
     *
     * Returns true if path (file or directory) $f is excluded. $f path is
     * relative to $root path.
     *
     * @see self::$exclude_list
     *
     * @param string $f Path to match
     */
    protected function isExclude(string $f): bool
    {
        foreach ($this->exclude_list as $v) {
            if (str_starts_with($f, $v)) {
                return true;
            }
        }

        return false;
    }

    /**
     * File is excluded.
     *
     * Returns true if file $f is excluded. $f path is relative to
     * $root path.
     *
     * @see self::$exclude_pattern
     *
     * @param string $f File to match
     */
    protected function isFileExclude(string $f): bool
    {
        return !$this->exclude_pattern ? false : (bool) preg_match($this->exclude_pattern, (string) $f);
    }

    /**
     * Item in jail.
     *
     * Returns true if file or directory $f is in jail.
     * ie. not outside the $root directory.
     *
     * @param string $f Path to match
     */
    protected function inJail(string $f): bool
    {
        $f = Path::real($f);

        return false !== $f ? (bool) preg_match('|^' . preg_quote($this->root, '|') . '|', (string) $f) : false;
    }

    /**
     * File in files.
     *
     * Returns true if file $f is in files array of $dir.
     *
     * @param string $f File to match
     */
    public function inFiles(string $f): bool
    {
        foreach ($this->dir['files'] as $v) {
            if ($v->relname == $f) {
                return true;
            }
        }

        return false;
    }

    /**
     * Directory list.
     *
     * Creates list of items in working directory and append it to $dir
     *
     * @uses sortHandler(), fileItem
     */
    public function getDir(): void
    {
        $dir = Path::clean($this->pwd);

        $dh = @opendir($dir);

        if (false === $dh) {
            throw new FileException('Unable to read directory.');
        }

        $d_res = $f_res = [];

        while (false !== ($file = readdir($dh))) {
            $fname = $dir . '/' . $file;

            if ($this->inJail($fname) && !$this->isExclude($fname)) {
                if (is_dir($fname) && '.' != $file) {
                    $tmp = new Item($fname, $this->root, $this->root_url);
                    if ('..' == $file) {
                        $tmp->parent = true;
                    }
                    $d_res[] = $tmp;
                }

                if (is_file($fname) && !str_starts_with($file, '.') && !$this->isFileExclude($file)) {
                    $f_res[] = new Item($fname, $this->root, $this->root_url);
                }
            }
        }
        closedir($dh);

        $this->dir = ['dirs' => $d_res, 'files' => $f_res];
        usort($this->dir['dirs'], [$this, 'sortHandler']);
        usort($this->dir['files'], [$this, 'sortHandler']);
    }

    /**
     * Root directories.
     *
     * Returns an array of directory under $root directory.
     *
     * @return array<int, Item> The items
     */
    public function getRootDirs(): array
    {
        $d = Files::getDirList($this->root);

        $dir = [];

        foreach ($d['dirs'] as $v) {
            $dir[] = new Item($v, $this->root, $this->root_url);
        }

        return $dir;
    }

    /**
     * Upload file.
     *
     * Move <var>$tmp</var> file to its final destination <var>$dest</var> and
     * returns the destination file path.
     * <var>$dest</var> should be in jail. This method will throw exception
     * if the file cannot be written.
     *
     * You should first verify upload status, with {@link Files::uploadStatus()}
     * or PHP native functions.
     *
     * @see Files::uploadStatus()
     *
     * @param string $tmp       Temporary uploaded file path
     * @param string $dest      Destination file
     * @param bool   $overwrite Overwrite mode
     *
     * @throws FileException
     *
     * @return string Destination real path
     */
    public function uploadFile(string $tmp, string $dest, bool $overwrite = false): string
    {
        $dest = $this->pwd . '/' . Path::clean($dest);

        if ($this->isFileExclude($dest)) {
            throw new FileException(__('Uploading this file is not allowed.'));
        }

        if (!$this->inJail(dirname($dest))) {
            throw new FileException(__('Destination directory is not in jail.'));
        }

        if (!$overwrite && file_exists($dest)) {
            throw new FileException(__('File already exists.'));
        }

        if (!is_writable(dirname($dest))) {
            throw new FileException(__('Cannot write in this directory.'));
        }

        if (false === @move_uploaded_file($tmp, $dest)) {
            throw new FileException(__('An error occurred while writing the file.'));
        }

        Files::inheritChmod($dest);

        return Path::real($dest);
    }

    /**
     * Upload file by bits.
     *
     * Creates a new file <var>$name</var> with contents of <var>$bits</var> and
     * return the destination file path.
     * <var>$name</var> should be in jail. This method will throw exception
     * if file cannot be written.
     *
     * @param string $bits Destination file content
     * @param string $name Destination file
     *
     * @throws FileException
     *
     * @return string Destination real path
     */
    public function uploadBits(string $name, string $bits): string
    {
        $dest = $this->pwd . '/' . Path::clean($name);

        if ($this->isFileExclude($dest)) {
            throw new FileException(__('Uploading this file is not allowed.'));
        }

        if (!$this->inJail(dirname($dest))) {
            throw new FileException(__('Destination directory is not in jail.'));
        }

        if (!is_writable(dirname($dest))) {
            throw new FileException(__('Cannot write in this directory.'));
        }

        $fp = @fopen($dest, 'wb');
        if (false === $fp) {
            throw new FileException(__('An error occurred while writing the file.'));
        }

        fwrite($fp, $bits);
        fclose($fp);
        Files::inheritChmod($dest);

        return Path::real($dest);
    }

    /**
     * New directory.
     *
     * Creates a new directory <var>$d</var> relative to working directory.
     *
     * @param string $d Directory name
     */
    public function makeDir(string $d): void
    {
        Files::makeDir($this->pwd . '/' . Path::clean($d));
    }

    /**
     * Move file.
     *
     * Moves a file <var>$s</var> to a new destination <var>$d</var>. Both
     * <var>$s</var> and <var>$d</var> are relative to $root.
     *
     * @param string $s Source file
     * @param string $d Destination file
     *
     * @throws FileException
     */
    public function moveFile(string $s, string $d): void
    {
        $s = $this->root . '/' . Path::clean($s);
        $d = $this->root . '/' . Path::clean($d);

        if (false === ($s = Path::real($s))) {
            throw new FileException(__('Source file does not exist.'));
        }

        $dest_dir = Path::real(dirname($d));

        if (!$this->inJail($s)) {
            throw new FileException(__('File is not in jail.'));
        }
        if (!$this->inJail($dest_dir)) {
            throw new FileException(__('File is not in jail.'));
        }

        if (!is_writable($dest_dir)) {
            throw new FileException(__('Destination directory is not writable.'));
        }

        if (false === @rename($s, $d)) {
            throw new FileException(__('Unable to rename file.'));
        }
    }

    /**
     * Remove item.
     *
     * Removes a file or directory <var>$f</var> which is relative to working
     * directory.
     *
     * @param string $f Path to remove
     */
    public function removeItem(string $f): void
    {
        $file = Path::real($this->pwd . '/' . Path::clean($f));

        if (false === $file) {
            return;
        }
        if (is_file($file)) {
            $this->removeFile($f);
        } elseif (is_dir($file)) {
            $this->removeDir($f);
        }
    }

    /**
     * Remove item.
     *
     * Removes a file <var>$f</var> which is relative to working directory.
     *
     * @param string $f File to remove
     *
     * @throws FileException
     */
    public function removeFile(string $f): void
    {
        $f = Path::real($this->pwd . '/' . Path::clean($f));

        if (false === $f) {
            return;
        }

        if (!$this->inJail($f)) {
            throw new FileException(__('File is not in jail.'));
        }

        if (!Files::isDeletable($f)) {
            throw new FileException(__('File cannot be removed.'));
        }

        if (false === @unlink($f)) {
            throw new FileException(__('File cannot be removed.'));
        }
    }

    /**
     * Remove item.
     *
     * Removes a directory <var>$d</var> which is relative to working directory.
     *
     * @param string $d Directory to remove
     *
     * @throws FileException
     */
    public function removeDir(string $d): void
    {
        $d = Path::real($this->pwd . '/' . Path::clean($d));

        if (false === $d) {
            return;
        }

        if (!$this->inJail($d)) {
            throw new FileException(__('Directory is not in jail.'));
        }

        if (!Files::isDeletable($d)) {
            throw new FileException(__('Directory cannot be removed.'));
        }

        if (false === @rmdir($d)) {
            throw new FileException(__('Directory cannot be removed.'));
        }
    }

    /**
     * SortHandler.
     *
     * This method is called by {@link getDir()} to sort files. Can be overrided
     * in inherited classes.
     *
     * @param Item $a Item object
     * @param Item $b Item object
     */
    protected function sortHandler(Item $a, Item $b): int
    {
        if ($a->parent && !$b->parent || !$a->parent && $b->parent) {
            return ($a->parent) ? -1 : 1;
        }

        return strcasecmp($a->basename, $b->basename);
    }
}
