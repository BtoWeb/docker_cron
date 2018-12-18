<?php
/**
 * Created by PhpStorm.
 * User: Utilisateur
 * Date: 17/12/2018
 * Time: 17:06
 */

namespace App\Command;


use App\Entity\CronTask;
use Docker\API\Model\ContainersIdExecPostBody;
use Docker\API\Model\ContainerSummaryItem;
use Docker\API\Model\ExecIdStartPostBody;
use Docker\Docker;
use Docker\Stream\DockerRawStream;
use Doctrine\ORM\EntityRepository;
use JMS\JobQueueBundle\Entity\Job;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DockerExecuteCronTaskCommand extends ContainerAwareCommand {

	/**
	 * Nombre de process d'une même commande en train de fonctionner
	 *
	 * @param EntityRepository $jobRepository
	 * @param CronTask $cronTask
	 *
	 * @return int
	 * @throws \Doctrine\ORM\NonUniqueResultException
	 */
	protected function getRunningJobCount( EntityRepository $jobRepository, CronTask $cronTask ): int {
		return $jobRepository->createQueryBuilder( 'j' )
		                     ->select( 'COUNT(j)' )
		                     ->where( 'j.command = :command' )
		                     ->andWhere( 'j.args LIKE :args' )
		                     ->andWhere( 'j.state = :state' )
		                     ->setParameters( [
			                     'command' => 'docker:crontask:execute',
			                     'args'    => '[' . $cronTask->getId() . ']',
			                     'state'   => "running",
		                     ] )
		                     ->getQuery()->getSingleScalarResult();
	}

	protected function configure() {
		$this->setName( 'docker:crontask:execute' )
		     ->addArgument( 'id', InputArgument::REQUIRED, "ID de la tâche cron à exécuter" );
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return int|null
	 * @throws \Doctrine\ORM\NonUniqueResultException
	 */
	protected function execute( InputInterface $input, OutputInterface $output ) {
		/** @var Logger $logger */
		$logger = $this->getContainer()->get( 'logger' );

		$cronTaskRepository = $this->getContainer()->get( 'doctrine' )->getRepository( CronTask::class );
		$jobRepository      = $this->getContainer()->get( 'doctrine' )->getRepository( Job::class );
		/** @var CronTask $cronTask */
		$cronTask = $cronTaskRepository->find( $input->getArgument( 'id' ) );

		/////////////////////////////////////////
		/// Récupération de la tâche cron
		if ( ! $cronTask ) {
			$logger->err( "CronTask not found", [ 'id' => $input->getArgument( 'id' ) ] );

			return 1;
		} elseif ( ! $cronTask->isActive() ) {
			$logger->err( "CronTask not active", [ 'id' => $input->getArgument( 'id' ) ] );

			return 1;
		}

		/////////////////////////////////////////
		/// Compte le nombre d'exécutions en cours
		if ( $this->getRunningJobCount( $jobRepository, $cronTask ) > $cronTask->getMax() ) {
			$logger->err( "Le nombre maximum d'instances de cette tâche est déjà atteint",
				[ 'id' => $input->getArgument( 'id' ), 'max' => $cronTask->getMax(), 'running' => $this->getRunningJobCount( $jobRepository, $cronTask ) ] );

			return 1;
		}

		$docker = Docker::create();

		/////////////////////////////////////////
		/// Scan la liste des containers
		$containerList = $docker->containerList();
		/** @var \Docker\API\Model\ContainerSummaryItem $container */
		$container = array_reduce( $containerList,
			function ( $carry, ContainerSummaryItem $containerSummaryItem ) use ( $cronTask ) {
				if ( $cronTask->getContainer() == $containerSummaryItem->getId() ) {
					$carry = $containerSummaryItem;
				}

				return $carry;
			},
			null );


		if ( ! $container ) {
			$logger->error( "Container non trouvé", [ 'id' => $cronTask->getId() ] );

			return 1;
		}

		$logger->notice( "Container trouvé", [ 'name' => $container->getNames()[0], 'id' => $container->getId() ] );

		/////////////////////////////////////////
		/// Préparation du corps de l'exécution de la commande
		$execPostBody = new ContainersIdExecPostBody();
		$execPostBody->setCmd( [ 'bash', '-c', $cronTask->getCommand() ] )
		             ->setUser( $cronTask->getUser() ?? 'root' )
		             ->setPrivileged( false )
		             ->setTty( false )
		             ->setAttachStdout( true )
		             ->setAttachStderr( true )
		             ->setAttachStdin( false );

		$logger->notice( "Exécution de la commande", [ 'cmd' => $execPostBody->getCmd() ] );

		$execInfo = $docker->containerExec( $container->getId(), $execPostBody );

		$execStartPostBody = new ExecIdStartPostBody();
		$execStartPostBody->setDetach( false );

		/** @var DockerRawStream $stream */
		$stream = $docker->execStart( $execInfo->getId(), $execStartPostBody );

		$stream->onStdout(
			function ( $text ) use ( $logger, $output ) {
				$output->write( $text );
			}
		);
		$stream->onStderr(
			function ( $text ) use ( $logger, $output ) {
				$output->write( $text );
			}
		);

		// Attend la fin de l'exécution
		$stream->wait();

		$logger->notice( "Fin de l'exécution de la commande" );

		return 0;
	}
}