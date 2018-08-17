<?php
/**
 * Created by PhpStorm.
 * User: Utilisateur
 * Date: 19/06/2018
 * Time: 16:03
 */

namespace App\Controller;


use App\Entity\ManualCommand;
use Doctrine\ORM\EntityRepository;
use JMS\JobQueueBundle\Entity\Job;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;


class ManualCommandController extends Controller
{
    /**
     * @Route("/")
     * @Template()
     * @param Request $request
     *
     * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function indexAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        /** @var EntityRepository $manualCommandRepository */
        $manualCommandRepository = $em->getRepository('App\Entity\ManualCommand');

        if ($request->getMethod() == 'POST') {
            $command = $request->get('command');

            /** @var ManualCommand $manualCommand */
            $manualCommand = $manualCommandRepository->find($command);
            $parameters    = $request->get('parameter') ?? [];

            if ($manualCommand && $manualCommand->isActive()) {
                $commandLine = $manualCommand->getCommand();

                foreach ($parameters as $name => $value) {
                    $commandLine = str_replace('%'.$name.'%', escapeshellarg($value), $commandLine);
                }

                $job = new Job('docker:execute', [$manualCommand->getContainer(), $commandLine, $manualCommand->getUser() ?? 'root'], true, $manualCommand->getName());
                $em->persist($job);
                $em->flush();

                return $this->redirectToRoute('jms_jobs_details', ['id' => $job->getId()]);
            }
        }

        return [
            'commands' => $manualCommandRepository->findBy(['active' => true]),
        ];
    }
}