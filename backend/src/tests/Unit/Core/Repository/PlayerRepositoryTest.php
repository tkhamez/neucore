<?php declare(strict_types=1);

namespace Tests\Unit\Core\Repository;

use Brave\Core\Entity\Player;
use Brave\Core\Repository\PlayerRepository;
use Tests\Helper;

class PlayerRepositoryTest extends \PHPUnit\Framework\TestCase
{
    public function testConstruct()
    {
        $em = (new Helper())->getEm();
        $r = new PlayerRepository($em);

        $this->assertInstanceOf('Doctrine\ORM\EntityRepository', $r);
        $this->assertSame(Player::class, $r->getClassName());
    }
}
