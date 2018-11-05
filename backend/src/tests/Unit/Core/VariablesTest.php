<?php declare(strict_types=1);

namespace Tests\Unit\Core;

use Brave\Core\Variables;

class VariablesTest extends \PHPUnit\Framework\TestCase
{
    public function testSanitizeValueAllowCharacterDeletion()
    {
        $this->assertSame('0', Variables::sanitizeValue(Variables::ALLOW_CHARACTER_DELETION, '0'));
        $this->assertSame('1', Variables::sanitizeValue(Variables::ALLOW_CHARACTER_DELETION, '1'));
        $this->assertSame('1', Variables::sanitizeValue(Variables::ALLOW_CHARACTER_DELETION, 'some text'));
        $this->assertSame('0', Variables::sanitizeValue(Variables::ALLOW_CHARACTER_DELETION, ''));
    }

    public function testSanitizeValueGroupsRequireValidToken()
    {
        $this->assertSame('0', Variables::sanitizeValue(Variables::GROUPS_REQUIRE_VALID_TOKEN, '0'));
        $this->assertSame('1', Variables::sanitizeValue(Variables::GROUPS_REQUIRE_VALID_TOKEN, '1'));
        $this->assertSame('1', Variables::sanitizeValue(Variables::GROUPS_REQUIRE_VALID_TOKEN, 'some text'));
        $this->assertSame('0', Variables::sanitizeValue(Variables::GROUPS_REQUIRE_VALID_TOKEN, ''));
    }

    public function testSanitizeValueShowPreviewBanner()
    {
        $this->assertSame('0', Variables::sanitizeValue(Variables::SHOW_PREVIEW_BANNER, '0'));
        $this->assertSame('1', Variables::sanitizeValue(Variables::SHOW_PREVIEW_BANNER, '1'));
        $this->assertSame('1', Variables::sanitizeValue(Variables::SHOW_PREVIEW_BANNER, 'some text'));
        $this->assertSame('0', Variables::sanitizeValue(Variables::SHOW_PREVIEW_BANNER, ''));
    }
}
