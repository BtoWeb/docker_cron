<?php
/**
 * Created by PhpStorm.
 * User: Utilisateur
 * Date: 17/12/2018
 * Time: 16:56
 */

namespace App\Scheduler;


use App\Entity\CronTask;
use Cron\CronExpression;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use JMS\JobQueueBundle\Cron\JobScheduler;
use JMS\JobQueueBundle\Entity\Job;
use Symfony\Bridge\Doctrine\RegistryInterface;

class CronTaskScheduler implements JobScheduler {

	/**
	 * @var \Doctrine\Common\Persistence\ObjectManager
	 */
	protected $em;

	/**
	 * @var CronTask
	 */
	protected $cronTask;

	/**
	 * @var EntityRepository
	 */
	protected $jobRepository;

	/**
	 * @var \DateTime
	 */
	protected $nextCheck;

	/**
	 * @return CronExpression
	 * @throws \Exception
	 */
	protected function getCronExpression(): CronExpression {
		$cronString = $this->cronTask->getCron();

		if ( ! $cronString && $this->cronTask->getInterval() ) {
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
				$this->cronTask->getInterval()
			);

			if ( is_numeric( $interval ) && $interval >= 60 ) {
				$minutes = floor( $interval / 60 );
				$hours   = floor( $minutes / 60 );
				$minutes = $minutes % 60;
				$days    = floor( $hours / 24 );
				$hours   = $hours % 24;

				// Cas simple : Toutes les X minutes

				if ( ! $days && ! $hours && $minutes ) {
					$cronString = '*/' . $minutes . ' * * * *';
				}
			} else {
				$cronString = $interval;
			}
		}

		if ( ! CronExpression::isValidExpression( $cronString ) ) {
			throw new \Exception( "CronTask#invalidCronExpression : " . $cronString );
		}

		return CronExpression::factory( $cronString );
	}

	/**
	 * CronTaskScheduler constructor.
	 *
	 * @param RegistryInterface $registry
	 * @param CronTask $cron_task
	 */
	public function __construct( RegistryInterface $registry, CronTask $cron_task ) {
		$this->em            = $registry->getManager();
		$this->jobRepository = $registry->getRepository( Job::class );
		$this->cronTask      = $cron_task;
	}

	/**
	 * @param $command
	 * @param \DateTime $lastRunAt
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function shouldSchedule( $command, \DateTime $lastRunAt ) {
		// Si on doit attendre jusqu'à une date donnée, on le fait !
		if ( $this->nextCheck && $this->nextCheck->getTimestamp() > time() ) {
			// Si on connait la prochaine date de schedule, alors on attend cette heure là ! (évite des requêtes inutiles sur la base de données)
			return false;
		}

		try {
			/** @var Job $nextJob */
			$nextJob = $this->jobRepository->createQueryBuilder( 'j' )
			                               ->where( 'j.command = :command' )
			                               ->andWhere( 'j.args LIKE :args' )
			                               ->andWhere( 'j.executeAfter = :nextRun' )
			                               ->setParameters( [
				                               'command' => 'docker:crontask:execute',
				                               'args'    => '[' . $this->cronTask->getId() . ']',
				                               'nextRun' => $this->getCronExpression()->getNextRunDate(),
			                               ] )
			                               ->getQuery()->getSingleResult();

			// On refera une vérif 1 minute avant le prochain run
			$this->nextCheck = $this->getCronExpression()->getNextRunDate( clone $nextJob->getExecuteAfter() )->sub( new \DateInterval( 'PT1M' ) );

			return false;
		} catch ( NoResultException $e ) {
			// Si le Job n'est pas encore programmé, alors on le lance !
			return true;
		} catch ( NonUniqueResultException $e ) {
			// Si on a déjà plusieurs fois le job enregistré en base, on ne fait rien !
			return false;
		}

		// Par défaut on ne fait rien
		return false;
	}

	/**
	 * @param $command
	 * @param \DateTime $lastRunAt
	 *
	 * @return Job
	 * @throws \Exception
	 */
	public function createJob( $command, \DateTime $lastRunAt ) {
		$job = new Job( 'docker:crontask:execute', [ $this->cronTask->getId() ], true, $this->cronTask->getName() );
		$job->setExecuteAfter( $this->getCronExpression()->getNextRunDate() );

		// On refera une vérif 1 minute avant le prochain run
		$this->nextCheck = $this->getCronExpression()->getNextRunDate( $this->getCronExpression()->getNextRunDate() )->sub( new \DateInterval( 'PT1M' ) );

		return $job;
	}
}