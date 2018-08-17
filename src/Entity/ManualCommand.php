<?php
/**
 * Created by PhpStorm.
 * User: Utilisateur
 * Date: 19/06/2018
 * Time: 16:56
 */

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;


/**
 * @ORM\Entity()
 * Class ManualCommand
 * @package App\Entity
 */
class ManualCommand
{
    /**
     * @ORM\Id()
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     * @var int
     */
    protected $id;

    /**
     * @ORM\Column(type="string", nullable=false, length=100)
     * @var string
     */
    protected $container;

    /**
     * @ORM\Column(type="string", nullable=false)
     * @var string
     */
    protected $containerName;

    /**
     * @ORM\Column(type="string", nullable=false)
     * @var string
     */
    protected $name;

    /**
     * @ORM\Column(type="text", nullable=false)
     * @var string
     */
    protected $command;

    /**
     * @ORM\Column(type="string", nullable=true, length=30)
     * @var string
     */
    protected $user;

    /**
     * @ORM\Column(type="boolean", nullable=false, options={"default":1})
     * @var boolean
     */
    protected $active;

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getContainer(): string
    {
        return $this->container;
    }

    /**
     * @param string $container
     *
     * @return ManualCommand
     */
    public function setContainer(string $container): ManualCommand
    {
        $this->container = $container;

        return $this;
    }

    /**
     * @return string
     */
    public function getContainerName(): string
    {
        return $this->containerName;
    }

    /**
     * @param string $containerName
     *
     * @return ManualCommand
     */
    public function setContainerName(string $containerName): ManualCommand
    {
        $this->containerName = $containerName;

        return $this;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     *
     * @return ManualCommand
     */
    public function setName(string $name): ManualCommand
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return string
     */
    public function getCommand(): string
    {
        return $this->command;
    }

    /**
     * @param string $command
     *
     * @return ManualCommand
     */
    public function setCommand(string $command): ManualCommand
    {
        $this->command = $command;

        return $this;
    }

    /**
     * @return string
     */
    public function getUser(): string
    {
        return $this->user;
    }

    /**
     * @param string $user
     *
     * @return ManualCommand
     */
    public function setUser(string $user): ManualCommand
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * @param bool $active
     *
     * @return ManualCommand
     */
    public function setActive(bool $active): ManualCommand
    {
        $this->active = $active;

        return $this;
    }

    /**
     * @return array
     */
    public function getParameters()
    {
        $matchs = [];

        $ret = [];

        if (preg_match_all('#%([a-zA-Z]+)%#', $this->command, $matchs)) {
            $ret = $matchs[1];
        }

        return $ret;
    }
}