<?php
namespace Brave\Core\Entity;

/**
 * @Entity
 * @Table(name="users")
 */
class User
{

	/**
	 * @var integer
	 * @Id
	 * @Column(name="id", type="integer")
	 * @GeneratedValue(strategy="AUTO")
	 */
	public $id;

	/**
	 * @var string
	 * @Column(type="string", length=64)
	 */
	public $name;

}
