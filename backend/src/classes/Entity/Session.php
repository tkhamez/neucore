<?php declare(strict_types=1);

namespace Brave\Core\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Session
 *
 * Only used to generate the database schema for the PdoSessionHandler.
 *
 * @see \Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler
 * @ORM\Table(name="sessions")
 * @ORM\Entity
 */
class Session
{
    /**
     * @var string
     * @ORM\Column(name="sess_id", type="binary", length=128, nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    public $sessId;

    /**
     * @var string
     * @ORM\Column(name="sess_data", type="blob", length=65535, nullable=false)
     */
    public $sessData;

    /**
     * @var int
     * @ORM\Column(name="sess_lifetime", type="integer", nullable=false)
     */
    public $sessLifetime;

    /**
     * @var int
     * @ORM\Column(name="sess_time", type="integer", nullable=false, options={"unsigned"=true})
     */
    public $sessTime;
}
