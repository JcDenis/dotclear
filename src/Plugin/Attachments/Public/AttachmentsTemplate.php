<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Attachments\Public;

// Dotclear\Plugin\Attachments\Public\AttachmentsTemplate
use Dotclear\App;
use Dotclear\Helper\Mapper\Strings;
use Dotclear\Process\Public\Template\TplAttr;

/**
 * Public templates for plugin Attachments.
 *
 * @ingroup  Plugin Attachments Template
 */
class AttachmentsTemplate
{
    // \cond
    // php tags break doxygen parser...
    private static $toff = ' ?>';
    private static $ton  = '<?php ';
    // \endcond

    public function __construct()
    {
        App::core()->template()->addBlock('Attachments', [$this, 'Attachments']);
        App::core()->template()->addBlock('AttachmentsHeader', [$this, 'AttachmentsHeader']);
        App::core()->template()->addBlock('AttachmentsFooter', [$this, 'AttachmentsFooter']);
        App::core()->template()->addValue('AttachmentMimeType', [$this, 'AttachmentMimeType']);
        App::core()->template()->addValue('AttachmentType', [$this, 'AttachmentType']);
        App::core()->template()->addValue('AttachmentFileName', [$this, 'AttachmentFileName']);
        App::core()->template()->addValue('AttachmentSize', [$this, 'AttachmentSize']);
        App::core()->template()->addValue('AttachmentTitle', [$this, 'AttachmentTitle']);
        App::core()->template()->addValue('AttachmentThumbnailURL', [$this, 'AttachmentThumbnailURL']);
        App::core()->template()->addValue('AttachmentURL', [$this, 'AttachmentURL']);
        App::core()->template()->addValue('MediaURL', [$this, 'MediaURL']);
        App::core()->template()->addBlock('AttachmentIf', [$this, 'AttachmentIf']);
        App::core()->template()->addValue('EntryAttachmentCount', [$this, 'EntryAttachmentCount']);

        App::core()->behavior('templateBeforeGetIfConditions')->add([$this, 'templateBeforeGetIfConditions']);
    }

    /*dtd
    <!ELEMENT tpl:Attachments - - -- Post Attachments loop -->
     */
    public function Attachments(TplAttr $attr, string $content): string
    {
        return self::$ton . "\n" .
            'if (App::core()->context()->get("posts") !== null) {' . "\n" .
            'App::core()->context()->set("attachments", new ArrayObject(App::core()->media()->getPostMedia(App::core()->context()->get("posts")->integer("post_id"),null,"attachment")));' . "\n" .
            "?>\n" .

            self::$ton . 'foreach (App::core()->context()->get("attachments") as $attach_i => $attach_f) : ' .
            '$GLOBALS[\'attach_i\'] = $attach_i; $GLOBALS[\'attach_f\'] = $attach_f;' .
            'App::core()->context()->set("file_url", $attach_f->file_url);' . self::$toff .
            $content .
            self::$ton . 'endforeach; App::core()->context()->set("attachments", null); unset($attach_i,$attach_f); App::core()->context()->set("file_url", null);' . self::$toff .

            self::$ton . '}' . self::$toff . "\n";
    }

    /*dtd
    <!ELEMENT tpl:AttachmentsHeader - - -- First attachments result container -->
     */
    public function AttachmentsHeader(TplAttr $attr, string $content): string
    {
        return
            self::$ton . 'if ($attach_i == 0) :' . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:AttachmentsFooter - - -- Last attachments result container -->
     */
    public function AttachmentsFooter(TplAttr $attr, string $content): string
    {
        return
            self::$ton . 'if ($attach_i+1 == count(App::core()->context()->get("attachments"))) :' . self::$toff .
            $content .
            self::$ton . 'endif;' . self::$toff;
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
    public function AttachmentIf(TplAttr $attr, string $content): string
    {
        $if = new Strings();

        if ($attr->isset('is_image')) {
            $if->add(((bool) $attr->get('is_image') ? '' : '!') . '$attach_f->media_image');
        }

        if ($attr->isset('has_thumb')) {
            $if->add(((bool) $attr->get('has_thumb') ? '' : '!') . 'isset($attach_f->media_thumb[\'sq\'])');
        }

        if ($attr->isset('is_mp3')) {
            $if->add('$attach_f->type ' . ((bool) $attr->get('is_mp3') ? '==' : '!=') . ' "audio/mpeg3"');
        }

        if ($attr->isset('is_flv')) {
            $if->add('$attach_f->type ' . ((bool) $attr->get('is_flv') ? '==' : '!=') . ' "video/x-flv"');
        }

        if ($attr->isset('is_audio')) {
            $if->add('$attach_f->type_prefix ' . ((bool) $attr->get('is_audio') ? '==' : '!=') . ' "audio"');
        }

        if ($attr->isset('is_video')) {
            // Since 2.15 .flv media are no more considered as video (Flash is obsolete)
            $sign = ((bool) $attr->get('is_video')) ? '==' : '!=';
            $test = '$attach_f->type_prefix ' . $sign . ' "video"';
            if ('==' == $sign) {
                $test .= ' && $attach_f->type != "video/x-flv"';
            } else {
                $test .= ' || $attach_f->type == "video/x-flv"';
            }
            $if->add($test);
        }

        if ($if->count()) {
            return self::$ton . 'if(' . implode(' ' . App::core()->template()->getOperator($attr->get('operator')) . ' ', $if->dump()) . ') :' . self::$toff . $content . self::$ton . 'endif;' . self::$toff;
        }

        return $content;
    }

    /*dtd
    <!ELEMENT tpl:AttachmentMimeType - O -- Attachment MIME Type -->
     */
    public function AttachmentMimeType(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf(App::core()->template()->getFilters($attr), '$attach_f->type') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:AttachmentType - O -- Attachment type -->
     */
    public function AttachmentType(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf(App::core()->template()->getFilters($attr), '$attach_f->media_type') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:AttachmentFileName - O -- Attachment file name -->
     */
    public function AttachmentFileName(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf(App::core()->template()->getFilters($attr), '$attach_f->basename') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:AttachmentSize - O -- Attachment size -->
    <!ATTLIST tpl:AttachmentSize
    full    CDATA    #IMPLIED    -- if set, size is rounded to a human-readable value (in KB, MB, GB, TB)
    >
     */
    public function AttachmentSize(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf(
            App::core()->template()->getFilters($attr),
            $attr->empty('full') ?
            'Dotclear\Helper\File\Files::size($attach_f->size)' :
            '$attach_f->size'
        ) . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:AttachmentTitle - O -- Attachment title -->
     */
    public function AttachmentTitle(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf(App::core()->template()->getFilters($attr), '$attach_f->media_title') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:AttachmentThumbnailURL - O -- Attachment square thumbnail URL -->
     */
    public function AttachmentThumbnailURL(TplAttr $attr): string
    {
        return
        self::$ton .
        'if (isset($attach_f->media_thumb[\'sq\'])) {' .
        'echo ' . sprintf(App::core()->template()->getFilters($attr), '$attach_f->media_thumb[\'sq\']') . ';' .
            '}' .
            '?>';
    }

    /*dtd
    <!ELEMENT tpl:AttachmentURL - O -- Attachment URL -->
     */
    public function AttachmentURL(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf(App::core()->template()->getFilters($attr), '$attach_f->file_url') . ';' . self::$toff;
    }

    public function MediaURL(TplAttr $attr): string
    {
        return self::$ton . 'echo ' . sprintf(App::core()->template()->getFilters($attr), 'App::core()->context()->get("file_url")') . ';' . self::$toff;
    }

    /*dtd
    <!ELEMENT tpl:EntryAttachmentCount - O -- Number of attachments for entry -->
    <!ATTLIST tpl:EntryAttachmentCount
    none    CDATA    #IMPLIED    -- text to display for "no attachments" (default: no attachments)
    one    CDATA    #IMPLIED    -- text to display for "one attachment" (default: one attachment)
    more    CDATA    #IMPLIED    -- text to display for "more attachment" (default: %s attachment, %s is replaced by the number of attachments)
    >
     */
    public function EntryAttachmentCount(TplAttr $attr): string
    {
        return App::core()->template()->displayCounter(
            'App::core()->context()->get("posts")->countMedia(\'attachment\')',
            [
                'none' => 'no attachments',
                'one'  => 'one attachment',
                'more' => '%d attachments',
            ],
            $attr,
            false
        );
    }

    public function templateBeforeGetIfConditions(string $tag, TplAttr $attr, string $content, Strings $if): void
    {
        if ('EntryIf' == $tag && $attr->isset('has_attachment')) {
            $if->add(((bool) $attr->get('has_attachment') ? '' : '!') . 'App::core()->context()->get("posts")->countMedia(\'attachment\')');
        }
    }
}
