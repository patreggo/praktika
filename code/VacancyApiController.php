<?php

namespace Api;

use App\Entity\Company;
use App\Entity\User;
use App\Entity\Vacancy\Vacancy;
use App\Form\ApiVacancyType;
use App\Repository\Vacancy\VacancyRepository;
use App\Service\FilterService;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api', name: 'api_')]
class VacancyApiController extends AbstractFOSRestController
{
    public function __construct(
        private FilterService $filterService
    ) {}

    #[Rest\Get('/many_vacancies', name: 'get_many_vacancies')]
    public function getManyVacancies(
        Request $request,
        VacancyRepository $vacancyRepository,
    ): Response
    {
        $filters = $this->filterService->parseFilters($request);
        $vacancies = $vacancyRepository->findByFilters($filters);
        $total = $vacancyRepository->countByFilters($filters);

        return $this->handleView($this->view([
            'data' => $vacancies,
            'total' => $total,
            'page' => (int)($filters['page'] ?? 1),
            'limit' => (int)($filters['limit'] ?? 20),
            'filters_applied' => array_keys($filters)
        ], Response::HTTP_OK));
    }

    #[Rest\Post('/new_vacancy', name: 'create_new_vacancy')]
    public function newVacancy(
        Request                $request,
        EntityManagerInterface $entityManager,
    ): Response
    {
        $vacancy = new Vacancy();
        $form = $this->createForm(ApiVacancyType::class, $vacancy);
        $data = json_decode($request->getContent(), true);
        $form->submit($data);
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($vacancy);
            $entityManager->flush();
            return $this->handleView($this->view($vacancy, Response::HTTP_CREATED));
        }
        return $this->handleView($this->view($form->getErrors(), Response::HTTP_BAD_REQUEST));
    }

    #[Rest\Get('/single_vacancy/{id}', name: 'get_single_vacancy')]
    public function getSingleVacancy(
        Vacancy $vacancy,
        VacancyRepository $vacancyRepository,
    ): Response
    {
        $data = $vacancyRepository->find(['id' => $vacancy->getId()]);
        return $this->handleView($this->view($data, Response::HTTP_OK));
    }

    #[Rest\Put('/edit_vacancy/{id}', name: 'edit_vacancy')]
    public function editVacancy(
        Vacancy $vacancy,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response
    {
        $form = $this->createForm(ApiVacancyType::class, $vacancy);
        $data = json_decode($request->getContent(), true);
        $form->submit($data);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            return $this->handleView($this->view($vacancy, Response::HTTP_OK));
        }

        return $this->handleView($this->view($form->getErrors(), Response::HTTP_BAD_REQUEST));
    }


    #[Rest\Get('/user/personal', name: 'personal')]
    public function userResume(
        VacancyRepository $vacancyRepository,
        EntityManagerInterface $entityManager,
    ): Response
    {
        $companies = $entityManager->getRepository(Company::class)->findBy(['user' => $this->getUser()]);
        $data = $vacancyRepository->createQueryBuilder('v')
            ->where('v.company IN (:companies)')
            ->setParameter('companies', $companies)
            ->getQuery()
            ->getResult();
        return $this->handleView($this->view($data, Response::HTTP_OK));
    }
}