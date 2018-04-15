<?php
namespace Tests\Unit\Core\Entity;

use Brave\Core\Entity\GroupRepository;
use Tests\Helper;

class GroupRepositoryTest extends \PHPUnit\Framework\TestCase
{
    public function testConstruct()
    {
        $em = (new Helper())->getEm();
        $r = new GroupRepository($em);
        $this->assertInstanceOf('Doctrine\ORM\EntityRepository', $r);
    }
}
