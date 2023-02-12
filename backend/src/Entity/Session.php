<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace Neucore\Entity;

/* @phan-suppress-next-line PhanUnreferencedUseNormal */
use Doctrine\ORM\Mapping as ORM;

/**
 * Session
 *
 * Only used to generate the database schema for the PdoSessionHandler.
 *
 * @see \Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler
 * @ORM\Table(name="sessions", options={"charset"="utf8mb4", "collate"="utf8mb4_unicode_520_ci"})
 * @ORM\Entity
 */
class Session
{
    /**
     * @ORM\Column(name="sess_id", type="binary", length=128, nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    public string $sessId;

    /**
     * @ORM\Column(name="sess_data", type="blob", length=65535, nullable=false)
     */
    public string $sessData;

    /**
     * @ORM\Column(name="sess_lifetime", type="integer", nullable=false)
     */
    public int $sessLifetime;

    /**
     * @ORM\Column(name="sess_time", type="integer", nullable=false, options={"unsigned"=true})
     */
    public int $sessTime;
}
