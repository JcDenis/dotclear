<?php
/**
 * @class Dotclear\Plugin\Attachments\Public\Attachments
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

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class Attachments
{
    public function __construct()
    {
        dcCore()->tpl->addBlock('Attachments', [__CLASS__, 'Attachments']);
        dcCore()->tpl->addBlock('AttachmentsHeader', [__CLASS__, 'AttachmentsHeader']);
        dcCore()->tpl->addBlock('AttachmentsFooter', [__CLASS__, 'AttachmentsFooter']);
        dcCore()->tpl->addValue('AttachmentMimeType', [__CLASS__, 'AttachmentMimeType']);
        dcCore()->tpl->addValue('AttachmentType', [__CLASS__, 'AttachmentType']);
        dcCore()->tpl->addValue('AttachmentFileName', [__CLASS__, 'AttachmentFileName']);
        dcCore()->tpl->addValue('AttachmentSize', [__CLASS__, 'AttachmentSize']);
        dcCore()->tpl->addValue('AttachmentTitle', [__CLASS__, 'AttachmentTitle']);
        dcCore()->tpl->addValue('AttachmentThumbnailURL', [__CLASS__, 'AttachmentThumbnailURL']);
        dcCore()->tpl->addValue('AttachmentURL', [__CLASS__, 'AttachmentURL']);
        dcCore()->tpl->addValue('MediaURL', [__CLASS__, 'MediaURL']);
        dcCore()->tpl->addBlock('AttachmentIf', [__CLASS__, 'AttachmentIf']);
        dcCore()->tpl->addValue('EntryAttachmentCount', [__CLASS__, 'EntryAttachmentCount']);

        dcCore()->behaviors->add('tplIfConditions', [__CLASS__, 'tplIfConditions']);
    }

    /*dtd
    <!ELEMENT tpl:Attachments - - -- Post Attachments loop -->
     */
    public static function Attachments($attr, $content)
    {
        $res = "<?php\n" .
            'if (dcCore()->context->posts !== null && dcCore()->media) {' . "\n" .
            'dcCore()->context->attachments = new ArrayObject(dcCore()->media->getPostMedia(dcCore()->context->posts->post_id,null,"attachment"));' . "\n" .
            "?>\n" .

            '<?php foreach (dcCore()->context->attachments as $attach_i => $attach_f) : ' .
            '$GLOBALS[\'attach_i\'] = $attach_i; $GLOBALS[\'attach_f\'] = $attach_f;' .
            'dcCore()->context->file_url = $attach_f->file_url; ?>' .
            $content .
            '<?php endforeach; dcCore()->context->attachments = null; unset($attach_i,$attach_f,dcCore()->context->file_url); ?>' .

            "<?php } ?>\n";

        return $res;
    }

    /*dtd
    <!ELEMENT tpl:AttachmentsHeader - - -- First attachments result container -->
     */
    public static function AttachmentsHeader($attr, $content)
    {
        return
            '<?php if ($attach_i == 0) : ?>' .
            $content .
            '<?php endif; ?>';
    }

    /*dtd
    <!ELEMENT tpl:AttachmentsFooter - - -- Last attachments result container -->
     */
    public static function AttachmentsFooter($attr, $content)
    {
        return
            '<?php if ($attach_i+1 == count(dcCore()->context->attachments)) : ?>' .
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
    public static function AttachmentIf($attr, $content)
    {
        $if = [];

        $operator = isset($attr['operator']) ? dcCore()->tpl::getOperator($attr['operator']) : '&&';

        if (isset($attr['is_image'])) {
            $sign = (boolean) $attr['is_image'] ? '' : '!';
            $if[] = $sign . '$attach_f->media_image';
        }

        if (isset($attr['has_thumb'])) {
            $sign = (boolean) $attr['has_thumb'] ? '' : '!';
            $if[] = $sign . 'isset($attach_f->media_thumb[\'sq\'])';
        }

        if (isset($attr['is_mp3'])) {
            $sign = (boolean) $attr['is_mp3'] ? '==' : '!=';
            $if[] = '$attach_f->type ' . $sign . ' "audio/mpeg3"';
        }

        if (isset($attr['is_flv'])) {
            $sign = (boolean) $attr['is_flv'] ? '==' : '!=';
            $if[] = '$attach_f->type ' . $sign . ' "video/x-flv"';
        }

        if (isset($attr['is_audio'])) {
            $sign = (boolean) $attr['is_audio'] ? '==' : '!=';
            $if[] = '$attach_f->type_prefix ' . $sign . ' "audio"';
        }

        if (isset($attr['is_video'])) {
            // Since 2.15 .flv media are no more considered as video (Flash is obsolete)
            $sign = (boolean) $attr['is_video'] ? '==' : '!=';
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
    public static function AttachmentMimeType($attr)
    {
        $f = dcCore()->tpl->getFilters($attr);

        return '<?php echo ' . sprintf($f, '$attach_f->type') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:AttachmentType - O -- Attachment type -->
     */
    public static function AttachmentType($attr)
    {
        $f = dcCore()->tpl->getFilters($attr);

        return '<?php echo ' . sprintf($f, '$attach_f->media_type') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:AttachmentFileName - O -- Attachment file name -->
     */
    public static function AttachmentFileName($attr)
    {
        $f = dcCore()->tpl->getFilters($attr);

        return '<?php echo ' . sprintf($f, '$attach_f->basename') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:AttachmentSize - O -- Attachment size -->
    <!ATTLIST tpl:AttachmentSize
    full    CDATA    #IMPLIED    -- if set, size is rounded to a human-readable value (in KB, MB, GB, TB)
    >
     */
    public static function AttachmentSize($attr)
    {
        $f = dcCore()->tpl->getFilters($attr);
        if (!empty($attr['full'])) {
            return '<?php echo ' . sprintf($f, '$attach_f->size') . '; ?>';
        }

        return '<?php echo ' . sprintf($f, 'Dotclear\File\Files::size($attach_f->size)') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:AttachmentTitle - O -- Attachment title -->
     */
    public static function AttachmentTitle($attr)
    {
        $f = dcCore()->tpl->getFilters($attr);

        return '<?php echo ' . sprintf($f, '$attach_f->media_title') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:AttachmentThumbnailURL - O -- Attachment square thumbnail URL -->
     */
    public static function AttachmentThumbnailURL($attr)
    {
        $f = dcCore()->tpl->getFilters($attr);

        return
        '<?php ' .
        'if (isset($attach_f->media_thumb[\'sq\'])) {' .
        'echo ' . sprintf($f, '$attach_f->media_thumb[\'sq\']') . ';' .
            '}' .
            '?>';
    }

    /*dtd
    <!ELEMENT tpl:AttachmentURL - O -- Attachment URL -->
     */
    public static function AttachmentURL($attr)
    {
        $f = dcCore()->tpl->getFilters($attr);

        return '<?php echo ' . sprintf($f, '$attach_f->file_url') . '; ?>';
    }

    public static function MediaURL($attr)
    {
        $f = dcCore()->tpl->getFilters($attr);

        return '<?php echo ' . sprintf($f, 'dcCore()->context->file_url') . '; ?>';
    }

    /*dtd
    <!ELEMENT tpl:EntryAttachmentCount - O -- Number of attachments for entry -->
    <!ATTLIST tpl:EntryAttachmentCount
    none    CDATA    #IMPLIED    -- text to display for "no attachments" (default: no attachments)
    one    CDATA    #IMPLIED    -- text to display for "one attachment" (default: one attachment)
    more    CDATA    #IMPLIED    -- text to display for "more attachment" (default: %s attachment, %s is replaced by the number of attachments)
    >
     */
    public static function EntryAttachmentCount($attr)
    {
        return dcCore()->tpl->displayCounter(
            'dcCore()->context->posts->countMedia(\'attachment\')',
            [
                'none' => 'no attachments',
                'one'  => 'one attachment',
                'more' => '%d attachments'
            ],
            $attr,
            false
        );
    }

    public static function tplIfConditions($tag, $attr, $content, $if)
    {
        if ($tag == 'EntryIf' && isset($attr['has_attachment'])) {
            $sign = (boolean) $attr['has_attachment'] ? '' : '!';
            $if[] = $sign . 'dcCore()->context->posts->countMedia(\'attachment\')';
        }
    }
}
