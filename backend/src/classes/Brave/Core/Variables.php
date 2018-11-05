<?php declare(strict_types=1);

namespace Brave\Core;

/**
 * All system (and maybe user later) variables.
 */
class Variables
{
    /**
     * System settings variable, "0" or "1".
     *
     * Allow users to delete their character.
     */
    const ALLOW_CHARACTER_DELETION = 'allow_character_deletion';

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
     * System settings variable, "0" or "1"
     *
     * Shows or hides the "preview" banner on the Home screen.
     */
    const SHOW_PREVIEW_BANNER = 'show_preview_banner';

    public static function sanitizeValue(string $variableName, string $value): string
    {
        switch ($variableName) {
            case Variables::ALLOW_CHARACTER_DELETION:
            case Variables::GROUPS_REQUIRE_VALID_TOKEN:
            case Variables::SHOW_PREVIEW_BANNER:
                $value = ((bool) $value) ? '1' : '0';
                break;
        }

        return $value;
    }
}
