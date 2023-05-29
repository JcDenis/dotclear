<?php
/**
 * @brief Formater core class
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core;

use dcCore;

class Formater
{
    /** @var    string  The legacy editor ID */
    public const LEGACY_FORMATER = 'dcLegacyEditor';

    /** @var    array<string,array<string,callable>>    The formaters stack */
    private $stack = [];

    /** @var    array<string,string>    The formats names */
    private $names = [];

    /**
     * Add a formater name.
     *
     * @param   string  $format     The format
     * @param   string  $name       The name
     */
    public function setName(string $format, string $name): void
    {
        $this->names[$format] = $name;
    }

    /**
     * Get the formater name.
     *
     * @param   string  $format     The format
     *
     * @return  string  The formater name.
     */
    public function getName(string $format): string
    {
        return $this->names[$format] ?? $format;
    }

    /**
     * Add a new text formater.
     *
     * Which will call the function <var>$func</var> to
     * transform text. The function must be a valid callback and takes one
     * argument: the string to transform. It returns the transformed string.
     *
     * @param   string      $id         The editor identifier (dcLegacyEditor, dcCKEditor, ...)
     * @param   string      $name       The formater name
     * @param   mixed       $callback   The function to use, must be a valid and callable callback
     */
    public function add(string $id, string $name, mixed $callback): void
    {
        if (is_callable($callback)) {
            $this->stack[$id][$name] = $callback;
        }
    }

    /**
     * Gets the editors list.
     *
     * @return  array<string,string>  The editors ID/name.
     */
    public function getEditors(): array
    {
        $editors = [];

        foreach (array_keys($this->stack) as $editor_id) {
            $editors[$editor_id] = __(dcCore::app()->plugins->getDefine($editor_id)->name);
        }

        return $editors;
    }

    /**
     * Gets all formaters.
     *
     * return all formaters sorted by actives editors
     *
     * @return  array<string,array<int,string>>  The formaters.
     */
    public function getFormaters(): array
    {
        $formaters_list = [];

        foreach ($this->stack as $editor => $formaters) {
            $formaters_list[$editor] = array_keys($formaters);
        }

        return $formaters_list;
    }

    /**
     * Gets the formaters for a given editor.
     *
     * return formaters for an editor if editor is active
     * return empty() array if editor is not active.
     * It can happens when a user choose an editor and admin deactivate that editor later
     *
     * @param   string  $id     The editor identifier (dcLegacyEditor, dcCKEditor, ...)
     *
     * @return  array<int,string>   The editor formaters.
     */
    public function getEditorFormaters(string $id): array
    {
        $formaters_list = [];

        if (isset($this->stack[$id])) {
            $formaters_list = array_keys($this->stack[$id]);
        }

        return $formaters_list;
    }

    /**
     * Call formater.
     *
     * If <var>$name</var> is a valid formater, it returns <var>$str</var>
     * transformed using that formater.
     *
     * @param   string  $id     The editor identifier (dcLegacyEditor, dcCKEditor, ...)
     * @param   string  $name   The formater name
     * @param   string  $str    The string to transform
     *
     * @return  string  The (formated) text
     */
    public function call(string $id, string $name, string $str): string
    {
        $backup_str = $str;

        if (isset($this->stack[$id]) && isset($this->stack[$id][$name])) {
            $str = call_user_func($this->stack[$id][$name], $str);

            return is_string($str) ? $str : $backup_str;
        }
        // Fallback with another editor if possible
        foreach ($this->stack as $editor => $formaters) {
            if (array_key_exists($name, $formaters)) {
                $str = call_user_func($this->stack[$editor][$name], $str);

                return is_string($str) ? $str : $backup_str;
            }
        }

        return $backup_str;
    }

    /**
     * Call legacy formater.
     *
     * If <var>$name</var> is a valid dcLegacyEditor formater, it returns
     * <var>$str</var> transformed using that formater.
     *
     * @param   string  $name   The name
     * @param   string  $str    The string
     *
     * @return  string  The (formated) text
     */
    public function callLegacy(string $name, string $str): string
    {
        return $this->call(self::LEGACY_FORMATER, $name, $str);
    }
}
