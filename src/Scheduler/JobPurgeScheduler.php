<?php
/**
 * Created by PhpStorm.
 * User: Utilisateur
 * Date: 30/05/2018
 * Time: 14:24
 */

namespace App\Scheduler;


use JMS\DiExtraBundle\Annotation as DI;
use JMS\JobQueueBundle\Cron\JobScheduler;
use JMS\JobQueueBundle\Entity\Job;

/**
 * @DI\Service()
 * @DI\Tag("jms_job_queue.scheduler", attributes = {"command": "jms-job-queue:clean-up"})
 * Class JobPurgeScheduler
 * @package App\Scheduler
 */
class JobPurgeScheduler implements JobScheduler
{
    /**
     * @var EntityRepository
     */
    protected $jobRepository;
/*
    public function __construct(Registry $registry)
    {
        $this->jobRepository = $registry->getRepository('JMSJobQueueBundle:Job');
    }*/

    /**
     * @param string $command
     * @param \DateTime $lastRunAt
     *
     * @return boolean
     */
    public function shouldSchedule($command, \DateTime $lastRunAt)
    {
        // Toutes les 30 minutes uniquement
        if ((time() - $lastRunAt->getTimestamp()) >= 1800) {
            // TODO : A coder
            return true;
        }
    }

    /**
     * @param string $command
     * @param \DateTime $lastRunAt
     *
     * @return Job
     */
    public function createJob($command, \DateTime $lastRunAt)
    {
        // TODO : A Améliorer
        return new Job('jms-job-queue:clean-up');

    }
}