<?php

declare(strict_types=1);

namespace Neucore\Repository;

use Doctrine\ORM\EntityRepository;
use Neucore\Entity\CharacterNameChange;

/**
 * @method CharacterNameChange|null find($id, $lockMode = null, $lockVersion = null)
 * @method CharacterNameChange|null findOneBy(array $criteria, array $orderBy = null)
 * @method CharacterNameChange[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CharacterNameChangeRepository extends EntityRepository {}
