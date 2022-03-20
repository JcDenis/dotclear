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

use Dotclear\Container\TraitContainer;

use Dotclear\Database\Record;

class User
{
    use TraitContainer;

    /** @var array  User info */
    protected $user_info = [
        'user_id'          => '',
        'user_super'       => 0,
        'user_pwd'         => '',
        'user_change_pwd'  => 0,
        'user_name'        => '',
        'user_firstname'   => '',
        'user_displayname' => '',
        'user_email'       => '',
        'user_url'         => '',
        'user_lang'        => 'en',
        'user_tz'          => 'Europe/London',
        'user_post_status' => -2,
        'user_creadt'      => '',
        'user_upddt'       => '',
    ];

    /** @var string     User options */
    protected $user_options = [
        'edit_size'      => 24,
        'enable_wysiwyg' => true,
        'toolbar_bottom' => false,
        'editor'         => ['xhtml' => 'dcCKEditor', 'wiki' => 'LegacyEditor'],
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
        if ($rs->exists('user_creadt')) {
            $this->user_info['user_creadt'];
        }
        if ($rs->exists('user_upddt')) {
            $this->user_info['user_upddt'];
        }
        if ($rs->exists('user_options')) {
            $this->setOptions($rs->options());
        }

        $this->setCN();

        return $this;
    }

    /**
     * Static function that returns user's common name given to his
     * <var>user_id</var>, <var>user_name</var>, <var>user_firstname</var> and
     * <var>user_displayname</var>.
     *
     * @param      string       $user_id           The user identifier
     * @param      string|null  $user_name         The user name
     * @param      string|null  $user_firstname    The user firstname
     * @param      string|null  $user_displayname  The user displayname
     *
     * @return     string  The user cn.
     */
    public static function getUserCN(string $user_id, ?string $user_name, ?string $user_firstname, ?string $user_displayname): string
    {
        if (!empty($user_displayname)) {
            return $user_displayname;
        }

        if (!empty($user_name)) {
            if (!empty($user_firstname)) {
                return $user_firstname . ' ' . $user_name;
            }

            return $user_name;
        } elseif (!empty($user_firstname)) {
            return $user_firstname;
        }

        return $user_id;
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
            'editor'         => ['xhtml' => 'dcCKEditor', 'wiki' => 'LegacyEditor'],
            'post_format'    => 'xhtml',
        ];
    }

    public function getInfo(string $key): mixed
    {
        return isset($this->user_info[$key]) ? $this->user_info[$key] : null;
    }

    public static function isId(mixed $arg, $strict = true): bool
    {
        return $strict ? is_string($arg) && preg_match('/^[A-Za-z0-9@._-]{2,}$/', $arg) : is_string($arg);
    }

    public static function toId(mixed $arg)
    {
        return (string) $arg;
    }

    public function setId(mixed $arg): string
    {
        $this->user_info['user_id'] = self::toId($arg);

        return $this->user_info['user_id'];
    }

    public function getId(): string
    {
        return $this->user_info['user_id'];
    }

    public static function isSuper(mixed $arg, $strict = true): bool
    {
        return self::isBinary($arg, $strict);
    }

    public static function toSuper(mixed $arg): int
    {
        return self::toBinary($arg);
    }

    public function setSuper(mixed $arg): int
    {
        $this->user_info['user_super'] = self::toBinary($arg);

        return $this->user_info['user_super'];
    }

    public function getSuper(): int
    {
        return $this->user_info['user_super'];
    }

    public static function isPwd(mixed $arg, $strict = true): bool
    {
        return self::isPassword($arg, $strict);
    }

    public static function toPwd(mixed $arg)
    {
        return self::toPassword($arg);
    }

    public function setPwd(mixed $arg): string
    {
        $this->user_info['user_pwd'] = self::toPassword($arg);

        return $this->user_info['user_pwd'];
    }

    public function getPwd(): string
    {
        return $this->user_info['user_pwd'];
    }

    public static function isChangePwd(mixed $arg, $strict = true): bool
    {
        return self::isBinary($arg, $strict);
    }

    public static function toChangePwd(mixed $arg): int
    {
        return self::toBinary($arg);
    }

    public function setChangePwd(mixed $arg): int
    {
        $this->user_info['user_change_pwd'] = self::toBinary($arg);

        return $this->user_info['user_change_pwd'];
    }

    public function getChangePwd(): int
    {
        return $this->user_info['user_change_pwd'];
    }

    public static function isName(mixed $arg, $strict = true): bool
    {
        return self::isString($arg, $strict);
    }

    public static function toName(mixed $arg): string
    {
        return slef::toString($arg);
    }

    public function setName(mixed $arg): string
    {
        $this->user_info['user_name'] = self::toString($arg);

        return $this->user_info['user_name'];
    }

    public function getName(): string
    {
        return $this->user_info['user_name'];
    }

    public static function isFirstame(mixed $arg, $strict = true): bool
    {
        return self::chekString($arg, $strict);
    }

    public static function toFirstname(mixed $arg): string
    {
        return self::toString($arg);
    }

    public function setFirstname(mixed $arg): string
    {
        $this->user_info['user_firstname'] = self::toString($arg);

        return $this->user_info['user_firstname'];
    }

    public function getFirstname(): string
    {
        return $this->user_info['user_firstname'];
    }

    public static function isDisplayname(mixed $arg, $strict = true): bool
    {
        return self::chekString($arg, $strict);
    }

    public static function toDisplayname(mixed $arg): string
    {
        return self::toString($arg);
    }

    public function setDisplayname(mixed $arg): string
    {
        $this->user_info['user_displayname'] = self::toString($arg);

        return $this->user_info['user_displayname'];
    }

    public function getDisplayname(): string
    {
        return $this->user_info['user_displayname'];
    }

    public function setEmail(mixed $arg): string
    {
        $this->user_info['user_email'] = self::toEmail($arg);

        return $this->user_info['user_email'];
    }

    public function getEmail(): string
    {
        return $this->user_info['user_email'];
    }

    public function setURL(mixed $arg): string
    {
        $this->user_info['user_url'] = self::toLang($arg);

        return $this->user_info['user_url'];
    }

    public function getURL(): string
    {
        return $this->user_info['user_url'];
    }

    public function setlang(mixed $arg): string
    {
        $this->user_info['user_lang'] = self::toLang($arg);

        return $this->user_info['user_lang'];
    }

    public function getLang(): string
    {
        return $this->user_info['user_lang'];
    }

    public function setTZ(mixed $arg): string
    {
        $this->user_info['user_tz'] = self::toTZ($arg);

        return $this->user_info['user_tz'];
    }

    public function getTZ(): string
    {
        return $this->user_info['user_tz'];
    }

    public static function isPostStatus(mixed $arg, $strict = true): bool
    {
        return self::isInteger($arg, $strict);
    }

    public static function toPostStatus(mixed $arg): int
    {
        return slef::toInteger($arg);
    }

    public function setPostStatus(mixed $arg): int
    {
        $this->user_info['user_post_status'] = self::toInteger($arg);

        return $this->user_info['user_post_status'];
    }

    public function getPostStatus(): int
    {
        return $this->user_info['user_post_status'];
    }

    public function getCreadt(): string
    {
        return $this->user_info['user_creadt'];
    }

    public function getUpddt(): string
    {
        return $this->user_info['user_upddt'];
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

    public function getOption(string $key, ?string $type = null): mixed
    {
        $this->user_options = $this->getOptions();

        return isset($this->user_options[$key]) ? self::toType($this->user_options[$key], $type) : null;
    }

    public function setCN(): string
    {
        $this->user_info['user_cn'] = self::getUserCN(
            $this->user_info['user_id'],
            $this->user_info['user_name'],
            $this->user_info['user_firstname'],
            $this->user_info['user_displayname']
        );

        return $this->user_info['user_cn'];
    }

    public function getCN(): string
    {
        return $this->user_info['user_cn'] ?? $this->getCN();
    }
}
