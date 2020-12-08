<?php

// src/Controller/DashboardController.php

namespace App\Controller;

use App\Service\MailingService;
use Conduction\BalanceBundle\Service\BalanceService;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Annotation\Route;

/**
 * The DashboardController test handles any calls that have not been picked up by another test, and wel try to handle the slug based against the wrc.
 *
 * Class DashboardController
 *
 * @Route("/dashboard/user")
 */
class DashboardUserController extends AbstractController
{
    /**
     * @Route("/")
     * @Template
     */
    public function indexAction(CommonGroundService $commonGroundService, Request $request)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $variables = [];

        // On an index route we might want to filter based on user input
        $variables['query'] = array_merge($request->query->all(), $variables['post'] = $request->request->all());

        // Get random unfilterd data
        $variables['challenges'] = $commonGroundService->getResource(['component' => 'chrc', 'type' => 'tenders'], $variables['query'])['hydra:member'];
        $variables['internships'] = $commonGroundService->getResource(['component' => 'mrc', 'type' => 'job_postings'], $variables['query'])['hydra:member'];

        //  Getting the participant @todo this needs to be more foolproof
        if ($this->getUser()) {
            $personUrl = $this->getUser()->getPerson();

            $variables['participants'] = $commonGroundService->getResourceList(['component' => 'edu', 'type' => 'participants', ['person' => $personUrl]])['hydra:member'];
            $employee = $commonGroundService->getResourceList(['component' => 'mrc', 'type' => 'employees', ['person' => $personUrl]])['hydra:member'];
            if (count($employee) > 0) {
                $employee = $employee[0];
            }
        }

        //employee connected to user

        //applications ophalen die gemaakt zijn door de user
        $variables['applications'] = $commonGroundService->getResourceList(['component' => 'mrc', 'type' => 'applications', ['employee' => $employee['@id']]])['hydra:member'];

//        if (!empty($variables['participant']['courses'])) {
//            $variables['courses'] = $variables['participant']['courses'];
//        }
//        if (!empty($variables['participant']['programs'])) {
//            $variables['programs'] = $variables['participant']['programs'];
//        }
//        if (!empty($variables['participant']['programs'])) {
//            $variables['results'] = $variables['participant']['results'];
//        }

        if (isset($variables['participants'])) {
            $courseIds = [];
            $groupIds = [];
            $participationIds = [];
            foreach ($variables['participants'] as $participant) {
                if (isset($participant['course']) && $participant['status'] && $participant['status'] == 'accepted') {
                    if (!in_array($participant['course']['id'], $courseIds)) {
                        $variables['courses'][] = $participant['course'];
                        $courseIds[] = $participant['course']['id'];
                    }
                }
                if (isset($participant['groupColumn']) && $participant['status'] && $participant['status'] == 'accepted') {
                    if (!in_array($participant['groupColumn']['id'], $groupIds)) {
                        $variables['groups'][] = $participant['groupColumn'];
                        $groupIds[] = $participant['groupColumn']['id'];
                    }
                }
                if (!in_array($participant['id'], $participationIds) &&
                    ($participant['groupColumn'] || $participant['program'] || $participant['course'])) {
                    $variables['participations'][] = $participant;
                    $participationIds[] = $participant['id'];
                }
            }
        }

        return $variables;
    }

    /**
     * @Route("/tutorials")
     * @Template
     */
    public function tutorialsAction(CommonGroundService $commonGroundService, Request $request)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $variables = [];

        // On an index route we might want to filter based on user input
        $variables['query'] = array_merge($request->query->all(), $variables['post'] = $request->request->all());

        // Get all tutorials
        $variables['tutorials'] = $commonGroundService->getResource(['component' => 'edu', 'type' => 'courses'], $variables['query'])['hydra:member'];

        //  Getting the participants
        if ($this->getUser()) {
            $participants = $commonGroundService->getResourceList(['component' => 'edu', 'type' => 'participants'], ['person' => $this->getUser()->getPerson()])['hydra:member'];
            if (count($participants) > 0) {
                // Get all tutorials for each participant of this user
                $tutorials = [];
                foreach ($participants as $participant) {
                    if (isset($participant['course'])) {
                        array_push($tutorials, $participant['course']);
                    }
                }
                $variables['tutorials'] = $tutorials;
            } else {
                unset($variables['tutorials']);
            }
        }

        return $variables;
    }

    /**
     * @Route("/tutorials/{id}")
     * @Template
     */
    public function tutorialAction(CommonGroundService $commonGroundService, Request $request, $id)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $variables = [];

        $variables['tutorial'] = $commonGroundService->getResource(['component' => 'edu', 'type' => 'courses', 'id' => $id]);
        $variables['tutorial']['results'][0] = ['id' => '1234', 'name' => 'test result'];

        return $variables;
    }

    /**
     * @Route("/internships")
     * @Template
     */
    public function internshipsAction(CommonGroundService $commonGroundService, Request $request)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $variables = [];

        // On an index route we might want to filter based on user input
        $variables['query'] = array_merge($request->query->all(), $variables['post'] = $request->request->all());

        // Get Interschip resources
        $variables['internships'] = $commonGroundService->getResource(['component' => 'mrc', 'type' => 'job_postings'], $variables['query'])['hydra:member'];

        return $variables;
    }

    /**
     * @Route("/internships/{id}")
     * @Template
     */
    public function internshipAction(CommonGroundService $commonGroundService, Request $request)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $variables = [];

        return $variables;
    }

    /**
     * @Route("/propositions")
     * @Template
     */
    public function propositionsAction(Request $request, CommonGroundService $commonGroundService)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $variables = [];

        // On an index route we might want to filter based on user input
        $variables['query'] = array_merge($request->query->all(), $variables['post'] = $request->request->all());

        return $variables;
    }

    /**
     * @Route("/propositions/{id}")
     * @Template
     */
    public function propositionAction(Request $request, CommonGroundService $commonGroundService, $id)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $variables = [];

        return $variables;
    }

    /**
     * @Route("/challenges")
     * @Template
     */
    public function challengesAction(Request $request, CommonGroundService $commonGroundService)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $variables = [];

        // On an index route we might want to filter based on user input
        $variables['query'] = array_merge($request->query->all(), $variables['post'] = $request->request->all());

        return $variables;
    }

    /**
     * @Route("/challenges/{id}")
     * @Template
     */
    public function challengeAction(Request $request, CommonGroundService $commonGroundService, $id)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $variables = [];

        return $variables;
    }

    /**
     * @Route("/teams")
     * @Template
     */
    public function teamsAction(CommonGroundService $commonGroundService, Request $request)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $variables = [];

        // On an index route we might want to filter based on user input
        $variables['query'] = array_merge($request->query->all(), $variables['post'] = $request->request->all());

        // Get all teams
        $variables['teams'] = $commonGroundService->getResource(['component' => 'edu', 'type' => 'groups'], $variables['query'])['hydra:member'];

        //  Getting the participants
        if ($this->getUser()) {
            $participants = $commonGroundService->getResourceList(['component' => 'edu', 'type' => 'participants'], ['person' => $this->getUser()->getPerson()])['hydra:member'];
            if (count($participants) > 0) {
                // Get the group for each participant of this user
                $teams = [];
                foreach ($participants as $participant) {
                    if (isset($participant['groupColumn'])) {
                        array_push($teams, $participant['groupColumn']);
                    }
                }
                $variables['teams'] = $teams;
            } else {
                unset($variables['teams']);
            }
        }

        return $variables;
    }

    /**
     * @Route("/teams/{id}")
     * @Template
     */
    public function teamAction(CommonGroundService $commonGroundService, Request $request)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $variables = [];

        return $variables;
    }

    /**
     * @Route("/likes")
     * @Template
     */
    public function likesAction(Request $request, CommonGroundService $commonGroundService)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        // Get all Internship resources
        $variables['internships'] = $commonGroundService->getResource(['component' => 'mrc', 'type' => 'job_postings'])['hydra:member'];

        // If user is logged in get/set every Internship this user liked
        if ($this->getUser()) {
            if ($commonGroundService->isResource($this->getUser()->getPerson())) {
                $internships = [];
                foreach ($variables['internships'] as $internship) {
                    $internshipUrl = $commonGroundService->cleanUrl(['component' => 'mrc', 'type' => 'job_postings', 'id' => $internship['id']]);
                    $likes = $commonGroundService->getResourceList(['component' => 'rc', 'type' => 'likes'], ['resource' => $internshipUrl, 'author' => $this->getUser()->getPerson()])['hydra:member'];
                    if (count($likes) > 0) {
                        array_push($internships, $internship);
                    }
                }
                $variables['internships'] = $internships;
            }
        }

        return $variables;
    }

    /**
     * @Route("/settings")
     * @Template
     */
    public function settingsAction(CommonGroundService $commonGroundService, Request $request)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $variables = [];

        if ($this->getUser()) {
            if ($commonGroundService->isResource($this->getUser()->getPerson())) {
                $variables['person'] = $commonGroundService->getResource($this->getUser()->getPerson());
                $variables['person'] = $commonGroundService->getResource(['component' => 'cc', 'type' => 'people', 'id' => $variables['person']['id']]);

                $portfolios = $commonGroundService->getResourceList(['component' => 'pfc', 'type' => 'portfolios'], ['owner' => $variables['person']['@id']])['hydra:member'];
                if (count($portfolios) > 0) {
                    $variables['portfolio'] = $portfolios[0];
                }
            }
        }

        if ($request->isMethod('POST')) {
            $name = $request->get('name');

            // Update (or create) the cc/person of this user
            if (isset($variables['person'])) {
                $person = $variables['person'];
            }

            if ($request->files->get('personalPhoto')) {
                $person['personalPhoto'] = base64_encode(file_get_contents($request->files->get('personalPhoto')));
            }

            $person['name'] = $name;
            $person['aboutMe'] = $request->get('aboutMe');
            $person['emails'][0] = [];
            $person['emails'][0]['name'] = 'email for '.$name;
            $person['emails'][0]['email'] = $request->get('email');
            $person['telephones'][0] = [];
            $person['telephones'][0]['name'] = 'telephone for '.$name;
            $person['telephones'][0]['telephone'] = $request->get('telephone');

            $address = [];
            $address['name'] = 'address for '.$name;
            $address['street'] = $request->get('street');
            $address['houseNumber'] = $request->get('houseNumber');
            $address['houseNumberSuffix'] = $request->get('houseNumberSuffix');
            $address['postalCode'] = $request->get('postalCode');
            $address['locality'] = $request->get('locality');
            $person['adresses'][0] = $address;

            $socials = [];
            $socials['name'] = 'socials for '.$name;
            $socials['description'] = 'socials for '.$name;
            $socials['facebook'] = $request->get('facebook');
            $socials['twitter'] = $request->get('twitter');
            $socials['linkedin'] = $request->get('linkedin');
            $socials['instagram'] = $request->get('instagram');
            $person['socials'][0] = $socials;

            $person = $commonGroundService->saveResource($person, ['component' => 'cc', 'type' => 'people']);

            // If this user has no person the user.person should be set to this $person?
            $users = $commonGroundService->getResourceList(['component' => 'uc', 'type' => 'users'], ['username' => $this->getUser()->getUsername()])['hydra:member'];
            if (count($users) > 0) {
                $user = $users[0];

                if (!$commonGroundService->isResource($this->getUser()->getPerson()) or !isset($user['person'])) {
                    $user['person'] = $commonGroundService->cleanUrl(['component' => 'cc', 'type' => 'people', 'id' => $person['id']]);
                    foreach ($user['userGroups'] as &$userGroup) {
                        $userGroup = '/groups/'.$userGroup['id'];
                    }
                    $commonGroundService->updateResource($user);
                }
            }

            // Update (or create) the pfc/portfolio of this user
            if (isset($variables['portfolio'])) {
                $portfolio = $variables['portfolio'];
            }
            $portfolio['name'] = 'portfolio of '.$name;
            $portfolio['owner'] = $commonGroundService->cleanUrl(['component' => 'cc', 'type' => 'people', 'id' => $person['id']]);
            $portfolio = $commonGroundService->saveResource($portfolio, ['component' => 'pfc', 'type' => 'portfolios']);

            return $this->redirect($this->generateUrl('app_dashboarduser_settings'));
        }

        return $variables;
    }

    /**
     * @Route("/organizations")
     * @Template
     */
    public function organizationsAction(CommonGroundService $commonGroundService, Request $request)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $variables['path'] = 'app_dashboarduser_organizations';
        $variables['pathToSingular'] = 'app_dashboarduser_organization';
        $variables['type'] = 'organization';

        if ($organization = $this->getUser()->getOrganization()) {
            $variables['organization'] = $commonGroundService->getResource($organization);
        }

        $user = $commonGroundService->getResourceList(['component' => 'uc', 'type' => 'users'], ['username' => $this->getUser()->getUsername()], true, false, true, false, false)['hydra:member'][0];
        $userGroups = $user['userGroups'];

        // Organizations
        $variables['items'] = [];
        $organizationIds = [];
        foreach ($userGroups as $userGroup) {
            if ($commonGroundService->isResource($userGroup['organization'])) {
                $organization = $commonGroundService->getResource($userGroup['organization']);
                if (!in_array($organization['id'], $organizationIds)) {
                    $variables['items'][] = $organization;
                    $organizationIds[] = $organization['id'];
                }
            }
        }

        if ($request->isMethod('POST')) {
            $resource = $request->request->all();
            $user = $commonGroundService->getResourceList(['component' => 'uc', 'type' => 'users'], ['username' => $this->getUser()->getUsername()], true, false, true, false, false)['hydra:member'][0];

            $resource = array_merge($user, $resource);
            unset($resource['userGroups']);
            $user = $commonGroundService->saveResource($resource, ['component' => 'uc', 'type' => 'users']);
//            $this->getUser()->setOrganization($resource['organization']);

            if (isset($variables['item']) && !empty($variables['item'])) {
                return $this->redirectToRoute($variables['path'], ['id' => $variables['item']['id']]);
            } else {
                return $this->redirectToRoute($variables['path']);
            }
        }

        return $variables;
    }

    /**
     * @Template
     * @Route("/organizations/{id}")
     */
    public function organizationAction(CommonGroundService $commonGroundService, BalanceService $balanceService, Request $request, $id = null)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $variables = [];

        $organizationUrl = $commonGroundService->cleanUrl(['component' => 'wrc', 'type' => 'organizations', 'id' => $id]);

        $account = $balanceService->getAcount($organizationUrl);

        if ($account !== false) {
            $account['balance'] = $balanceService->getBalance($organizationUrl);
            $variables['account'] = $account;
            $variables['payments'] = $commonGroundService->getResourceList(['component' => 'bare', 'type' => 'payments'], ['acount.id' => $account['id'], 'order[dateCreated]' => 'desc'])['hydra:member'];
        }

        $groups = $commonGroundService->getResourceList(['component' => 'uc', 'type' => 'groups'], ['organization' => $organizationUrl])['hydra:member'];
        if (count($groups) > 0) {
            $group = $groups[0];
            $variables['users'] = $group['users'];
        }

        $redirectToPlural = false;
        if ($id && $id != 'new') {
            $variables['item'] = $commonGroundService->getResource(['component' => 'wrc', 'type' => 'organizations', 'id' => $id]);
            $newOrganization = false;
        } else {
            $variables['item'] = [];
            $variables['item']['name'] = 'New';

            $newOrganization = true;
            $redirectToPlural = true;
        }
        $variables['path'] = 'app_dashboarduser_organization';
        $variables['pathToPlural'] = 'app_dashboarduser_organizations';

        // Lets see if there is a post to procces
        if ($request->isMethod('POST')) {
            $resource = $request->request->all();

            // Add the post data to the already aquired resource data
            $resource = array_merge($variables['item'], $resource);

            if (isset($resource['style'])) {
                $resource['style'] = '/styles/'.$resource['style']['id'];
            }

            if ($newOrganization) {
                $users = $commonGroundService->getResourceList(['component' => 'uc', 'type' => 'users'], ['username' => $this->getUser()->getUsername()])['hydra:member'];
                if (count($users) > 0) {
                    $userUrl = $commonGroundService->cleanUrl(['component' => 'uc', 'type' => 'users', 'id' => $users[0]['id']]);
                    $resource['privacyContact'] = $userUrl;
                    $resource['technicalContact'] = $userUrl;
                    $resource['administrationContact'] = $userUrl;
                }
            }

            // Update to the commonground component
            $variables['item'] = $commonGroundService->saveResource($resource, ['component' => 'wrc', 'type' => 'organizations']);

            if ($newOrganization) {
                $organizationUrl = $commonGroundService->cleanUrl(['component' => 'wrc', 'type' => 'organizations', 'id' => $variables['item']['id']]);
                $balanceService->createAccount($organizationUrl);
            }

            $variables['userGroups'] = $commonGroundService->getResourceList(['component' => 'uc', 'type' => 'groups'], ['organization' => $variables['item']['@id']])['hydra:member'];

            $user = $commonGroundService->getResourceList(['component' => 'uc', 'type' => 'users'], ['username' => $this->getUser()->getUsername()], true, false, true, false, false)['hydra:member'][0];

            if (!empty($resource['nameUG']) && !empty($resource['descUG'])) {
                $newUserGroup['name'] = $resource['nameUG'];
                $newUserGroup['description'] = $resource['descUG'];
                $newUserGroup['title'] = $resource['nameUG'];
                $newUserGroup['organization'] = $variables['item']['@id'];
                $newUserGroup['canBeRegisteredFor'] = true;
                $newUserGroup['users'][] = 'users/'.$user['id'];

                $newUserGroup = $commonGroundService->saveResource($newUserGroup, ['component' => 'uc', 'type' => 'groups']);
            }

            if (count($variables['userGroups']) == 0) {
                $userGroup = [];
                $userGroup['name'] = $variables['item']['name'].'-admin';
                $userGroup['organization'] = $variables['item']['@id'];
                $userGroup['description'] = 'The administrators for the organization';
                $userGroup['code'] = $variables['item']['name'].'-admin';
                $userGroup['canBeRegisteredFor'] = false;
                $userGroup['users'][] = 'users/'.$user['id'];

                $userGroup = $commonGroundService->saveResource($userGroup, ['component' => 'uc', 'type' => 'groups']);
            } else {
                $userGroup = $variables['userGroups'][0];
            }
            if (!$this->getUser()->getOrganization()) {
                $user['organization'] = $userGroup['organization'];
                unset($user['userGroups']);
                $user = $commonGroundService->saveResource($user, ['component' => 'uc', 'type' => 'users']);
            }
            if (isset($resource['backUrl'])) {
                return $this->redirect($resource['backUrl']);
            }
            if ($redirectToPlural === true) {
                return $this->redirectToRoute($variables['pathToPlural']);
            } else {
                return $this->redirectToRoute($variables['path'], ['id' => $variables['item']['id']]);
            }
        }

        return $variables;
    }

    /**
     * @Template
     * @Route("/transactions/{organization}")
     */
    public function transactionsAction(Session $session, CommonGroundService $commonGroundService, BalanceService $balanceService, MailingService $mailingService, Request $request, $organization)
    {
        // On an index route we might want to filter based on user input
        $variables = [];

        $organization = $commonGroundService->getResource(['component' => 'wrc', 'type' => 'organizations', 'id' => $organization]);
        $organizationUrl = $commonGroundService->cleanUrl(['component' => 'wrc', 'type' => 'organizations', 'id' => $organization['id']]);
        $variables['organization'] = $organization;

        if ($session->get('mollieCode')) {
            $mollieCode = $session->get('mollieCode');
            $session->remove('mollieCode');
            $result = $balanceService->processMolliePayment($mollieCode, $organizationUrl);

            $person = $commonGroundService->getResource($this->getUser()->getPerson());
            $data = [];
            $data['receiver'] = $person['name'];
            $data['invoice'] = $result['invoice'];

            $mailingService->sendMail('mails/invoice.html.twig', 'no-reply@conduction.academy', $this->getUser()->getUsername(), 'invoice', $data);

            if ($result['status'] == 'paid') {
                $variables['message'] = 'Payment processed successfully! <br> €'.$result['amount'].'.00 was added to your balance. <br>  Invoice with reference: '.$result['reference'].' is created.';
            } else {
                $variables['message'] = 'Something went wrong, the status of the payment is: '.$result['status'].' please try again.';
            }
        }

        $account = $balanceService->getAcount($organizationUrl);

        if ($account !== false) {
            $account['balance'] = $balanceService->getBalance($organizationUrl);
            $variables['account'] = $account;
            $variables['payments'] = $commonGroundService->getResourceList(['component' => 'bare', 'type' => 'payments'], ['acount.id' => $account['id'], 'order[dateCreated]' => 'desc'])['hydra:member'];
        }

        if ($request->isMethod('POST')) {
            $amount = $request->get('amount') * 1.21;
            $amount = (number_format($amount, 2));

            $payment = $balanceService->createMolliePayment($amount, $request->get('redirectUrl'));
            $session->set('mollieCode', $payment['id']);

            return $this->redirect($payment['redirectUrl']);
        }

        return $variables;
    }
}
