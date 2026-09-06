<?php

declare(strict_types=1);

namespace Neucore\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Neucore\Entity\McpToken;

/**
 * @extends EntityRepository<McpToken>
 */
class McpTokenRepository extends EntityRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        $class = new ClassMetadata(McpToken::class);
        parent::__construct($em, $class);
    }

    public function findById(int $id): ?McpToken
    {
        /** @var McpToken|null */
        return $this->find($id);
    }

    public function findByName(string $name): ?McpToken
    {
        /** @var McpToken|null */
        return $this->findOneBy(['name' => $name]);
    }
}
