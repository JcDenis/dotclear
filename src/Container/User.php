<?php
/**
 * @class Dotclear\Container\User
 * @brief Dotclear simple User container
 *
 * @package Dotclear
 * @subpackage Container
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Container;

use Dotclear\Container\Common;

use Dotclear\Database\Record;

class User extends Common
{
    /** @var string   User id */
    protected $user_id = '';

    /** @var int        Is super admin */
    protected $user_super = 0;

    /** @var string     User password */
    protected $user_pwd = '';

    /** @var int        User can change password */
    protected $user_change_pwd = 0;

    /** @var string     User name */
    protected $user_name = '';

    /** @var string     User firstname */
    protected $user_firstname = '';

    /** @var string     User displayname (pseudo) */
    protected $user_displayname = '';

    /** @var string     User main email */
    protected $user_email = '';

    /** @var string     User URL */
    protected $user_url = '';

    /** @var string     User language */
    protected $user_lang = 'en';

    /** @var string     User timesone */
    protected $user_tz = 'Europe/London';

    /** @var string     User default post status */
    protected $user_post_status = '';

    /** @var string     User options */
    protected $user_options = [
        'edit_size'      => 24,
        'enable_wysiwyg' => true,
        'toolbar_bottom' => false,
        'editor'         => ['xhtml' => 'dcCKEditor', 'wiki' => 'dcLegacyEditor'],
        'post_format'    => 'xhtml',
    ];

    /** @var string     User other emails (comma separated list ) */
    protected $user_profile_mails = '';

    /** @var string     User other URLs (comma separated list ) */
    protected $user_profile_urls = '';

    public function fromRecord(Record $rs): User
    {
        if ($rs->exists('user_id')) {
            $this->setId($rs->user_id);
        }
        if ($rs->exists('user_super')) {
            $this->setSuper($rs->user_super);
        }
        if ($rs->exists('user_pwd')) {
            $this->setPwd($rs->user_pwd);
        }
        if ($rs->exists('user_change_pwd')) {
            $this->setChangePwd($rs->user_change_pwd);
        }
        if ($rs->exists('user_name')) {
            $this->setName($rs->user_name);
        }
        if ($rs->exists('user_firstname')) {
            $this->setFirstname($rs->user_firstname);
        }
        if ($rs->exists('user_displayname')) {
            $this->setDisplayname($rs->user_displayname);
        }
        if ($rs->exists('user_email')) {
            $this->setEmail($rs->user_email);
        }
        if ($rs->exists('user_url')) {
            $this->setURL($rs->user_url);
        }
        if ($rs->exists('user_lang')) {
            $this->setLang($rs->user_lang);
        }
        if ($rs->exists('user_tz')) {
            $this->setTZ($rs->user_tz);
        }
        if ($rs->exists('user_post_status')) {
            $this->setPostStatus($rs->user_post_status);
        }
        if ($rs->exists('user_options')) {
            $this->setOptions($rs->options());
        }

        return $this;
    }

    /**
     * Returns user default settings in an associative array with setting names in keys.
     *
     * @return  array   User default settings.
     */
    public static function defaultOptions(): array
    {
        return [
            'edit_size'      => 24,
            'enable_wysiwyg' => true,
            'toolbar_bottom' => false,
            'editor'         => ['xhtml' => 'dcCKEditor', 'wiki' => 'dcLegacyEditor'],
            'post_format'    => 'xhtml',
        ];
    }

    public static function checkId(mixed $arg, $strict = true): bool
    {
        return $strict ? is_string($arg) && preg_match('/^[A-Za-z0-9@._-]{2,}$/', $arg) : is_string($arg);
    }

    public static function toId(mixed $arg)
    {
        return (string) $arg;
    }

    public function setId(mixed $arg): string
    {
        $this->user_id = self::toId($arg);

        return $this->user_id;
    }

    public function getId(): string
    {
        return $this->user_id;
    }

    public static function checkSuper(mixed $arg, $strict = true): bool
    {
        return self::checkBinary($arg, $strict);
    }

    public static function toSuper(mixed $arg): int
    {
        return self::toBinary($arg);
    }

    public function setSuper(mixed $arg): int
    {
        $this->user_super = self::toBinary($arg);

        return $this->user_super;
    }

    public function getSuper(): int
    {
        return $this->user_super;
    }

    public static function checkPwd(mixed $arg, $strict = true): bool
    {
        return self::checkPassword($arg, $strict);
    }

    public static function toPwd(mixed $arg)
    {
        return self::toPassword($arg);
    }

    public function setPwd(mixed $arg): string
    {
        $this->user_pwd = self::toPassword($arg);

        return $this->user_pwd;
    }

    public function getPwd(): string
    {
        return $this->user_pwd;
    }

    public static function checkChangePwd(mixed $arg, $strict = true): bool
    {
        return self::checkBinary($arg, $strict);
    }

    public static function toChangePwd(mixed $arg): int
    {
        return self::toBinary($arg);
    }

    public function setChangePwd(mixed $arg): int
    {
        $this->user_change_pwd = self::toBinary($arg);

        return $this->user_change_pwd;
    }

    public function getChangePwd(): int
    {
        return $this->user_change_pwd;
    }

    public static function checkName(mixed $arg, $strict = true): bool
    {
        return self::checkString($arg, $strict);
    }

    public static function toName(mixed $arg): string
    {
        return slef::toString($arg);
    }

    public function setName(mixed $arg): string
    {
        $this->user_name = self::toString($arg);

        return $this->user_name;
    }

    public function getName(): string
    {
        return $this->user_name;
    }

    public static function checkFirstame(mixed $arg, $strict = true): bool
    {
        return self::chekString($arg, $strict);
    }

    public static function toFirstname(mixed $arg): string
    {
        return self::toString($arg);
    }

    public function setFirstname(mixed $arg): string
    {
        $this->user_firstname = self::toString($arg);

        return $this->user_firstname;
    }

    public function getFirstname(): string
    {
        return $this->user_firstname;
    }

    public static function checkDisplayname(mixed $arg, $strict = true): bool
    {
        return self::chekString($arg, $strict);
    }

    public static function toDisplayname(mixed $arg): string
    {
        return self::toString($arg);
    }

    public function setDisplayname(mixed $arg): string
    {
        $this->user_displayname = self::toString($arg);

        return $this->user_displayname;
    }

    public function getDisplayname(): string
    {
        return $this->user_displayname;
    }

    public function setEmail(mixed $arg): string
    {
        $this->user_email = self::toEmail($arg);

        return $this->user_email;
    }

    public function getEmail(): string
    {
        return $this->user_email;
    }

    public function setURL(mixed $arg): string
    {
        $this->user_url = self::toLang($arg);

        return $this->user_url;
    }

    public function getURL(): string
    {
        return $this->user_url;
    }

    public function setlang(mixed $arg): string
    {
        $this->user_lang = self::toLang($arg);

        return $this->user_lang;
    }

    public function getLang(): string
    {
        return $this->user_lang;
    }

    public function setTZ(mixed $arg): string
    {
        $this->user_tz = self::toTZ($arg);

        return $this->user_tz;
    }

    public function getTZ(): string
    {
        return $this->user_tz;
    }

    public static function checkPostStatus(mixed $arg, $strict = true): bool
    {
        return self::checkInteger($arg, $strict);
    }

    public static function toPostStatus(mixed $arg): int
    {
        return slef::toInteger($arg);
    }

    public function setPostStatus(mixed $arg): int
    {
        $this->user_post_status = self::toInteger($arg);

        return $this->user_post_status;
    }

    public function getPostStatus(): int
    {
        return $this->user_post_status;
    }

    public function setOptions(array $arg)
    {
        $this->user_options = array_merge($this->getOptions(), $arg);

        return $this->user_options;
    }

    public function getOptions(): array
    {
        return array_merge($this->defaultOptions(), $this->user_options);
    }

    public function setOption(string $key, mixed $val, ?string $type = null): mixed
    {
        $this->user_options = $this->setOptions([$key => $type ? self::toType($val, $type) : $val]);

        return $this->user_options[$key];
    }

    public function getOption(string $key): mixed
    {
        $this->user_options = $this->getOptions();

        if (isset($this->user_options[$key])) {
            return $this->user_options[$key];
        }

        return null;
    }

    public function getUserCN(): string
    {
        return Utils::getUserCN(
            $this->user_id,
            $this->user_name,
            $this->user_firstname,
            $this->user_displayname
        );
    }
}
