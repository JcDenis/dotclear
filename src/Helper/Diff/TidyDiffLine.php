<?php

/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright AGPL-3.0
 */
declare(strict_types=1);

namespace Dotclear\Helper\Diff;

/**
 * @class TidyDiffLine
 * @brief TIDY diff line
 *
 * A diff line representation. Used by a TIDY chunk.
 */
class TidyDiffLine
{
    /**
     * Line type
     *
     * @var string  $type
     */
    public $type;

    /**
     * Line number for old and new context
     *
     * @var int[]   $lines
     */
    public $lines;

    /**
     * Line content
     *
     * @var string  $content
     */
    public $content;

    /**
     * Constructor
     *
     * Creates a line representation for a tidy chunk.
     *
     * @param string        $type        Tine type
     * @param int[]         $lines       Line number for old and new context
     * @param string        $content     Line content
     */
    public function __construct(string $type, ?array $lines, ?string $content)
    {
        $allowed_type = ['context', 'delete', 'insert'];

        if (in_array($type, $allowed_type) && is_array($lines) && is_string($content)) {
            $this->type    = $type;
            $this->lines   = $lines;
            $this->content = $content;
        }
    }

    /**
     * Magic get
     *
     * Returns field content according to the given name, null otherwise.
     *
     * @param string    $n            Field name
     */
    public function __get(string $n): mixed
    {
        return $this->{$n} ?? null;
    }

    /**
     * Overwrite
     *
     * Overwrites content for the current line.
     *
     * @param string    $content        Line content
     */
    public function overwrite(?string $content): void
    {
        if (is_string($content)) {
            $this->content = $content;
        }
    }
}
