<?php

// src/Controller/InternshipController.php

namespace App\Controller;

use App\Service\MailingService;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * The InternshipController test handles any calls that have not been picked up by another test, and wel try to handle the slug based against the wrc.
 *
 * Class InternshipController
 *
 * @Route("/internships")
 */
class InternshipController extends AbstractController
{
    /**
     * @Route("/")
     * @Template
     */
    public function indexAction(CommonGroundService $commonGroundService, Request $request)
    {

        // On an index route we might want to filter based on user input
        $variables['query'] = array_merge($request->query->all(), $variables['post'] = $request->request->all());

        // Get resources Interschips
        $variables['internships'] = $commonGroundService->getResource(['component' => 'mrc', 'type' => 'job_postings'], $variables['query'])['hydra:member'];

        // If user is loged in check for every Internship if this user liked it
        if ($this->getUser()) {
            if ($commonGroundService->isResource($this->getUser()->getPerson())) {
                foreach ($variables['internships'] as &$internship) {
                    $internshipUrl = $commonGroundService->cleanUrl(['component' => 'mrc', 'type' => 'job_postings', 'id'=>$internship['id']]);
                    $likes = $commonGroundService->getResourceList(['component' => 'rc', 'type' => 'likes'], ['resource' => $internshipUrl, 'author' => $this->getUser()->getPerson()])['hydra:member'];
                    if (count($likes) > 0) {
                        $internship['like'] = $likes[0];
                    }
                }
            }
        }

        return $variables;
    }

    /**
     * @Route("/{id}")
     * @Template
     */
    public function positionAction(CommonGroundService $commonGroundService, MailingService $mailingService, Request $request, $id, ParameterBagInterface $params)
    {
        $variables = [];

        // Get resource internship
        $variables['internship'] = $commonGroundService->getResource(['component' => 'mrc', 'type' => 'job_postings', 'id'=>$id]);

        //get all positions of the hiringOrganization
        $variables['positions'] = $commonGroundService->getResourceList(['component' => 'mrc', 'type' => 'job_postings'], ['hiringOrganization' => $variables['internship']['hiringOrganization']])['hydra:member'];

        // Check if there is a user loged in
        if ($this->getUser()) {
            // Get the cc/person url of this user
            $personUrl = $this->getUser()->getPerson();

            //get employee conected to the user
            $employees = $commonGroundService->getResourcelist(['component' => 'mrc', 'type' => 'employees'], ['person' => $personUrl])['hydra:member'];
            if (count($employees) > 0) {
                $variables['employee'] = $employees[0];
            } else {
                //create a new employee if this user doesn't have one
                $variables['employee']['person'] = $personUrl;
                $variables['employee'] = $commonGroundService->createResource($variables['employee'], ['component' => 'mrc', 'type' => 'employees']);
            }
        }

        // Lets see if there is a post to procces
        if ($request->isMethod('POST')) {
            if (empty($this->getUser())) {
                return $this->redirect($this->generateUrl('app_user_idvault'));
            } elseif (empty($variables['employee']) || empty($personUrl)) {
                return $this->redirect($this->generateUrl('app_internship_position', ['id'=>$id]));
            }

            $variables['application'] = [];
            $resource = $request->request->all();
            $resource['employee'] = '/employees/'.$variables['employee']['id'];
            $resource['jobPosting'] = '/job_postings/'.$variables['internship']['id'];
            $resource['status'] = 'applied';
            // Update to the commonground component
            $variables['application'] = $commonGroundService->saveResource($resource, ['component' => 'mrc', 'type' => 'applications']);

            // Send an email to organization of this jobPosting:
            // (Maybe this should be handled with the VSBE to send emails whenever an application is created?)
            if (isset($variables['internship']['hiringOrganization'])) {

                // Determine the receiver
                $organization = $commonGroundService->getResource($variables['internship']['hiringOrganization']);

                if (isset($organization['administrationContact'])) {
                    $receiver = $commonGroundService->getResource($organization['administrationContact'])['username'];
                } else {
                    //for excisting organization that still uses this. this is to prevent errors.
                    $receiver = $organization['contact'];
                }

                $data = [
                    'student'   => $commonGroundService->getResource($personUrl),
                    'internship'=> $variables['internship'],

                ];

                $mailingService->sendMail('mails/internship_application.html.twig', 'no-reply@stage-platform.nl', $receiver, 'stage inschrijving', $data);
            }
        }

        return $variables;
    }

    /**
     * @Route("/internships/like/{id}/{backUrl}")
     * @Template
     */
    public function likeAction(CommonGroundService $commonGroundService, Request $request, $id, $backUrl)
    {
        // Get resource internship
        $internship = $commonGroundService->getResource(['component' => 'mrc', 'type' => 'job_postings', 'id'=>$id]);
        $internshipUrl = $commonGroundService->cleanUrl(['component' => 'mrc', 'type' => 'job_postings', 'id'=>$id]);

        if ($this->getUser()) {
            if ($commonGroundService->isResource($this->getUser()->getPerson())) {
                $likes = $commonGroundService->getResourceList(['component' => 'rc', 'type' => 'likes'], ['resource' => $internshipUrl, 'author' => $this->getUser()->getPerson()])['hydra:member'];
                if (count($likes) > 0) {
                    $like = $likes[0];
                    // Delete this existing like
                    $commonGroundService->deleteResource($like);
                } else {
                    // Create a new like
                    $like['resource'] = $internshipUrl;
                    $like['author'] = $this->getUser()->getPerson();
                    $like['organization'] = $internship['hiringOrganization'];
                    $commonGroundService->createResource($like, ['component' => 'rc', 'type' => 'likes']);
                }
            }
        }

        if ($backUrl == 'app_internship_index') {
            return $this->redirect($this->generateUrl('app_internship_index').'#'.$internship['id']);
        } else {
            return $this->redirect($this->generateUrl($backUrl));
        }
    }
}
