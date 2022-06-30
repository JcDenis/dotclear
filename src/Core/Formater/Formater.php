<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Formater;

// Dotclear\Core\Formater\Formater
use Dotclear\App;

/**
 * Text formater methods.
 *
 * @ingroup  Core Text
 */
final class Formater
{
    /**
     * @var array<string,mixed> $formaters
     *                          formaters container
     */
    private $formaters = [];

    /**
     * Add editor formater.
     *
     * Adds a new text formater which will call the function <var>$callback</var> to
     * transform text. The function must be a valid callback and takes one
     * argument: the string to transform. It returns the transformed string.
     *
     * @param string   $editor   The editor identifier (LegacyEditor, CKEditor, ...)
     * @param string   $formater The formater name
     * @param callable $callback The function to use, must be a valid and callable callback
     */
    public function addEditorFormater(string $editor, string $formater, callable $callback): void
    {
        $this->formaters[$editor][$formater] = $callback;
    }

    /**
     * Get the editors list.
     *
     * @return array<string, string> The editors
     */
    public function getEditors(): array
    {
        $editors = [];

        foreach (array_keys($this->formaters) as $editor) {
            if (App::core()->plugins()->hasModule($editor)) {
                $editors[$editor] = App::core()->plugins()->getModule($editor)->name();
            }
        }

        return $editors;
    }

    /**
     * Get the formaters.
     *
     * return all formaters sorted by actives editors
     *
     * @return array<string,array> The formaters
     */
    public function getFormaters(): array
    {
        $formaters_list = [];
        foreach ($this->formaters as $editor => $formaters) {
            $formaters_list[$editor] = array_keys($formaters);
        }

        return $formaters_list;
    }

    /**
     * Get the editor formaters.
     *
     * return formaters for an editor if editor is active
     * return empty() array if editor is not active.
     * It can happens when a user choose an editor and admin deactivate that editor later
     *
     * @param string $editor The editor identifier (LegacyEditor, CKEditor, ...)
     *
     * @return array<int,string> The formaters
     */
    public function getEditorFormaters(string $editor = ''): array
    {
        $formaters_list = [];

        if (isset($this->formaters[$editor])) {
            $formaters_list = array_keys($this->formaters[$editor]);
        }

        return $formaters_list;
    }

    /**
     * Call editor formater. (format a string).
     *
     * If <b>$formater</b> is a valid formater, it returns <b>$str</b>
     * transformed using that formater.
     *
     * @param string $editor   The editor identifier (LegacyEditor, CKEditor, ...)
     * @param string $formater The formater name
     * @param string $str      The string to transform
     *
     * @return string The formated string
     */
    public function callEditorFormater(string $editor, string $formater, string $str): string
    {
        if (isset($this->formaters[$editor], $this->formaters[$editor][$formater])) {
            return call_user_func($this->formaters[$editor][$formater], $str);
        }

        // Fallback with another editor if possible
        foreach ($this->formaters as $editor => $formaters) {
            if (array_key_exists($formater, $formaters)) {
                return call_user_func($this->formaters[$editor][$formater], $str);
            }
        }

        return $str;
    }
}
