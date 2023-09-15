<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Interface\Core;

use Dotclear\Database\Cursor;
use Dotclear\Helper\File\File;
use Exception;

/**
 * Media manager interface.
 *
 * @since 2.28
 */
interface MediaInterface
{
    /**
     * The Media database table name.
     *
     * @var     string  MEDIA_TABLE_NAME
     */
    public const MEDIA_TABLE_NAME = 'media';

    /**
     * Open a database table cursor.
     *
     * @return  Cursor  The media database table cursor
     */
    public function openMediaCursor(): Cursor;

    /**
     * Get post media instance
     *
     * @return  PostMediaInterface  The psot media handler
     */
    public function postMedia(): PostMediaInterface;

    /**
     * Get thumbnail file pattern.
     *
     * @param   string  $type   The thumbnail type (default, alpha, webp)
     *
     * @return  string  The file pattern
     */
    public function getThumbnailFilePattern(string $type = ''): string;

    /**
     * Get thumb sizes definniton.
     *
     * Tubmnail sizes:
     * - m: medium image
     * - s: small image
     * - t: thumbnail image
     * - sq: square image
     *
     * @return array<string,array<int|string,string>>
     */
    public function getThumbSizes(): array;

    /**
     * Set media type filter.
     *
     * Correspond, if set, to the base mimetype (ex: "image" for "image/jpg" mimetype)
     * Might be image, audio, text, vidéo, application
     *
     * @param   string  $type The base mime type
     */
    public function setFilterMimeType(string $type): void;

    /**
     * Get current root path.
     *
     * @return  string The current root path
     */
    public function getRoot(): string;

    /**
     * Get current root public URL.
     *
     * @return  string The current root URL
     */
    public function getRootUrl(): string;

    /**
     * Change working directory.
     *
     * $dir is relative to instance {@link $root} directory.
     *
     * @param   string  $dir    Directory
     */
    public function chdir(?string $dir): void;

    /**
     * Get working directory
     *
     * @return  string  The working directory path
     */
    public function getPwd(): string;

    /**
     * Current directory is writable.
     *
     * @return  bool    true if working directory is writable
     */
    public function writable(): bool;

    /**
     * Add exclusion
     *
     * Appends an exclusion to exclusions list.
     *
     * @see     $exclude_list
     *
     * @param   string|array<int,string>    $list   Exclusion regexp
     */
    public function addExclusion($list): void;

    /**
     * Sets the exclude pattern.
     *
     * (returned type not set for backward compatibility with Helper\File\Manager)
     *
     * @param   string  $pattern    The regexp pattern
     *
     * @return void
     */
    public function setExcludePattern(string $pattern);

    /**
     * File in files.
     *
     * @param   string  $file   File to match (relative to root)
     *
     * @return  bool    true if file $file is in files array of {@link $dir}
     */
    public function inFiles(string $file): bool;

    /**
     * Adds a new file handler for a given media type and event.
     *
     * Available events are:
     * - create: file creation
     * - update: file update
     * - remove: file deletion
     *
     * (returned type not set for backward compatibility with Helper\File\Manager)
     *
     * @param   string          $type       The media type
     * @param   string          $event      The event
     * @param   callable|array  $function   The callback
     *
     * @return void
     */
    public function addFileHandler(string $type, string $event, $function);

    /**
     * Returns HTML breadCrumb for media manager navigation.
     *
     * @param   string  $href   The URL pattern
     * @param   string  $last   The last item pattern
     *
     * @return  string  HTML code
     */
    public function breadCrumb(string $href, string $last = ''): string;

    /**
     * Sets the file sort.
     *
     * (returned type not set for backward compatibility with Helper\File\Manager)
     *
     * @param   string  $type   The type
     *
     * @return void
     */
    public function setFileSort(string $type = 'name');

    /**
     * Get current dirs.
     *
     * @return array<int,File>
     */
    public function getDirs(): array;

    /**
     * Get current dirs.
     *
     * @return array<int,File>
     */
    public function getFiles(): array;

    /**
     * Gets current working directory content (using filesystem).
     *
     * (returned type not set for backward compatibility with Helper\File\Manager)
     *
     * @return void
     */
    public function getFSDir();

    /**
     * Gets current working directory content.
     *
     * @param   null|string     $type   The media type filter
     *
     * @throws  Exception
     */
    public function getDir(?string $type = null): void;

    /**
     * Gets file by its id. Returns a filteItem object.
     *
     * @param   int     $id     The file identifier
     *
     * @return  File    The file.
     */
    public function getFile(int $id): ?File;

    /**
     * Search into media db (only).
     *
     * @param   string  $query  The search query
     *
     * @return  bool    true or false if nothing found
     */
    public function searchMedia(string $query): bool;

    /**
     * Returns media items attached to a blog post.
     *
     * Result is an array containing Files objects.
     *
     * @param   int     $post_id    The post identifier
     * @param   mixed   $media_id   The media identifier(s)
     * @param   mixed   $link_type  The link type(s)
     *
     * @return  array<int,File>     Array of Files.
     */
    public function getPostMedia(int $post_id, $media_id = null, $link_type = null): array;

    /**
     * Rebuilds database items collection.
     *
     * Optional <var>$pwd</var> parameter is
     * the path where to start rebuild.
     *
     * @param   string  $pwd        The directory to rebuild
     * @param   bool    $recursive  If true rebuild also sub-directories
     *
     * @throws  Exception
     */
    public function rebuild(string $pwd = '', bool $recursive = false): void;

    /**
     * Rebuilds thumbnails.
     *
     * Optional <var>$pwd</var> parameter is
     * the path where to start rebuild.
     *
     * @param   string  $pwd        The directory to rebuild
     * @param   bool    $force      Recreate existing thumbnails if True
     * @param   bool    $recursive  If true rebuild also sub-directories
     *
     * @throws  Exception
     */
    public function rebuildThumbnails(string $pwd = '', bool $recursive = false, bool $force = false): void;

    /**
     * Makes a dir.
     *
     * @param   string  $name   The directory to create
     */
    public function makeDir(?string $name): void;

    /**
     * Remove a dir.
     *
     * Removes a directory which is relative to working directory.
     *
     * @param   string  $directory  Directory to remove
     */
    public function removeDir(?string $directory): void;

    /**
     * Creates or updates a file in database.
     *
     * Returns new media ID or false if file does not exist.
     *
     * (returned type not set for backward compatibility with Helper\File\Manager)
     *
     * @param   string  $name       The file name (relative to working directory)
     * @param   string  $title      The file title
     * @param   bool    $private    File is private
     * @param   mixed   $dt         File date
     * @param   bool    $force      The force flag
     *
     * @throws  Exception
     *
     * @return  int|false    New media ID or false
     */
    public function createFile(string $name, ?string $title = null, bool $private = false, $dt = null, bool $force = true);

    /**
     * Updates a file in database.
     *
     * (returned type not set for backward compatibility with Helper\File\Manager)
     *
     * @param   File    $file       The file
     * @param   File    $newFile    The new file
     *
     * @throws  Exception
     *
     * @return void
     */
    public function updateFile(File $file, File $newFile);

    /**
     * Uploads a file.
     *
     * Helper\Manager returns string but Core\Media returns int|false.
     *
     * @param   string      $tmp        The full path of temporary uploaded file
     * @param   string      $dest       The file name (relative to working directory)me
     * @param   bool        $overwrite  File should be overwrite
     * @param   string      $title      The file title (should be string|null)
     * @param   bool        $private    File is private
     *
     * @throws  Exception
     *
     * @return  mixed   New media ID or false (should be int|false)
     */
    public function uploadFile(string $tmp, string $dest, bool $overwrite = false, ?string $title = null, bool $private = false);

    /**
     * Creates a file from binary content.
     *
     * @param   string  $name   The file name (relative to working directory)
     * @param   string  $bits   The binary file contentits
     *
     * @throws  Exception
     *
     * @return  string  New media ID or false
     */
    public function uploadBits(string $name, string $bits): string;

    /**
     * Remove item
     *
     * Removes a file or directory which is relative to working directory.
     *
     * @param string    $name            Item to remove
     */
    public function removeItem(?string $name): void;

    /**
     * Removes a file.
     *
     * @param   string  $file   filename
     *
     * @throws  Exception
     */
    public function removeFile(?string $file): void;

    /**
     * Root directories
     *
     * Returns an array of directory under {@link $root} directory.
     *
     * @uses    File
     *
     * @return  array<int,string>
     */
    public function getDBDirs(): array;

    /**
     * Extract zip file in current location.
     *
     * @param   File    $f              File object
     * @param   bool    $create_dir     Create dir
     *
     * @throws  Exception
     *
     * @return  string  The destination
     */
    public function inflateZipFile(File $f, bool $create_dir = true): string;

    /**
     * Gets the zip content.
     *
     * @param   File    $f  File object
     *
     * @return  array  The zip content.
     */
    public function getZipContent(File $f): array;

    /**
     * Calls file handlers registered for recreate event.
     *
     * @param   File    $f  File object
     */
    public function mediaFireRecreateEvent(File $f): void;

    /* Image handlers
    ------------------------------------------------------- */
    /**
     * Create image thumbnails.
     *
     * @param   Cursor  $cur    The Cursor
     * @param   string  $f      Image filename
     * @param   bool    $force  Force creation
     *
     * @return  bool
     */
    public function imageThumbCreate(?Cursor $cur, string $f, bool $force = true): bool;

    /**
     * Remove image thumbnails.
     *
     * @param   string  $f  Image filename
     *
     * @return  bool
     */
    public function imageThumbRemove(string $f): bool;

    /**
     * Returns HTML code for audio player (HTML5).
     *
     * @param   string  $type       The audio mime type
     * @param   string  $url        The audio URL to play
     * @param   string  $player     The player URL
     * @param   mixed   $args       The player arguments
     * @param   bool    $fallback   The fallback
     * @param   bool    $preload    Add preload="auto" attribute if true, else preload="none"
     *
     * @return  string
     */
    public static function audioPlayer(string $type, string $url, ?string $player = null, $args = null, bool $fallback = false, bool $preload = true): string;

    /**
     * Returns HTML code for video player (HTML5).
     *
     * @param   string  $type       The video mime type
     * @param   string  $url        The video URL to play
     * @param   string  $player     The player URL
     * @param   mixed   $args       The player arguments
     * @param   bool    $fallback   The fallback (not more used)
     * @param   bool    $preload    Add preload="auto" attribute if true, else preload="none"
     *
     * @return  string
     */
    public static function videoPlayer(string $type, string $url, ?string $player = null, $args = null, bool $fallback = false, bool $preload = true): string;

    /**
     * Returns HTML code for MP3 player (HTML5).
     *
     * @param   string  $url        The audio URL to play
     * @param   string  $player     The player URL
     * @param   mixed   $args       The player arguments
     * @param   bool    $fallback   The fallback (not more used)
     * @param   bool    $preload    Add preload="auto" attribute if true, else preload="none"
     *
     * @return  string
     */
    public static function mp3player(string $url, ?string $player = null, $args = null, bool $fallback = false, bool $preload = true): string;

    /**
     * Returns HTML code for FLV player.
     *
     * @deprecated  since 2.15, nothing to use instead
     *
     * @param   string  $url        The url
     * @param   string  $player     The player
     * @param   mixed   $args       The arguments
     *
     * @return  string
     */
    public static function flvplayer(string $url, ?string $player = null, $args = null): string;
}
