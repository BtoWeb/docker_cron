<?php
/**
 * Created by PhpStorm.
 * User: Utilisateur
 * Date: 18/12/2018
 * Time: 17:32
 */

namespace App\Command;


use App\Entity\ManualCommand;
use Docker\API\Model\ContainersIdExecPostBody;
use Docker\API\Model\ContainerSummaryItem;
use Docker\API\Model\ExecIdStartPostBody;
use Docker\Docker;
use Docker\Stream\DockerRawStream;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DockerExecuteManualTaskCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName('docker:manualtask:execute')
            ->addArgument('id', InputArgument::REQUIRED, "ID de la tâche manuelle à exécuter")
            ->addArgument('parameters', InputArgument::REQUIRED, "Arguments de la commande au format JSON");
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var Logger $logger */
        $logger = $this->getContainer()->get('logger');

        $manualTaskRepository = $this->getContainer()->get('doctrine')->getRepository(ManualCommand::class);

        /** @var ManualCommand $manualTask */
        $manualTask = $manualTaskRepository->find($input->getArgument('id'));

        /////////////////////////////////////////
        /// Récupération de la tâche cron
        if (!$manualTask) {
            $logger->err("ManualCommand not found", ['id' => $input->getArgument('id')]);

            return 1;
        } elseif (!$manualTask->isActive()) {
            $logger->err("ManualCommand not active", ['id' => $input->getArgument('id')]);

            return 1;
        }

        $docker = Docker::create();

        /////////////////////////////////////////
        /// Scan la liste des containers
        $containerList = $docker->containerList();
        /** @var \Docker\API\Model\ContainerSummaryItem $container */
        $container = array_reduce($containerList,
            function ($carry, ContainerSummaryItem $containerSummaryItem) use ($manualTask) {
                if ($manualTask->getContainer() == $containerSummaryItem->getId()) {
                    $carry = $containerSummaryItem;
                }

                return $carry;
            },
            null);


        if (!$container) {
            $logger->error("Container non trouvé", ['id' => $manualTask->getId()]);

            return 1;
        }

        $logger->notice("Container trouvé", ['name' => $container->getNames()[0], 'id' => $container->getId()]);

        /////////////////////////////////////////
        /// Préparation du corps de l'exécution de la commande
        $command = $manualTask->getCommand();
        $parameters = json_decode($input->getArgument('parameters'), true);
        foreach ($parameters as $key => $value) {
            $command = str_replace('%' . $key . '%', escapeshellarg($value), $command);
        }
        $execPostBody = new ContainersIdExecPostBody();
        $execPostBody->setCmd(['bash', '-c', $command])
            ->setUser($manualTask->getUser() ?? 'root')
            ->setPrivileged(false)
            ->setTty(false)
            ->setAttachStdout(true)
            ->setAttachStderr(true)
            ->setAttachStdin(false);

        $logger->notice("Exécution de la commande", ['cmd' => $execPostBody->getCmd()]);

        $execInfo = $docker->containerExec($container->getId(), $execPostBody);

        $execStartPostBody = new ExecIdStartPostBody();
        $execStartPostBody->setDetach(false);

        /** @var DockerRawStream $stream */
        $stream = $docker->execStart($execInfo->getId(), $execStartPostBody);

        $stream->onStdout(
            function ($text) use ($logger, $output) {
                $output->write($text);
            }
        );
        $stream->onStderr(
            function ($text) use ($logger, $output) {
                $output->write($text);
            }
        );

        // Attend la fin de l'exécution
        $stream->wait();

        $logger->notice("Fin de l'exécution de la commande");

        return 0;
    }
}