<?php
/**
 * Created by PhpStorm.
 * User: Utilisateur
 * Date: 18/12/2018
 * Time: 13:56
 */

namespace App\Scheduler;


use App\Entity\CronTask;
use Doctrine\ORM\EntityRepository;
use JMS\JobQueueBundle\Cron\JobScheduler;
use Symfony\Bridge\Doctrine\RegistryInterface;

class SchedulerRegistry {
	/**
	 * @var JobScheduler[]
	 */
	protected $schedulers;

	/**
	 * @var RegistryInterface
	 */
	protected $registry;

	/**
	 * @var EntityRepository
	 */
	protected $cronTaskRepository;


	protected function scanCronTasks() {
		/** @var CronTask $cronTask */
		foreach ( $this->cronTaskRepository->findBy( [ 'active' => 1 ] ) as $cronTask ) {
			$name = $cronTask->getId() . '-' . $cronTask->getName();

			$this->schedulers[ $name ] = new CronTaskScheduler( $this->registry, $cronTask );
		}
	}

	/**
	 * @param \JMS\JobQueueBundle\Cron\SchedulerRegistry $oldRegistry
	 * @param RegistryInterface $registry
	 */
	public function __construct( \JMS\JobQueueBundle\Cron\SchedulerRegistry $oldRegistry, RegistryInterface $registry ) {
		$this->registry           = $registry;
		$this->schedulers         = $oldRegistry->getSchedulers();
		$this->cronTaskRepository = $registry->getRepository( CronTask::class );

		$this->scanCronTasks();
	}

	/**
	 * @return CronTaskSchedulerList
	 */
	public function getSchedulers() {
		return $this->schedulers;
	}
}