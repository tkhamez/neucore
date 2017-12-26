<?php
namespace Brave\Core\Resource;

use Doctrine\ORM\EntityManager;

abstract class AbstractResource
{
	/**
	 * @var EntityManager
	 */
	protected $entityManager = null;

	public function __construct(EntityManager $entityManager)
	{
		$this->entityManager = $entityManager;
	}
}
