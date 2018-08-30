<?php declare(strict_types=1);

namespace Tests\Unit\Core\Repository;

use Brave\Core\Repository\AppRepository;
use Tests\Helper;

class AppRepositoryTest extends \PHPUnit\Framework\TestCase
{
    public function testConstruct()
    {
        $em = (new Helper())->getEm();
        $r = new AppRepository($em);
        $this->assertInstanceOf('Doctrine\ORM\EntityRepository', $r);
    }
}
