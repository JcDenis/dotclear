<?php

/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright AGPL-3.0
 */
declare(strict_types=1);

namespace Dotclear\Helper\Network;

/**
 * @class HttpCacheStack
 */
class HttpCacheStack
{
    /**
     * The files path stack
     *
     * @var    string[]     $files
     */
    private array $files = [];

    /**
     * The timestamps stack
     *
     * @var    int[]        $times
     */
    private array $times = [];

    /**
     * Reset files list.
     */
    public function resetFiles(): void
    {
        $this->files = [];
    }

    /**
     * Add a file to the files list.
     *
     * @param   string  $file   The file path
     */
    public function addFile(string $file): void
    {
        $this->files[] = $file;
    }

    /**
     * Add files to the files list.
     *
     * @param   array<int,string>   $files  The files path to add
     */
    public function addFiles(array $files): void
    {
        foreach ($files as $file) {
            $this->addFile($file);
        }
    }

    /**
     * Get the files list.
     *
     * @return  array<int,string>   The files path
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    /**
     * Reset timestamps list.
     */
    public function resetTimes(): void
    {
        $this->times = [];
    }

    /**
     * Add a timestamp to the timestamps list.
     *
     * @param   int     $time   The timestamp
     */
    public function addTime(int $time): void
    {
        $this->times[] = $time;
    }

    /**
     * Add timestamps to the timestamps list.
     *
     * @param   array<int,int>  $times  The timestamps
     */
    public function addTimes(array $times): void
    {
        foreach ($times as $time) {
            $this->addTime($time);
        }
    }

    /**
     * Get the timestamps list.
     *
     * @return  array<int,int>  The timestamps
     */
    public function getTimes(): array
    {
        return $this->times;
    }
}
