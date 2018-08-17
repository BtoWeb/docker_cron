<?php
/**
 * Created by PhpStorm.
 * User: Utilisateur
 * Date: 28/05/2018
 * Time: 16:46
 */

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;


/**
 * @ORM\Entity()
 * @ORM\Table(uniqueConstraints={  @ORM\UniqueConstraint(name="search_idx", columns={"container", "name"}) })
 * @ORM\HasLifecycleCallbacks()
 * Class CronTask
 * @package App\Entity
 */
class CronTask
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
     * @ORM\Column(type="string", nullable=true, name="interv")
     * @var string
     */
    protected $interval;

    /**
     * @ORM\Column(type="string", nullable=true)
     * @var string
     */
    protected $cron;

    /**
     * @ORM\Column(type="integer", nullable=false, options={"default":1})
     * @var int
     */
    protected $max;

    /**
     * @ORM\Column(type="boolean", nullable=false, options={"default":1})
     * @var boolean
     */
    protected $active;

    /**
     * @ORM\Column(type="datetime", nullable=false, options={"default":"CURRENT_TIMESTAMP"})
     * @var \DateTime
     */
    protected $dateAdd;


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
     * @return CronTask
     */
    public function setContainer(string $container): CronTask
    {
        $this->container = $container;

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
     * @return CronTask
     */
    public function setName(string $name): CronTask
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
     * @return CronTask
     */
    public function setCommand(string $command): CronTask
    {
        $this->command = $command;

        return $this;
    }

    /**
     * @return string
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param string $user
     *
     * @return CronTask
     */
    public function setUser(string $user): CronTask
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return string
     */
    public function getInterval()
    {
        return $this->interval;
    }

    /**
     * @param string $interval
     *
     * @return CronTask
     */
    public function setInterval(string $interval): CronTask
    {
        $this->interval = $interval;

        return $this;
    }

    /**
     * @return string
     */
    public function getCron()
    {
        return $this->cron;
    }

    /**
     * @param string $cron
     *
     * @return CronTask
     */
    public function setCron(string $cron): CronTask
    {
        $this->cron = $cron;

        return $this;
    }

    /**
     * @return int
     */
    public function getMax(): int
    {
        return $this->max;
    }

    /**
     * @param int $max
     *
     * @return CronTask
     */
    public function setMax(int $max): CronTask
    {
        $this->max = $max;

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
     * @return CronTask
     */
    public function setActive(bool $active): CronTask
    {
        $this->active = $active;

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getDateAdd(): \DateTime
    {
        return $this->dateAdd;
    }

    /**
     * @ORM\PrePersist()
     */
    public function prePersist() {
        $this->dateAdd = new \DateTime();
    }
}