<?php
/**
 * @class Dotclear\Process\Admin\Help\Help
 * @brief Dotclear admin locale help resources helper
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Help;

class Help
{
    private static $file = [];

    private $news    = '';
    private $doc     = [];
    private $context = [];
    private $flag    = null;

    public function news(string $value = null, bool $replace = false): ?string
    {
        if ($replace || empty($this->news)) {
            $this->news = $value;
        }

        return $this->news;
    }

    public function doc(array $values = null, bool $replace = true): array
    {
        if ($values) {
            if ($replace) {
                $this->doc = [];
            }
            foreach($values as $key => $value) {
                if (!array_key_exists($key, $this->doc)) {
                    $this->doc[$key] = (string) $value;
                }
            }
        }

        return $this->doc;
    }

    public function context(string $key, string $value = null, bool $replace = false): ?string
    {
        if ($replace || !array_key_exists($key, $this->context)) {
            $this->context[$key] = $value;
        }

        return $this->context[$key];
    }

    public function flag(bool $flag = null): ?bool
    {
        if (null !== $flag) {
            $this->flag = $flag;
        }

        return $this->flag;
    }

    public function file(string $file): void
    {
        # Do not require twice the same file (prevent loop)
        if (!isset(static::$file[$file])) {
            static::$file[$file] = true;
            ob_start();
            require_once $file;
            ob_end_clean();
        }
    }

    public function dump(): array
    {
        return [
            'news'    => $this->news,
            'doc'     => $this->doc,
            'context' => $this->context,
            'flag'    => $this->flag,
            'files'   => static::$files,
        ];
    }
}
