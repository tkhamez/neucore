<?php declare(strict_types=1);

namespace Tests\Unit\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Neucore\Entity\SystemVariable;
use Neucore\Factory\RepositoryFactory;
use Neucore\Repository\SystemVariableRepository;
use PHPUnit\Framework\TestCase;
use Tests\Helper;

class SystemVariableRepositoryTest extends TestCase
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var SystemVariableRepository
     */
    private $repository;

    protected function setUp(): void
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
