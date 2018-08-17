<?php
/**
 * Created by PhpStorm.
 * User: Utilisateur
 * Date: 28/05/2018
 * Time: 17:57
 */

namespace App\Command;


use Docker\API\Model\ContainersIdExecPostBody;
use Docker\API\Model\ExecIdStartPostBody;
use Docker\Docker;
use Docker\Stream\DockerRawStream;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DockerExecuteCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName('docker:execute')
             ->addArgument('container', InputArgument::REQUIRED, "ID ou Nom du container")
             ->addArgument('cmd', InputArgument::REQUIRED, "ligne de commande à exécuter")
             ->addArgument('user', InputArgument::OPTIONAL, "utilisateur");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $logger = $this->getContainer()->get('logger');

        $docker = Docker::create();

        /////////////////////////////////////////
        /// Scan la liste des containers
        $containerList = $docker->containerList();
        /** @var \Docker\API\Model\ContainerSummaryItem $container */
        $container = null;

        foreach ($containerList as $containerSummaryItem) {
            $info = array_merge($containerSummaryItem->getNames(), [$containerSummaryItem->getId()]);

            if (in_array($input->getArgument('container'), $info) || in_array('/'.$input->getArgument('container'), $info)) {
                $container = $containerSummaryItem;
            }
        }

        if ( ! $container) {
            $logger->error("Container non trouvé");
            exit(1);
        }

        $logger->notice("Container trouvé", ['name' => $container->getNames()[0], 'id' => $container->getId()]);

        /////////////////////////////////////////
        /// Préparation du corps de l'exécution de la commande
        $execPostBody = new ContainersIdExecPostBody();
        $execPostBody->setCmd(['bash', '-c', $input->getArgument('cmd')])
                     ->setUser($input->getArgument('user'))
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
                //$logger->info($text);
            }
        );
        $stream->onStderr(
            function ($text) use ($logger, $output) {
                $output->write($text);
                //$logger->error($text, $output);
            }
        );

        // Attend la fin de l'exécution
        $stream->wait();
    }

}