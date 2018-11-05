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

    public function testSetGetValue()
    {
        $var = new SystemVariable('nam');
        $this->assertSame($var, $var->setValue('val'));
        $this->assertSame('val', $var->getValue());
    }
}
