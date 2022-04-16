<?php
/**
 * @class Dotclear\Plugin\Attachments\Public\AttachmentsTemplate
 * @brief Dotclear Plugin class
 *
 * @package Dotclear
 * @subpackage PluginAttachments
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Attachments\Public;

use ArrayObject;

class AttachmentsTemplate
{
    public function __construct()
    {
        dotclear()->template()->addBlock('Attachments', [$this, 'Attachments']);
        dotclear()->template()->addBlock('AttachmentsHeader', [$this, 'AttachmentsHeader']);
        dotclear()->template()->addBlock('AttachmentsFooter', [$this, 'AttachmentsFooter']);
        dotclear()->template()->addValue('AttachmentMimeType', [$this, 'AttachmentMimeType']);
        dotclear()->template()->addValue('AttachmentType', [$this, 'AttachmentType']);
        dotclear()->template()->addValue('AttachmentFileName', [$this, 'AttachmentFileName']);
        dotclear()->template()->addValue('AttachmentSize', [$this, 'AttachmentSize']);
        dotclear()->template()->addValue('AttachmentTitle', [$this, 'AttachmentTitle']);
        dotclear()->template()->addValue('AttachmentThumbnailURL', [$this, 'AttachmentThumbnailURL']);
        dotclear()->template()->addValue('AttachmentURL', [$this, 'AttachmentURL']);
        dotclear()->template()->addValue('MediaURL', [$this, 'MediaURL']);
        dotclear()->template()->addBlock('AttachmentIf', [$this, 'AttachmentIf']);
        dotclear()->template()->addValue('EntryAttachmentCount', [$this, 'EntryAttachmentCount']);

        dotclear()->behavior()->add('tplIfConditions', [$this, 'tplIfConditions']);
    }

    /*dtd
    <!ELEMENT tpl:Attachments - - -- Post Attachments loop -->
     */
    public function Attachments(ArrayObject $attr, string $content): string
    {
        $res = "<?php\n" .
            'if (dotclear()->context()->get("posts") !== null) {' . "\n" .
            'dotclear()->context()->set("attachments", new ArrayObject(dotclear()->media()->getPostMedia(dotclear()->context()->get("posts")->fInt("post_id"),null,"attachment")));' . "\n" .
            "?>\n" .

            '<?php foreach (dotclear()->context()->get("attachments") as $attach_i => $attach_f) : ' .
            '$GLOBALS[\'attach_i\'] = $attach_i; $GLOBALS[\'attach_f\'] = $attach_f;' .
            'dotclear()->context()->set("file_url", $attach_f->file_url); ?>' .
            $content .
            '<?php endforeach; dotclear()->context()->set("attachments", null); unset($attach_i,$attach_f); dotclear()->context()->set("file_url", null); ?>' .

            "<?php } ?>\n";

        return $res;
    }

    /*dtd
    <!ELEMENT tpl:AttachmentsHeader - - -- First attachments result container -->
     */
    public function AttachmentsHeader(ArrayObject $attr, string $content): string
    {
        return
            '<?php if ($attach_i == 0) : ?>' .
            $content .
            '<?php endif; ?>';
    }

    /*dtd
    <!ELEMENT tpl:AttachmentsFooter - - -- Last attachments result container -->
     */
    public function AttachmentsFooter(ArrayObject $attr, string $content): string
    {
        return
            '<?php if ($attach_i+1 == count(dotclear()->context()->get("attachments"))) : ?>' .
            $content .
            '<?php endif; ?>';
    }

    /*dtd
    <!ELEMENT tpl:AttachmentsIf - - -- Test on attachment fields -->
    <!ATTLIST tpl:AttachmentIf
    is_image    (0|1)    #IMPLIED    -- test if attachment is an image (value : 1) or not (value : 0)
    has_thumb    (0|1)    #IMPLIED    -- test if attachment has a square thumnail (value : 1) or not (value : 0)
    is_mp3    (0|1)    #IMPLIED    -- test if attachment is a mp3 file (value : 1) or not (value : 0)
    is_flv    (0|1)    #IMPLIED    -- test if attachment is a flv file (value : 1) or not (value : 0)
    is_audio    (0|1)    #IMPLIED    -- test if attachment is an audio file (value : 1) or not (value : 0)
    is_video    (0|1)    #IMPLIED    -- test if attachment is a video file (value : 1) or not (value : 0)
    >
     */
    public function AttachmentIf(ArrayObject $attr, string $content): string
    {
        $if = [];

        $operator = isset($attr['operator']) ? dotclear()->template()->getOperator($attr['operator']) : '&&';

        if (isset($attr['is_image'])) {
            $sign = (bool) $attr['is_image'] ? '' : '!';
            $if[] = $sign . '$attach_f->media_image';
        }

        if (isset($attr['has_thumb'])) {
            $sign = (bool) $attr['has_thumb'] ? '' : '!';
            $if[] = $sign . 'isset($attach_f->media_thumb[\'sq\'])';
        }

        if (isset($attr['is_mp3'])) {
            $sign = (bool) $attr['is_mp3'] ? '==' : '!=';
            $if[] = '$attach_f->type ' . $sign . ' "audio/mpeg3"';
        }

        if (isset($attr['is_flv'])) {
            $sign = (bool) $attr['is_flv'] ? '==' : '!=';
            $if[] = '$attach_f->type ' . $sign . ' "video/x-flv"';
        }

        if (isset($attr['is_audio'])) {
            $sign = (bool) $attr['is_audio'] ? '==' : '!=';
            $if[] = '$attach_f->type_prefix ' . $sign . ' "audio"';
        }

        if (isset($attr['is_video'])) {
            // Since 2.15 .flv media are no more considered as video (Flash is obsolete)
            $sign = (bool) $attr['is_video'] ? '==' : '!=';
            $test = '$attach_f->type_prefix ' . $sign . ' "video"';
            if ($sign == '==') {
                $test .= ' && $attach_f->type != "video/x-flv"';
            } else {
                $test .= ' || $attach_f->type == "video/x-flv"';
            }
            $if[] = $test;
        }

        if (count($if) != 0) {
            return '<?php if(' . implode(' ' . $operator . ' ', (array) $if) . ') : ?>' . $content . '<?php endif; ?>';
        }

        return $content;
    }

    /*dtd
    <!ELEMENT tpl:AttachmentMimeType - O -- Attachment MIME Type -->
     */
    public function AttachmentMimeType(ArrayObject $attr): string
    {
        return '<?php echo ' . sprintf(dotclear()->template()->getFilters($attr), '$attach_f->type') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:AttachmentType - O -- Attachment type -->
     */
    public function AttachmentType(ArrayObject $attr): string
    {
        return '<?php echo ' . sprintf(dotclear()->template()->getFilters($attr), '$attach_f->media_type') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:AttachmentFileName - O -- Attachment file name -->
     */
    public function AttachmentFileName(ArrayObject $attr): string
    {
        return '<?php echo ' . sprintf(dotclear()->template()->getFilters($attr), '$attach_f->basename') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:AttachmentSize - O -- Attachment size -->
    <!ATTLIST tpl:AttachmentSize
    full    CDATA    #IMPLIED    -- if set, size is rounded to a human-readable value (in KB, MB, GB, TB)
    >
     */
    public function AttachmentSize(ArrayObject $attr): string
    {
        return '<?php echo ' . sprintf(dotclear()->template()->getFilters($attr), empty($attr['full']) ?
            'Dotclear\Helper\File\Files::size($attach_f->size)' :
            '$attach_f->size'
        ) . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:AttachmentTitle - O -- Attachment title -->
     */
    public function AttachmentTitle(ArrayObject $attr): string
    {
        return '<?php echo ' . sprintf(dotclear()->template()->getFilters($attr), '$attach_f->media_title') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:AttachmentThumbnailURL - O -- Attachment square thumbnail URL -->
     */
    public function AttachmentThumbnailURL(ArrayObject $attr): string
    {
        return
        '<?php ' .
        'if (isset($attach_f->media_thumb[\'sq\'])) {' .
        'echo ' . sprintf(dotclear()->template()->getFilters($attr), '$attach_f->media_thumb[\'sq\']') . ';' .
            '}' .
            '?>';
    }

    /*dtd
    <!ELEMENT tpl:AttachmentURL - O -- Attachment URL -->
     */
    public function AttachmentURL(ArrayObject $attr): string
    {
        return '<?php echo ' . sprintf(dotclear()->template()->getFilters($attr), '$attach_f->file_url') . '; ?>';
    }

    public function MediaURL(ArrayObject $attr): string
    {
        return '<?php echo ' . sprintf(dotclear()->template()->getFilters($attr), 'dotclear()->context()->get("file_url")') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:EntryAttachmentCount - O -- Number of attachments for entry -->
    <!ATTLIST tpl:EntryAttachmentCount
    none    CDATA    #IMPLIED    -- text to display for "no attachments" (default: no attachments)
    one    CDATA    #IMPLIED    -- text to display for "one attachment" (default: one attachment)
    more    CDATA    #IMPLIED    -- text to display for "more attachment" (default: %s attachment, %s is replaced by the number of attachments)
    >
     */
    public function EntryAttachmentCount(ArrayObject $attr): string
    {
        return dotclear()->template()->displayCounter(
            'dotclear()->context()->get("posts")->countMedia(\'attachment\')',
            [
                'none' => 'no attachments',
                'one'  => 'one attachment',
                'more' => '%d attachments'
            ],
            $attr,
            false
        );
    }

    public function tplIfConditions(string $tag, ArrayObject $attr, string $content, ArrayObject $if): void
    {
        if ('EntryIf' == $tag && isset($attr['has_attachment'])) {
            $sign = (bool) $attr['has_attachment'] ? '' : '!';
            $if[] = $sign . 'dotclear()->context()->get("posts")->countMedia(\'attachment\')';
        }
    }
}
