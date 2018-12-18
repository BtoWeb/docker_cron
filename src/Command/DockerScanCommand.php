<?php
/**
 * Created by PhpStorm.
 * User: Utilisateur
 * Date: 28/05/2018
 * Time: 17:16
 */

namespace App\Command;


use App\Entity\CronTask;
use App\Entity\ManualCommand;
use Docker\API\Model\ContainerSummaryItem;
use Docker\Docker;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use JMS\JobQueueBundle\Console\CronCommand;
use JMS\JobQueueBundle\Entity\CronJob;
use JMS\JobQueueBundle\Entity\Job;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DockerScanCommand extends ContainerAwareCommand implements CronCommand {
	/**
	 * @var EntityManager
	 */
	protected $em;

	/**
	 * @var EntityRepository
	 */
	protected $cronTaskRepository;

	/**
	 * @var EntityRepository
	 */
	protected $manualCommandRepository;

	/**
	 * @var EntityRepository
	 */
	protected $cronJobRepository;

	/**
	 * @param ContainerSummaryItem $container
	 *
	 * @return CronTask[]
	 */
	protected function processContainer( $container ) {
		/** @var CronTask[] $commands */
		$commands = [];

		foreach ( $container->getLabels() as $label => $value ) {
			$matchs = [];
			if ( preg_match( '#deck-chores\.([^.]+)\.(command|interval|cron|user|max)#', $label, $matchs ) ) {
				$commandName = $matchs[1];

				if ( ! isset( $commands[ $commandName ] ) ) {
					$commands[ $commandName ] = $this->cronTaskRepository->findOneBy( [ 'container' => $container->getId(), 'name' => $commandName ] );

					if ( ! $commands[ $commandName ] ) {
						$commands[ $commandName ] = new CronTask();
						$commands[ $commandName ]->setContainer( $container->getId() )
						                         ->setName( $commandName )
						                         ->setActive( true )
						                         ->setMax( 1 );
					} else {
						$commands[ $commandName ]->setActive( true );
					}
				}

				switch ( $matchs[2] ) {
					case 'command':
						$commands[ $commandName ]->setCommand( $value );
						break;

					case 'interval':
						$commands[ $commandName ]->setInterval( $value );
						break;

					case 'cron':
						$commands[ $commandName ]->setCron( $value );
						break;

					case 'user':
						$commands[ $commandName ]->setUser( $value );
						break;

					case 'max':
						$commands[ $commandName ]->setMax( intval( $value ) );
						break;
				}
			}
		}

		return array_values( $commands );
	}

	/**
	 * @param ContainerSummaryItem $container
	 *
	 * @return ManualCommand[]
	 */
	protected function processContainerManual( $container ) {
		/** @var ManualCommand[] $commands */
		$commands = [];

		foreach ( $container->getLabels() as $label => $value ) {
			$matchs = [];
			if ( preg_match( '#manual-chores\.([^.]+)\.(command|user)#', $label, $matchs ) ) {
				$commandName = $matchs[1];

				if ( ! isset( $commands[ $commandName ] ) ) {
					$commands[ $commandName ] = $this->manualCommandRepository->findOneBy( [ 'container' => $container->getId(), 'name' => $commandName ] );

					if ( ! $commands[ $commandName ] ) {
						$commands[ $commandName ] = new ManualCommand();
						$commands[ $commandName ]->setContainer( $container->getId() )
						                         ->setName( $commandName )
						                         ->setContainerName( $container->getNames()[0] ?? 'noname' )
						                         ->setActive( true );
					} else {
						$commands[ $commandName ]->setActive( true );
					}
				}

				switch ( $matchs[2] ) {
					case 'command':
						$commands[ $commandName ]->setCommand( $value );
						break;

					case 'user':
						$commands[ $commandName ]->setUser( $value );
						break;
				}
			}
		}

		return array_values( $commands );
	}

	protected function configure() {
		$this->setName( 'docker:scan' );
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return int|null|void
	 * @throws \Doctrine\ORM\ORMException
	 * @throws \Doctrine\ORM\OptimisticLockException
	 */
	protected function execute( InputInterface $input, OutputInterface $output ) {
		$docker = Docker::create();

		/////////////////////////////////////////
		/// Scan la liste des containers
		$containerList = $docker->containerList();

		/////////////////////////////////////////
		/// Désactive toutes les tâches cron (sans commit)
		$this->em                      = $this->getContainer()->get( 'doctrine' )->getManager();
		$this->cronTaskRepository      = $this->em->getRepository( 'App\Entity\CronTask' );
		$this->manualCommandRepository = $this->em->getRepository( 'App\Entity\ManualCommand' );
		$this->cronJobRepository       = $this->em->getRepository( CronJob::class );
		/** @var CronTask $manualCommand */
		foreach ( $this->cronTaskRepository->findAll() as $manualCommand ) {
			$manualCommand->setActive( false );
			$this->em->persist( $manualCommand );
		}
		/** @var ManualCommand $cronTask */
		foreach ( $this->manualCommandRepository->findAll() as $manualCommand ) {
			$manualCommand->setActive( false );
			$this->em->persist( $manualCommand );
		}

		/////////////////////////////////////////
		/// Parse les informations depuis les labels des containers
		$nbTasks          = 0;
		$nbManualCommands = 0;
		foreach ( $containerList as $containerSummaryItem ) {
			foreach ( $this->processContainer( $containerSummaryItem ) as $manualCommand ) {
				$nbTasks ++;
				$this->em->persist( $manualCommand );
			}

			foreach ( $this->processContainerManual( $containerSummaryItem ) as $manualCommand ) {
				$nbManualCommands ++;
				$this->em->persist( $manualCommand );
			}
		}

		/////////////////////////////////////////
		// Flush toutes les modifications
		$this->em->flush();

		$output->writeln( $nbTasks . " tâches trouvées" );
		$output->writeln( $nbManualCommands . " commandes manuelles trouvées" );


		/////////////////////////////////////////
		/// Supprime toutes les CronTask dont on a plus besoin ainsi que les infos de cron associées
		/** @var CronTask $cronTask */
		foreach ( $this->cronTaskRepository->findBy( [ 'active' => false ] ) as $cronTask ) {
			/** @var CronJob $cronJob */
			$cronJob = $this->cronJobRepository->findOneBy( [ 'command' => $cronTask->getId() . '-' . $cronTask->getName() ] );

			if ( $cronJob ) {
				$output->writeln( "Suppression du CronJob : " . $cronJob->getCommand() );
				$this->em->remove( $cronJob );
			}
			$this->em->remove( $cronTask );
		}

		$this->em->flush();
	}

	/**
	 * @return Job
	 */
	public function createCronJob( \DateTime $lastRunAt ) {
		return new Job( 'docker:scan' );
	}

	/**
	 * @return boolean
	 */
	public function shouldBeScheduled( \DateTime $lastRunAt ) {
		// Quoi qu'il arrive, le scan est exécuté au moins 1 fois par heure
		return ( time() - $lastRunAt->getTimestamp() ) >= 3600;
	}
}