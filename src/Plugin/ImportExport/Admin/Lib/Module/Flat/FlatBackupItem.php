<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\ImportExport\Admin\Lib\Module\Flat;

// Dotclear\Plugin\ImportExport\Admin\Lib\Module\Flat\FlatBackupItem

/**
 * Flat backup item for plugin ImportExport.
 *
 * @ingroup  Plugin ImportExport
 */
class FlatBackupItem
{
    public function __construct(public string $__name, private array $__data, public int $__line)
    {
    }

    public function get(string $name): string
    {
        return iconv('UTF-8', 'UTF-8//IGNORE', (string) $this->__data[$name]);
    }

    public function f(string $name): string
    {
        return $this->get($name);
    }

    public function set(string $n, mixed $v): void
    {
        $this->__data[$n] = $v;
    }

    public function exists(string $n): bool
    {
        return isset($this->__data[$n]);
    }

    public function drop(string ...$args): void
    {
        foreach ($args as $n) {
            if (isset($this->__data[$n])) {
                unset($this->__data[$n]);
            }
        }
    }

    public function substitute(string $old, string $new): void
    {
        if (isset($this->__data[$old])) {
            $this->__data[$new] = $this->__data[$old];
            unset($this->__data[$old]);
        }
    }
}
