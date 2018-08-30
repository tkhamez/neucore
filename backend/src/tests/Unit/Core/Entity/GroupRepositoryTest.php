<?php declare(strict_types=1);

namespace Tests\Unit\Core\Entity;

use Brave\Core\Repository\GroupRepository;
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
