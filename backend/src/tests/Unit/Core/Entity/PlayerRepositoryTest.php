<?php

namespace Tests\Unit\Core\Entity;

use Brave\Core\Entity\PlayerRepository;
use Tests\Helper;

class PlayerRepositoryTest extends \PHPUnit\Framework\TestCase
{
    public function testConstruct()
    {
        $em = (new Helper())->getEm();
        $r = new PlayerRepository($em);
        $this->assertInstanceOf('Doctrine\ORM\EntityRepository', $r);
    }
}
