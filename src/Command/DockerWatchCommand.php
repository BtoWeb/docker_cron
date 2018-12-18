<?php
/**
 * Created by PhpStorm.
 * User: Utilisateur
 * Date: 30/05/2018
 * Time: 15:20
 */

namespace App\Command;


use Docker\API\Model\EventsGetResponse200;
use Docker\Docker;
use Docker\Stream\EventStream;
use JMS\JobQueueBundle\Entity\Job;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DockerWatchCommand extends ContainerAwareCommand
{
    /**
     * @param EventsGetResponse200 $event
     */
    public function onEvent($event)
    {
        $em            = $this->getContainer()->get('doctrine')->getManager();
        $jobRepository = $em->getRepository('JMSJobQueueBundle:Job');

        if ($event->getType() == 'container' && in_array($event->getAction(), ['start', 'stop'])) {
            $this->getContainer()->get('logger')->info("Event détecté", ['action' => $event->getAction(), 'container' => $event->getActor()->getID()]);

            $job      = new Job('docker:scan');
            $executeAfter = new \DateTime();
            // On groupe les lancements : max 1 par minute (dans 1 minute !)
            $executeAfter->setTimestamp(mktime(null, null, 0) + 60);
            $job->setExecuteAfter($executeAfter);

            if ( ! $jobRepository->findOneBy(['command' => 'docker:scan', 'executeAfter' => $executeAfter])) {
                $em->persist($job);
                $em->flush();
            }
        }
    }

    protected function configure()
    {
        $this->setName('docker:watch');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /////////////////////////////////////////
        /// Lock : 1 seul process à la fois
        $lockfile = $this->getContainer()->getParameter('kernel.root_dir').DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'var'.DIRECTORY_SEPARATOR.'docker.watch.lock';
        if ( ! is_file($lockfile)) {
            touch($lockfile);
        }
        $lock = fopen($lockfile, 'w');
        if ( ! flock($lock, LOCK_EX | LOCK_NB)) {
            return;
        }


        /////////////////////////////////////////
        /// Lance un scan avant toute chose !
        $em = $this->getContainer()->get('doctrine')->getManager();

        $job      = new Job('docker:scan');
        $executeAfter = new \DateTime();
        // On groupe les lancements : max 1 par minute (dans 1 minute !)
        $executeAfter->setTimestamp(mktime(null, null, 0) + 60);
        $job->setExecuteAfter($executeAfter);
        $em->persist($job);
        $em->flush();

        $docker = Docker::create();

        /** @var EventStream $eventStream */
        $eventStream = $docker->systemEvents(['since' => ''.time(), 'until' => ''.(time() + 3600)]);

        $eventStream->onFrame([$this, 'onEvent']);
        $eventStream->wait();

        flock($lock, LOCK_UN);
        fclose($lock);
    }
}