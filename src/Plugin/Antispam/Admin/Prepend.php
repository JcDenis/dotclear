<?php
/**
 * @class Dotclear\Plugin\Antispam\Admin\Prepend
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginAntispam
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Antispam\Admin;

use ArrayObject;

use Dotclear\Database\Structure;
use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependAdmin;
use Dotclear\Plugin\Antispam\Admin\AntispamBehavior;
use Dotclear\Plugin\Antispam\Common\Antispam;
use Dotclear\Plugin\Antispam\Common\AntispamUrl;
use Dotclear\Plugin\Antispam\Common\Filter\FilterWords;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Prepend extends AbstractPrepend
{
    use TraitPrependAdmin;

    public function loadModule(): void
    {
        if (!defined('DC_ANTISPAM_CONF_SUPER')) {
            define('DC_ANTISPAM_CONF_SUPER', false);
        }

        # Menu and favs
        $this->addStandardMenu('Plugins');
        $this->addStandardFavorites('admin');

        new Antispam();
        new AntispamUrl();
        new AntispamBehavior();
    }

    public function installModule(): ?bool
    {
        $s = new Structure(dotclear()->con(), dotclear()->prefix);

        $s->comment
            ->comment_spam_status('varchar', 128, true, 0)
            ->comment_spam_filter('varchar', 32, true, null)
        ;

        $s->spamrule
            ->rule_id('bigint', 0, false)
            ->blog_id('varchar', 32, true)
            ->rule_type('varchar', 16, false, "'word'")
            ->rule_content('varchar', 128, false)

            ->primary('pk_spamrule', 'rule_id')
        ;

        $s->spamrule->index('idx_spamrule_blog_id', 'btree', 'blog_id');
        $s->spamrule->reference('fk_spamrule_blog', 'blog_id', 'blog', 'blog_id', 'cascade', 'cascade');

        if ($s->driver() == 'pgsql') {
            $s->spamrule->index('idx_spamrule_blog_id_null', 'btree', '(blog_id IS NULL)');
        }

        # Schema installation
        $si      = new Structure(dotclear()->con(), dotclear()->prefix);
        $changes = $si->synchronize($s);

        # Creating default wordslist
        if (dotclear()->version()->get('Antispam') === null) {
            $_o = new FilterWords();
            $_o->defaultWordsList();
            unset($_o);
        }

        dotclear()->blog()->settings()->antispam->put('antispam_moderation_ttl', 0, 'integer', 'Antispam Moderation TTL (days)', false);

        return true;
    }
}
