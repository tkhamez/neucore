<?php
namespace Tests\Unit\Core\Entity;

use Brave\Core\Entity\AppRepository;
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
