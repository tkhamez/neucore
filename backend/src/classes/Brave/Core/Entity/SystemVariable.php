<?php declare(strict_types=1);

namespace Brave\Core\Entity;

/**
 * A system settings variable.
 *
 * @SWG\Definition(
 *     definition="SystemVariable",
 *     required={"name", "value"}
 * )
 * @Entity
 * @Table(name="system_variables")
 */
class SystemVariable implements \JsonSerializable
{
    /**
     * Public variables.
     */
    const SCOPE_PUBLIC = 'public';

    /**
     * Variables that are only visible on the settings page.
     */
    const SCOPE_SETTINGS = 'settings';

    /**
     * Variables that are not exposed to the frontend.
     */
    const SCOPE_BACKEND = 'backend';

    /**
     * System settings variable, "0" or "1".
     *
     * Allow users to delete their character.
     */
    const ALLOW_CHARACTER_DELETION = 'allow_character_deletion';

    /**
     * System settings variable, "0" or "1".
     *
     * Activates the login URL for managed accounts.
     */
    const ALLOW_LOGIN_MANAGED = 'allow_login_managed';

    /**
     * System settings variable, "0" or "1".
     *
     * 1: The API for application does not return groups for a player account
     *    if one or more of their characters has an invalid token.
     *
     * 0: ignore invalid tokens.
     */
    const GROUPS_REQUIRE_VALID_TOKEN = 'groups_require_valid_token';

    /**
     * How long the deactivation of the account will be delayed after a token became invalid.
     */
    const ACCOUNT_DEACTIVATION_DELAY = 'account_deactivation_delay';

    /**
     * System settings variable, "0" or "1"
     *
     * Shows or hides the "preview" banner on the Home screen.
     */
    const SHOW_PREVIEW_BANNER = 'show_preview_banner';

    /**
     * EVE character name for the character that can be used to send mails.
     */
    const MAIL_CHARACTER = 'mail_character';

    /**
     * ESI token to send mails.
     *
     * JSON with character ID, access token, expire time and refresh token.
     */
    const MAIL_TOKEN = 'mail_token';

    /**
     * Activate the "account disabled" EVE mail notification
     */
    const MAIL_ACCOUNT_DISABLED_ACTIVE = 'mail_account_disabled_active';

    /**
     * The "account disabled" EVE mail is only send to accounts that have a character in one of these alliances
     * (comma separated list of EVE alliance IDs).
     */
    const MAIL_ACCOUNT_DISABLED_ALLIANCES = 'mail_account_disabled_alliances';

    /**
     * Subject for "account disabled" EVE mail notification
     */
    const MAIL_ACCOUNT_DISABLED_SUBJECT = 'mail_account_disabled_subject';

    /**
     * Body for "account disabled" EVE mail notification
     */
    const MAIL_ACCOUNT_DISABLED_BODY = 'mail_account_disabled_body';

    /**
     * Character with director role for member tracking.
     *
     * This is the base name of this variable, the actual variable must have a number suffix
     * because there can be several director characters.
     *
     * Scope = settings
     */
    const DIRECTOR_CHAR = 'director_char_';

    /**
     * Tokens for DIRECTOR_CHAR
     *
     * Scope = backend
     */
    const DIRECTOR_TOKEN = 'director_token_';

    /**
     * Variable name.
     *
     * @SWG\Property(maxLength=255)
     * @Column(type="string", length=255)
     * @Id
     * @NONE
     * @var string
     */
    private $name;

    /**
     * Variable value.
     *
     * @SWG\Property
     * @Column(type="text", length=65535, nullable=true)
     * @var string
     */
    private $value;

    /**
     * @Column(type="string", length=16, options={"default" : "public"})
     * @var string
     */
    private $scope = self::SCOPE_PUBLIC;

    public function jsonSerialize()
    {
        return [
            'name' => $this->name,
            'value' => $this->value,
        ];
    }

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getValue(): string
    {
        return (string) $this->value;
    }

    public function setValue(string $value): SystemVariable
    {
        switch ($this->name) {
            case self::ALLOW_CHARACTER_DELETION:
            case self::ALLOW_LOGIN_MANAGED:
            case self::GROUPS_REQUIRE_VALID_TOKEN:
            case self::SHOW_PREVIEW_BANNER:
            case self::MAIL_ACCOUNT_DISABLED_ACTIVE:
                $this->value = ((bool) $value) ? '1' : '0';
                break;
            case self::ACCOUNT_DEACTIVATION_DELAY:
                $this->value = (string) abs((int) $value);
                break;
            case self::MAIL_ACCOUNT_DISABLED_ALLIANCES:
                $allianceIds = [];
                foreach (explode(',', $value) as $allianceId) {
                    if ((int) $allianceId > 0) {
                        $allianceIds[] = (int) $allianceId;
                    }
                }
                $this->value = implode(',', $allianceIds);
                break;
            default:
                $this->value = $value;
        }

        return $this;
    }

    public function getScope(): string
    {
        return $this->scope;
    }

    public function setScope(string $scope): SystemVariable
    {
        $this->scope = $scope;

        return $this;
    }
}
