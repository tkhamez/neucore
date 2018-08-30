<?php declare(strict_types=1);

namespace Tests\Unit\Core\Repository;

use Brave\Core\Entity\Role;
use Brave\Core\Repository\RoleRepository;
use Tests\Helper;

class RoleRepositoryTest extends \PHPUnit\Framework\TestCase
{
    public function testConstruct()
    {
        $em = (new Helper())->getEm();
        $r = new RoleRepository($em);

        $this->assertInstanceOf('Doctrine\ORM\EntityRepository', $r);
        $this->assertSame(Role::class, $r->getClassName());
    }
}
