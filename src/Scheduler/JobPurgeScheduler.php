<?php
/**
 * Created by PhpStorm.
 * User: Utilisateur
 * Date: 30/05/2018
 * Time: 14:24
 */

namespace App\Scheduler;


use App\Entity\DockerJob;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityRepository;
use JMS\JobQueueBundle\Cron\JobScheduler;
use JMS\JobQueueBundle\Entity\Job;

/**
 * Class JobPurgeScheduler
 * @package App\Scheduler
 */
class JobPurgeScheduler implements JobScheduler
{
    /**
     * @var \Doctrine\Common\Persistence\ObjectManager|object
     */
    protected $em;

    /**
     * @var EntityRepository
     */
    protected $jobRepository;

    public function __construct(Registry $registry)
    {
        $this->em = $registry->getManager();
        $this->jobRepository = $registry->getRepository('App:DockerJob');
    }

    /**
     * @param string $command
     * @param \DateTime $lastRunAt
     *
     * @return boolean
     */
    public function shouldSchedule($command, \DateTime $lastRunAt)
    {
        // Toutes les 30 minutes uniquement
        return (time() - $lastRunAt->getTimestamp()) >= 1800;
    }

    /**
     * @param string $command
     * @param \DateTime $lastRunAt
     *
     * @return Job
     */
    public function createJob($command, \DateTime $lastRunAt)
    {
        $now = new \DateTime();
        $now->modify("-2 days");

        // Supprimer les DockerJob dont on a plus besoin
        /** @var DockerJob[] $dockerJobs */
        $dockerJobs = $this->jobRepository->createQueryBuilder('d')
            ->innerJoin('d.job', 'j')
            ->where('j.createdAt <= :maxdate')
            ->setParameter('maxDate', $now)
            ->getQuery()->getResult();

        foreach ($dockerJobs as $dockerJob) {
            $this->em->remove($dockerJob);
        }
        $this->em->flush();

        return new Job('jms-job-queue:clean-up', ['--per-call=10000']);

    }
}