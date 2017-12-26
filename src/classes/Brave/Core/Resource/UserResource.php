<?php
namespace Brave\Core\Resource;

use Brave\Core\Entity\User;

class UserResource extends AbstractResource
{

	/**
	 * @param int $id
	 * @return User|null
	 */
	public function get($id)
	{
		return $this->entityManager->find('Brave\Core\Entity\User', $id);
	}
}
