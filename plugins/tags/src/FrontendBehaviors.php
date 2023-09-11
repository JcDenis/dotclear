<?php
/**
 * @brief tags, a plugin for Dotclear 2
 *
 * @package Dotclear
 * @subpackage Plugins
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\tags;

use ArrayObject;
use Dotclear\App;
use Dotclear\Core\Frontend\Utility;
use Dotclear\Helper\File\Path;

class FrontendBehaviors
{
    /**
     * Public init
     */
    public static function publicPrepend(): void
    {
        // Localized string we find in template
        __("This tag's comments Atom feed");
        __("This tag's entries Atom feed");
    }

    /**
     * Prepare tags metadata if necessary (Entries or Comments blocks)
     *
     * @param      string       $block  The block
     * @param      ArrayObject  $attr   The attribute
     *
     * @return     string
     */
    public static function templateBeforeBlock(string $block, ArrayObject $attr): string
    {
        if (($block == 'Entries' || $block == 'Comments') && isset($attr['tag'])) {
            return
            "<?php\n" .
            "if (!isset(\$params)) { \$params = []; }\n" .
            "if (!isset(\$params['from'])) { \$params['from'] = ''; }\n" .
            "if (!isset(\$params['sql'])) { \$params['sql'] = ''; }\n" .
            "\$params['from'] .= ', '.App::con()->prefix().'meta META ';\n" .
            "\$params['sql'] .= 'AND META.post_id = P.post_id ';\n" .
            "\$params['sql'] .= \"AND META.meta_type = 'tag' \";\n" .
            "\$params['sql'] .= \"AND META.meta_id = '" . App::con()->escape($attr['tag']) . "' \";\n" .
                "?>\n";
        } elseif (empty($attr['no_context']) && ($block == 'Entries' || $block == 'Comments')) {
            return
                '<?php if (App::frontend()->ctx->exists("meta") && App::frontend()->ctx->meta->rows() && (App::frontend()->ctx->meta->meta_type == "tag")) { ' .
                "if (!isset(\$params)) { \$params = []; }\n" .
                "if (!isset(\$params['from'])) { \$params['from'] = ''; }\n" .
                "if (!isset(\$params['sql'])) { \$params['sql'] = ''; }\n" .
                "\$params['from'] .= ', '.App::con()->prefix().'meta META ';\n" .
                "\$params['sql'] .= 'AND META.post_id = P.post_id ';\n" .
                "\$params['sql'] .= \"AND META.meta_type = 'tag' \";\n" .
                "\$params['sql'] .= \"AND META.meta_id = '\".App::con()->escape(App::frontend()->ctx->meta->meta_id).\"' \";\n" .
                "} ?>\n";
        }

        return '';
    }

    /**
     * Adds tags tpl path.
     */
    public static function addTplPath(): void
    {
        $tplset           = App::themes()->moduleInfo(App::blog()->settings()->system->theme, 'tplset');
        $default_template = Path::real(My::path()) . DIRECTORY_SEPARATOR . Utility::TPL_ROOT . DIRECTORY_SEPARATOR;

        if (!empty($tplset) && is_dir($default_template . $tplset)) {
            App::frontend()->tpl->setPath(App::frontend()->tpl->getPath(), $default_template . $tplset);
        } else {
            App::frontend()->tpl->setPath(App::frontend()->tpl->getPath(), $default_template . App::config()->defaultTplset());
        }
    }
}
