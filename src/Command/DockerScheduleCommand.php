<?php
/**
 * Created by PhpStorm.
 * User: Utilisateur
 * Date: 29/05/2018
 * Time: 15:27
 */

namespace App\Command;


use App\Entity\CronTask;
use App\Entity\DockerJob;
use Cron\CronExpression;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use JMS\JobQueueBundle\Entity\Job;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DockerScheduleCommand extends ContainerAwareCommand
{
    /**
     * @var EntityManager
     */
    protected $em;

    /**
     * @var EntityRepository
     */
    protected $cronTaskRepository;

    /**
     * @param CronTask $cronTask
     *
     * @return \DateTime|null
     */
    protected function shouldRun($cronTask)
    {
        $cronString = $cronTask->getCron();

        if ( ! $cronString && $cronTask->getInterval()) {
            $interval = str_replace(
                [
                    'yearly',
                    'annually',
                    'monthly',
                    'weekly',
                    'daily',
                    'hourly',
                    'every minute',
                ],
                [
                    '@yearly',
                    '@annually',
                    '@monthly',
                    '@weekly',
                    '@daily',
                    '@hourly',
                    '* * * * *',
                ],
                $cronTask->getInterval()
            );

            if (is_numeric($interval) && $interval >= 60) {
                $minutes = floor($interval / 60);
                $hours   = floor($minutes / 60);
                $minutes = $minutes % 60;
                $days    = floor($hours / 24);
                $hours   = $hours % 24;

                // Cas simple : Toutes les X minutes

                if ( ! $days && ! $hours && $minutes) {
                    $cronString = '*/'.$minutes.' * * * *';
                }
            }
        }

        if ( ! CronExpression::isValidExpression($cronString)) {
            /*if ($cronString) {
                var_dump($cronString);
                exit();
            }*/

            return null;
        }

        $cronExpression = CronExpression::factory($cronString);

        return $cronExpression->getNextRunDate();
    }

    /**
     * @param CronTask $cronTask
     * @param \DateTime $runDate
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    protected function runTask($cronTask, \DateTime $runDate)
    {
        /** @var EntityRepository $dockerJobRepository */
        $dockerJobRepository = $this->em->getRepository('App:DockerJob');

        /** @var DockerJob $dockerJob */
        $dockerJob = $dockerJobRepository->createQueryBuilder('d')
                                         ->innerJoin('d.job', 'j')
                                         ->where('d.cronTask = :task')
                                         ->andWhere('j.executeAfter = :runDate')
                                         ->setParameters(['task' => $cronTask, 'runDate' => $runDate])
                                         ->getQuery()->getOneOrNullResult();

        if ( ! $dockerJob) {
            $job = new Job('docker:execute', [$cronTask->getContainer(), $cronTask->getCommand(), $cronTask->getUser() ?? 'root'], true, $cronTask->getName());
            $job->setExecuteAfter($runDate);

            $dockerJob = new DockerJob();
            $dockerJob->setCronTask($cronTask)
                      ->setJob($job);

            $this->em->persist($dockerJob);
        }
    }

    protected function configure()
    {
        $this->setName('docker:schedule');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $logger = $this->getContainer()->get('logger');

        while (true) {
            $logger->info("Exécution du Scheduler pour les tâches docker");
            $start = time();

            $this->em                 = $this->getContainer()->get('doctrine')->getManager();
            $this->cronTaskRepository = $this->em->getRepository('App:CronTask');

            /** @var CronTask[] $cronTasks */
            $cronTasks = $this->cronTaskRepository->findBy(['active' => true]);

            foreach ($cronTasks as $cronTask) {
                $runDate = $this->shouldRun($cronTask);

                if ($runDate) {
                    try {
                        $this->runTask($cronTask, $runDate);
                    } catch (NonUniqueResultException $e) {
                        var_dump($e->getMessage());
                    } catch (OptimisticLockException $e) {
                        var_dump($e->getMessage());
                    } catch (ORMException $e) {
                        var_dump($e->getMessage());
                    }
                }
            }

            // Ajoute toutes les tâches à faire
            $this->em->flush();

            // Purge le cache Doctrine
            if ($this->em->getCache()) {
                $this->em->getCache()->evictQueryRegions();
                $this->em->getCache()->evictCollectionRegions();
                $this->em->getCache()->evictEntityRegions();
            }

            $end = time();

            $duration = $end - $start;

            // On relance la planification chaque minute
            sleep(max(0, 60 - $duration));
        }
    }
}