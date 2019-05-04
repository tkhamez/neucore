<?php declare(strict_types=1);

namespace Neucore\Entity;

use Swagger\Annotations as SWG;
use Doctrine\ORM\Mapping as ORM;

/**
 * A system settings variable.
 *
 * @SWG\Definition(
 *     definition="SystemVariable",
 *     required={"name", "value"}
 * )
 * @ORM\Entity
 * @ORM\Table(name="system_variables")
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
     * Time, remain and reset from X-Esi-Error-Limit-* HTTP headers.
     *
     * Scope = backend
     */
    const ESI_ERROR_LIMIT = 'esi_error_limit';

    /**
     * The default theme.
     */
    const CUSTOMIZATION_DEFAULT_THEME = 'customization_default_theme';

    /**
     * Value for HTML head title tag.
     *
     * Scope = public
     */
    const CUSTOMIZATION_DOCUMENT_TITLE = 'customization_document_title';

    /**
     * URL for the links of the logos in the navigation bar and on the home page.
     *
     * Scope = public
     */
    const CUSTOMIZATION_WEBSITE = 'customization_website';

    /**
     * URL of GitHub repository for various links to the documentation.
     *
     * Scope = public
     */
    const CUSTOMIZATION_GITHUB = 'customization_github';

    /**
     * Organization name used in navigation bar.
     *
     * Scope = public
     */
    const CUSTOMIZATION_NAV_TITLE = 'customization_nav_title';

    /**
     * Organization logo used in navigation bar (Base64 encoded).
     *
     * Scope = public
     */
    const CUSTOMIZATION_NAV_LOGO = 'customization_nav_logo';

    /**
     * Headline on the home page.
     *
     * Scope = public
     */
    const CUSTOMIZATION_HOME_HEADLINE = 'customization_home_headline';

    /**
     * Text below the headline on the homepage.
     *
     * Scope = public
     */
    const CUSTOMIZATION_HOME_DESCRIPTION = 'customization_home_description';

    /**
     * Organization logo used on the home page (Base64 encoded).
     *
     * Scope = public
     */
    const CUSTOMIZATION_HOME_LOGO = 'customization_home_logo';

    /**
     * Text for the footer.
     *
     * Scope = public
     */
    const CUSTOMIZATION_FOOTER_TEXT = 'customization_footer_text';

    /**
     * Variable name.
     *
     * @SWG\Property(maxLength=255)
     * @ORM\Column(type="string", length=255)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     * @var string
     */
    private $name;

    /**
     * Variable value.
     *
     * @SWG\Property
     * @ORM\Column(type="text", length=16777215, nullable=true)
     * @var string
     */
    private $value;

    /**
     * @ORM\Column(type="string", length=16, options={"default" : "public"})
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
            case self::MAIL_ACCOUNT_DISABLED_BODY:
                $this->value = $value;
                break;
            case self::CUSTOMIZATION_HOME_LOGO:
            case self::CUSTOMIZATION_NAV_LOGO:
                if (preg_match('#^data:image/[a-z]+;base64,[a-zA-Z0-9+/]+={0,2}$#', $value)) {
                    $this->value = $value;
                }
                break;
            default:
                $this->value = trim(str_replace(["\r\n", "\n"], ' ', $value));
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
