<?php

declare(strict_types=1);

namespace Tests\Unit\Entity;

use Neucore\Entity\SystemVariable;
use PHPUnit\Framework\TestCase;

class SystemVariableTest extends TestCase
{
    public function testJsonSerialize()
    {
        $var = new SystemVariable('nam');
        $var->setValue('val');

        $this->assertSame(
            ['name' => 'nam', 'value' => 'val'],
            json_decode((string) json_encode($var), true)
        );
    }

    public function testGetName()
    {
        $var = new SystemVariable('nam');
        $this->assertSame('nam', $var->getName());
    }

    public function testSetValue()
    {
        // testing a variable that has no validation
        $var = new SystemVariable(SystemVariable::MAIL_TOKEN);
        $this->assertSame('abc', $var->setValue('abc')->getValue());
    }

    public function testSetValue_AllowCharacterDeletion()
    {
        $var = new SystemVariable(SystemVariable::ALLOW_CHARACTER_DELETION);
        $this->assertSame('0', $var->setValue('0')->getValue());
        $this->assertSame('1', $var->setValue('1')->getValue());
        $this->assertSame('1', $var->setValue('some text')->getValue());
        $this->assertSame('0', $var->setValue('')->getValue());

        $var2 = new SystemVariable(SystemVariable::ALLOW_LOGIN_MANAGED);
        $this->assertSame('1', $var2->setValue('some text')->getValue());

        $var3 = new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN);
        $this->assertSame('0', $var3->setValue('')->getValue());

        $var4 = new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_ACTIVE);
        $this->assertSame('1', $var4->setValue('some text')->getValue());

        $var5 = new SystemVariable(SystemVariable::MAIL_MISSING_CHARACTER_ACTIVE);
        $this->assertSame('0', $var5->setValue('')->getValue());

        $var6 = new SystemVariable(SystemVariable::RATE_LIMIT_APP_ACTIVE);
        $this->assertSame('1', $var6->setValue('some text')->getValue());
    }

    public function testSetValue_AccountDeactivationDelay()
    {
        $var = new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_DELAY);
        $this->assertSame('0', $var->setValue('abc')->getValue());
        $this->assertSame('10', $var->setValue('-10')->getValue());
        $this->assertSame('10', $var->setValue('10')->getValue());
        $this->assertSame('0', $var->setValue('')->getValue());

        $var2 = new SystemVariable(SystemVariable::MAIL_MISSING_CHARACTER_RESEND);
        $this->assertSame('0', $var2->setValue('abc')->getValue());

        $var3 = new SystemVariable(SystemVariable::RATE_LIMIT_APP_RESET_TIME);
        $this->assertSame('10', $var3->setValue('-10')->getValue());

        $var4 = new SystemVariable(SystemVariable::RATE_LIMIT_APP_MAX_REQUESTS);
        $this->assertSame('0', $var4->setValue('')->getValue());
    }

    public function testSetValue_MailInvalidTokenAlliances()
    {
        $var = new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_ALLIANCES);
        $this->assertSame('123,456', $var->setValue(' 123 , 456 , abc, ')->getValue());

        $var2 = new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_CORPORATIONS);
        $this->assertSame('123,456', $var2->setValue(' 123 , 456 , abc, ')->getValue());

        $var3 = new SystemVariable(SystemVariable::MAIL_MISSING_CHARACTER_CORPORATIONS);
        $this->assertSame('123,456', $var3->setValue(' 123 , 456 , abc, ')->getValue());
    }

    public function testSetValue_MultilineText()
    {
        $var1 = new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_BODY);
        $this->assertSame(" Multiline \ntext. ", $var1->setValue(" Multiline \ntext. ")->getValue());

        $var2 = new SystemVariable(SystemVariable::MAIL_MISSING_CHARACTER_BODY);
        $this->assertSame("Other multiline \ntext.", $var2->setValue("Other multiline \ntext.")->getValue());

        $var3 = new SystemVariable(SystemVariable::CUSTOMIZATION_LOGIN_TEXT);
        $this->assertSame("\nmore multi-\nlines\n", $var3->setValue("\nmore multi-\nlines\n")->getValue());

        $var4 = new SystemVariable(SystemVariable::CUSTOMIZATION_HOME_MARKDOWN);
        $this->assertSame(" Test\ntext\n ", $var4->setValue(" Test\ntext\n ")->getValue());
    }

    public function testSetValue_Images()
    {
        $var1 = new SystemVariable(SystemVariable::CUSTOMIZATION_HOME_LOGO);
        $this->assertSame('', $var1->setValue('abc')->getValue());
        $this->assertSame('', $var1->setValue('data:text/plain;base64,T3==')->getValue());
        $this->assertSame('data:image/png;base64,T/3+a=', $var1->setValue('data:image/png;base64,T/3+a=')->getValue());

        $var2 = new SystemVariable(SystemVariable::CUSTOMIZATION_NAV_LOGO);
        $this->assertSame('', $var2->setValue('abc')->getValue());
        $this->assertSame('', $var2->setValue('data:text/plain;base64,T3==')->getValue());
        $this->assertSame('data:image/png;base64,T/3+a=', $var2->setValue('data:image/png;base64,T/3+a=')->getValue());
    }

    public function testSetValue_MailInvalidTokenSubject()
    {
        // This is the default validation, single line text, so no extra test for others like this.

        $var = new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_SUBJECT);
        $this->assertSame('Test this line', $var->setValue(" Test\nthis\r\nline ")->getValue());
    }

    public function testSetGetScope()
    {
        $var = new SystemVariable('nam');
        $this->assertSame($var, $var->setScope(SystemVariable::SCOPE_PUBLIC));
        $this->assertSame(SystemVariable::SCOPE_PUBLIC, $var->getScope());
    }
}
