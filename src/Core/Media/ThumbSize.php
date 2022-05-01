<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Media;

// Dotclear\Core\Media\ThumbSize

/**
 * Media thumb sizes stackers.
 *
 * This class handles Dotclear media items
 * thumb sizes definitions.
 *
 * @ingroup  Core Media
 */
class ThumbSize
{
    /**
     * @var array<string,string> $codes
     *                           Media thumb sizes codes
     */
    private array $codes = [];

    /**
     * @var array<string,int> $sizes
     *                        Media thumb sizes sizes
     */
    private array $sizes = [];

    /**
     * @var array<string,bool> $crops
     *                         Media thumb sizes resizes style (crop or ratio)
     */
    private array $crops = [];

    /**
     * @var array<string,string> $names
     *                           Media thumb sizes names
     */
    private array $names = [];

    /**
     * Add a thumb size defintion.
     *
     * @param string      $code The code
     * @param null|bool   $crop Ratio or crop
     * @param null|int    $size The size
     * @param null|string $name The name
     *
     * @return self
     */
    public function set(string $code, ?int $size = null, ?bool $crop = null, ?string $name = null)
    {
        if (!$this->exists($code)) {
            $this->codes[$code] = $code;
            $this->sizes[$code] = 0;
            $this->crops[$code] = false;
            $this->names[$code] = '';
        }
        if (null !== $size) {
            $this->sizes[$code] = $size;
        }
        if (null !== $crop) {
            $this->crops[$code] = $crop;
        }
        if (null !== $name) {
            $this->names[$code] = $name;
        }

        return $this;
    }

    /**
     * Check if a code exists.
     *
     * @param string The code
     *
     * @return bool True if code Exists
     */
    public function exists(string $code): bool
    {
        return array_key_exists($code, $this->codes);
    }

    /**
     * Get all thumb size codes.
     *
     * @return array<string, string> The codes
     */
    public function getCodes(): array
    {
        return $this->codes;
    }

    /**
     * Get a thumb size size.
     *
     * @param string $code The code
     *
     * @return int The size
     */
    public function getSize(string $code): int
    {
        return $this->exists($code) ? $this->sizes[$code] : 0;
    }

    /**
     * Get all thumb size sizes.
     *
     * @return array<string, int> The sizes
     */
    public function getSizes(): array
    {
        return $this->sizes;
    }

    /**
     * Get a thumb size resize style.
     *
     * @param string $code The code
     *
     * @return bool True if crop (false for ratio)
     */
    public function isCrop(string $code): bool
    {
        return $this->exists($code) ? $this->crop[$code] : false;
    }

    /**
     * Get a thumb size resize style literal.
     *
     * @param string $code The code
     *
     * @return string crop or ratio
     */
    public function getCrop(string $code): string
    {
        return $this->isCrop($code) ? 'crop' : 'ratio';
    }

    /**
     * Get all thumb size resize styles.
     *
     * @return array<string, bool> The style
     */
    public function getCrops(): array
    {
        return $this->crops;
    }

    /**
     * Get a thumb size name.
     *
     * @param string $code  The code
     * @param bool   $trans Translate property
     *
     * @return string The name
     */
    public function getName(string $code, $trans = true): string
    {
        return $this->exists($code) ? ($trans ? __($this->names[$code]) : $this->names[$code]) : '';
    }

    /**
     * Get all thumb size names.
     *
     * @return array<string, string> The names
     */
    public function getNames(): array
    {
        return $this->names;
    }
}
