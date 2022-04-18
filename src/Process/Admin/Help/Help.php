<?php
/**
 * @note Dotclear\Process\Admin\Help\Help
 * @brief Dotclear admin locale help resources helper
 *
 * @ingroup  Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Help;

class Help
{
    /** @var array<string, bool>       Keep track of loaded file */
    private static $file = [];

    /** @var string New bloc */
    private $news = '';

    /** @var array<string, string>      Doc bloc */
    private $doc = [];

    /** @var array<string, string>      Context bloc */
    private $context = [];

    /** @var bool Admin context flag */
    private $flag;

    /**
     * Add/get news bloc.
     *
     * @param string $value   The value
     * @param bool   $replace Replace existing bloc
     *
     * @return null|string The new bloc
     */
    public function news(string $value = null, bool $replace = false): ?string
    {
        if ($replace || empty($this->news)) {
            $this->news = $value;
        }

        return $this->news;
    }

    /**
     * Add/get doc bloc.
     *
     * @param array $values  The values
     * @param bool  $replace Replace existing bloc
     *
     * @return array The doc bloc
     */
    public function doc(array $values = null, bool $replace = true): array
    {
        if ($values) {
            if ($replace) {
                $this->doc = [];
            }
            foreach ($values as $key => $value) {
                if (!array_key_exists($key, $this->doc)) {
                    $this->doc[$key] = (string) $value;
                }
            }
        }

        return $this->doc;
    }

    /**
     * Add/get context bloc.
     *
     * @param string $value   The key
     * @param string $value   The value
     * @param bool   $replace Replace existing bloc
     *
     * @return null|string The context bloc
     */
    public function context(string $key, string $value = null, bool $replace = false): ?string
    {
        if ($replace || !array_key_exists($key, $this->context)) {
            $this->context[$key] = $value;
        }

        return $this->context[$key];
    }

    /**
     * Set/get flag.
     *
     * @param bool $flag The flag
     *
     * @return null|bool The flag
     */
    public function flag(bool $flag = null): ?bool
    {
        if (null !== $flag) {
            $this->flag = $flag;
        }

        return $this->flag;
    }

    /**
     * Require a ressource file.
     *
     * @param string $file The file path
     */
    public function file(string $file): void
    {
        // Do not require twice the same file (prevent loop)
        if (!isset(self::$file[$file])) {
            self::$file[$file] = true;
            ob_start();

            require_once $file;
            ob_end_clean();
        }
    }

    /**
     * Dump help.
     *
     * @return array<string, mixed> The help
     */
    public function dump(): array
    {
        return [
            'news'    => $this->news,
            'doc'     => $this->doc,
            'context' => $this->context,
            'flag'    => $this->flag,
            'files'   => self::$file,
        ];
    }
}
