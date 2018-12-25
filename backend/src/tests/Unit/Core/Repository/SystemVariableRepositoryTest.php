<?php declare(strict_types=1);

namespace Tests\Unit\Core\Repository;

use Brave\Core\Entity\SystemVariable;
use Brave\Core\Factory\RepositoryFactory;
use Brave\Core\Repository\SystemVariableRepository;
use Tests\Helper;

class SystemVariableRepositoryTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \Doctrine\ORM\EntityManagerInterface
     */
    private $em;

    /**
     * @var SystemVariableRepository
     */
    private $repository;

    public function setUp()
    {
        $helper = new Helper();
        $helper->emptyDb();
        $this->em = $helper->getEm();
        $this->repository = (new RepositoryFactory($this->em))->getSystemVariableRepository();
    }

    public function testGetDirectors()
    {
        $var1 = new SystemVariable(SystemVariable::DIRECTOR_CHAR . 1);
        $var2 = new SystemVariable(SystemVariable::DIRECTOR_CHAR . 2);
        $var3 = new SystemVariable(SystemVariable::DIRECTOR_TOKEN . 1);
        $this->em->persist($var1);
        $this->em->persist($var2);
        $this->em->persist($var3);
        $this->em->flush();

        $actual = $this->repository->getDirectors();
        $this->assertSame(2, count($actual));
        $this->assertSame(SystemVariable::DIRECTOR_CHAR . 1, $actual[0]->getName());
        $this->assertSame(SystemVariable::DIRECTOR_CHAR . 2, $actual[1]->getName());
    }
}
