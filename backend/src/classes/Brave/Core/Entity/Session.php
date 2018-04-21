<?php declare(strict_types=1);

namespace Brave\Core\Entity;

/**
 * Session
 *
 * Only used to generate the database schema for the PdoSessionHandler.
 *
 * @see \Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler
 * @Table(name="sessions")
 * @Entity
 */
class Session
{
    /**
     * @var string
     * @Column(name="sess_id", type="binary", length=128, nullable=false)
     * @Id
     * @GeneratedValue(strategy="NONE")
     */
    private $sessId;

    /**
     * @var string
     * @Column(name="sess_data", type="blob", length=65535, nullable=false)
     */
    private $sessData;

    /**
     * @var int
     * @Column(name="sess_lifetime", type="integer", nullable=false)
     */
    private $sessLifetime;

    /**
     * @var int
     * @Column(name="sess_time", type="integer", nullable=false, options={"unsigned"=true})
     */
    private $sessTime;
}
