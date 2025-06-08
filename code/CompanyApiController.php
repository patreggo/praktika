<?php

namespace Api;

use App\Entity\Company;
use App\Form\CompanyApiType;
use App\Repository\CompanyRepository;
use App\Service\FilterService;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/company', name: 'api_company_')]
class CompanyApiController extends AbstractFOSRestController
{
    public function __construct(
        private FilterService $filterService
    ) {}

    #[Rest\Get('/list', name: 'list', methods: ['GET'])]
    public function getManyCompanies(
        CompanyRepository $companyRepository,
        Request $request,
    ): Response
    {
        $filters = $this->filterService->parseFilters($request);
        $companies = $companyRepository->findByFilters($filters);
        $total = $companyRepository->countByFilters($filters);

        return $this->handleView($this->view([
            'data' => $companies,
            'total' => $total,
            'page' => (int)($filters['page'] ?? 1),
            'limit' => (int)($filters['limit'] ?? 20),
            'filters_applied' => array_keys($filters)
        ], Response::HTTP_OK));
    }

    #[Rest\Get('/{id}', name: 'single', methods: ['GET'])]
    public function getSingleCompany(
        Company $company,
        CompanyRepository $companyRepository,
    ): Response
    {
        $data = $companyRepository->find(['id' => $company->getId()]);
        return $this->handleView($this->view($data, Response::HTTP_OK));
    }

    #[Rest\Post('/new', name: 'new')]
    public function newCompany(
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response
    {
        $userRoles = $this->getUser()->getRoles();
        if(!in_array('ROLE_EMPLOYER', $userRoles, true)){
            throw new AccessDeniedHttpException();
        }
        $company = new Company();
        $company->setUser($this->getUser());
        $company->setIsConfirmed(false); // По умолчанию компания не подтверждена

        $form = $this->createForm(CompanyApiType::class, $company);
        $data = json_decode($request->getContent(), true);
        $form->submit($data);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($company);
            $entityManager->flush();

            return $this->handleView($this->view($company, Response::HTTP_CREATED));
        }

        return $this->handleView($this->view($form->getErrors(), Response::HTTP_BAD_REQUEST));
    }

    #[Rest\Put('/{id}', name: 'update')]
    public function updateCompany(
        Company $company,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response
    {
        // Проверяем, что пользователь является владельцем компании
        if ($company->getUser() !== $this->getUser()) {
            return $this->handleView($this->view(['error' => 'Access denied'], Response::HTTP_FORBIDDEN));
        }

        $form = $this->createForm(CompanyApiType::class, $company);
        $data = json_decode($request->getContent(), true);
        $form->submit($data);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->handleView($this->view($company, Response::HTTP_OK));
        }

        return $this->handleView($this->view($form->getErrors(), Response::HTTP_BAD_REQUEST));
    }

    #[Rest\Delete('/{id}', name: 'delete')]
    public function deleteCompany(
        Company $company,
        EntityManagerInterface $entityManager,
    ): Response
    {
        if ($company->getUser() !== $this->getUser()) {
            return $this->handleView($this->view(['error' => 'Access denied'], Response::HTTP_FORBIDDEN));
        }

        $entityManager->remove($company);
        $entityManager->flush();

        return $this->handleView($this->view(['message' => 'Company deleted successfully'], Response::HTTP_OK));
    }

    #[Rest\Get('/user/personal', name: 'personal')]
    public function userCompanies(
        CompanyRepository $companyRepository,
    ): Response
    {
        $data = $companyRepository->findBy(['user' => $this->getUser()->getId()]);
        return $this->handleView($this->view($data, Response::HTTP_OK));
    }
}