<?php declare(strict_types=1);

namespace Tests\Unit\Core\Entity;

use Brave\Core\Entity\AllianceRepository;
use Tests\Helper;

class AllianceRepositoryTest extends \PHPUnit\Framework\TestCase
{
    public function testConstruct()
    {
        $em = (new Helper())->getEm();
        $r = new AllianceRepository($em);
        $this->assertInstanceOf('Doctrine\ORM\EntityRepository', $r);
    }
}
