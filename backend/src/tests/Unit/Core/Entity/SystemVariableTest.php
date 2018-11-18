<?php declare(strict_types=1);

namespace Tests\Unit\Core\Entity;

use Brave\Core\Entity\SystemVariable;

class SystemVariableTest extends \PHPUnit\Framework\TestCase
{
    public function testJsonSerialize()
    {
        $var = new SystemVariable('nam');
        $var->setValue('val');

        $this->assertSame(
            ['name' => 'nam', 'value' => 'val'],
            json_decode(json_encode($var), true)
        );
    }

    public function testGetName()
    {
        $var = new SystemVariable('nam');
        $this->assertSame('nam', $var->getName());
    }

    public function testSetValueAllowCharacterDeletion()
    {
        $var = new SystemVariable(SystemVariable::ALLOW_CHARACTER_DELETION);
        $this->assertSame('0', $var->setValue('0')->getValue());
        $this->assertSame('1', $var->setValue('1')->getValue());
        $this->assertSame('1', $var->setValue('some text')->getValue());
        $this->assertSame('0', $var->setValue('')->getValue());
    }

    public function testSetValueGroupsRequireValidToken()
    {
        $var = new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN);
        $this->assertSame('0', $var->setValue('0')->getValue());
        $this->assertSame('1', $var->setValue('1')->getValue());
        $this->assertSame('1', $var->setValue('some text')->getValue());
        $this->assertSame('0', $var->setValue('')->getValue());
    }

    public function testSetValueShowPreviewBanner()
    {
        $var = new SystemVariable(SystemVariable::SHOW_PREVIEW_BANNER);
        $this->assertSame('0', $var->setValue('0')->getValue());
        $this->assertSame('1', $var->setValue('1')->getValue());
        $this->assertSame('1', $var->setValue('some text')->getValue());
        $this->assertSame('0', $var->setValue('')->getValue());
    }

    public function testSetValueMailAccountDisabledActive()
    {
        $var = new SystemVariable(SystemVariable::MAIL_ACCOUNT_DISABLED_ACTIVE);
        $this->assertSame('0', $var->setValue('0')->getValue());
        $this->assertSame('1', $var->setValue('1')->getValue());
        $this->assertSame('1', $var->setValue('some text')->getValue());
        $this->assertSame('0', $var->setValue('')->getValue());
    }

    public function testSetGetScope()
    {
        $var = new SystemVariable('nam');
        $this->assertSame($var, $var->setScope(SystemVariable::SCOPE_PUBLIC));
        $this->assertSame(SystemVariable::SCOPE_PUBLIC, $var->getScope());
    }
}
