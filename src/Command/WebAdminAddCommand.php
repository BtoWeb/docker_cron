<?php
/**
 * Created by PhpStorm.
 * User: Utilisateur
 * Date: 19/06/2018
 * Time: 16:40
 */

namespace App\Command;


use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class WebAdminAddCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName('web:admin:add')
             ->addArgument('username', InputArgument::REQUIRED, "Nom d'utilisateur")
             ->addArgument('password', InputArgument::REQUIRED, "Mot de passe");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $encoder = $this->getContainer()->get('security.password_encoder');
        $em      = $this->getContainer()->get('doctrine')->getManager();

        $user = new User();
        $user->setUsername($input->getArgument('username'))
             ->setPassword($encoder->encodePassword($user, $input->getArgument('password')));

        $em->persist($user);
        $em->flush();
    }
}