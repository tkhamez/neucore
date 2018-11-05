<?php declare(strict_types=1);

namespace Brave\Core\Entity;

/**
 * A system settings variable.
 *
 * @SWG\Definition(
 *     definition="SystemVariable",
 *     required={"name", "value"}
 * )
 * @Entity
 * @Table(name="settings")
 */
class SystemVariable implements \JsonSerializable
{
    /**
     * Variable name.
     *
     * @SWG\Property(maxLength=255)
     * @Column(type="string", length=255)
     * @Id
     * @NONE
     * @var string
     */
    private $name;

    /**
     * Variable value.
     *
     * @SWG\Property
     * @Column(type="text", length=65535, nullable=true)
     * @var string
     */
    private $value;

    /**
     * {@inheritDoc}
     * @see \JsonSerializable::jsonSerialize()
     */
    public function jsonSerialize()
    {
        return [
            'name' => $this->name,
            'value' => $this->value,
        ];
    }

    /**
     * Constructor
     */
    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * @param string $value
     * @return SystemVariable
     */
    public function setValue(string $value): SystemVariable
    {
        $this->value = $value;
        return $this;
    }
}
