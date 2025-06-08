<?php

namespace Api;

use App\Entity\Resume\Resume;
use App\Entity\Resume\VacancyResponse;
use App\Entity\Resume\VacancyResponseStatus;
use App\Form\Resume\VacancyResponseApiType;
use App\Repository\Resume\VacancyResponseRepository;
use App\Repository\Resume\VacancyResponseStatusRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\Annotations\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

#[Route('/api/vacancy_response', name: 'api_vacancy_response_')]
class VacancyResponseApiController extends AbstractFOSRestController
{
    #[Rest\Post('/new', name: 'new')]
    public function newVacancyResponse(
        Request                         $request,
        VacancyResponseStatusRepository $vacancyResponseStatusRepository,
        EntityManagerInterface          $entityManager,
        UserRepository                  $userRepository
    ): Response
    {
        $user = $this->getUser();

        $vacancyResponse = new VacancyResponse();
        $vacancyResponse->setStatus($vacancyResponseStatusRepository->findOneBy(["techName" => VacancyResponseStatus::DEFAULT_STATUS_TECH_NAME]));
        $vacancyResponse->setInitiator($user);
        $form = $this->createForm(VacancyResponseApiType::class, $vacancyResponse);
        $data = json_decode($request->getContent(), true);

        $form->submit($data);
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($vacancyResponse);
            $entityManager->flush();
            return $this->handleView($this->view($vacancyResponse, Response::HTTP_CREATED));
        }
        return $this->handleView($this->view($form->getErrors(), Response::HTTP_BAD_REQUEST));

    }

    #[Rest\Get('/user/personal', name: 'personal')]
    public function userResume(
        VacancyResponseRepository $vacancyResponseRepository,
    ): Response
    {
        $data = $vacancyResponseRepository->findBy(['initiator' => $this->getUser()->getId()]);
        return $this->handleView($this->view($data, Response::HTTP_OK));
    }
}