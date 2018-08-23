<?php
/**
 * Created by PhpStorm.
 * User: Utilisateur
 * Date: 28/05/2018
 * Time: 17:07
 */

namespace App\Entity;


use Doctrine\ORM\Mapping as ORM;
use JMS\JobQueueBundle\Entity\Job;

/**
 * @ORM\Entity()
 * Class CronJob
 * @package App\Entity
 */
class DockerJob
{
    /**
     * @ORM\Id()
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     * @var int
     */
    protected $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\CronTask")
     * @var CronTask
     */
    protected $cronTask;

    /**
     * @ORM\ManyToOne(targetEntity="JMS\JobQueueBundle\Entity\Job", cascade={"persist", "remove"})
     * @var Job
     */
    protected $job;

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return Job
     */
    public function getJob(): Job
    {
        return $this->job;
    }

    /**
     * @param Job $job
     *
     * @return DockerJob
     */
    public function setJob(Job $job): DockerJob
    {
        $this->job = $job;

        return $this;
    }

    /**
     * @return CronTask
     */
    public function getCronTask(): CronTask
    {
        return $this->cronTask;
    }

    /**
     * @param CronTask $cronTask
     *
     * @return DockerJob
     */
    public function setCronTask(CronTask $cronTask): DockerJob
    {
        $this->cronTask = $cronTask;

        return $this;
    }
}