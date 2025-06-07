<?php

namespace Api\Resume;

use App\Entity\Resume\Resume;
use App\Form\Resume\ResumeApiType;
use App\Repository\Resume\ResumeRepository;
use App\Service\FilterService;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


#[Route('/api/resume', name: 'api_resume_')]
class ResumeApiController extends AbstractFOSRestController
{

    public function __construct(
        private FilterService $filterService
    ) {}

    #[Rest\Get('/list', name: 'list', methods: ['GET'])]
    public function getManyResumes(
        ResumeRepository $resumeRepository,
        Request $request,
    ): Response
    {
        $filters = $this->filterService->parseFilters($request);
        $resumes = $resumeRepository->findByFilters($filters);
        $total = $resumeRepository->countByFilters($filters);

        return $this->handleView($this->view([
            'data' => $resumes,
            'total' => $total,
            'page' => (int)($filters['page'] ?? 1),
            'limit' => (int)($filters['limit'] ?? 20),
            'filters_applied' => array_keys($filters)
        ], Response::HTTP_OK));
    }


    #[Rest\Get('/{id}', name: 'single', methods: ['GET'])]
    public function getSingleResume(
        Resume           $resume,
        ResumeRepository $resumeRepository,
    ): Response
    {
        $data = $resumeRepository->find(['id' => $resume->getId()]);
        return $this->handleView($this->view($data, Response::HTTP_OK));
    }

    #[Rest\Post('/new', name: 'new')]
    public function newResume(
        Request              $request,
        EntityManagerInterface $entityManager,
    ): Response
    {
        $resume = new Resume();
        $resume->setUser($this->getUser());
        $form = $this->createForm(ResumeApiType::class, $resume);
        $data = json_decode($request->getContent(), true);
        $form->submit($data);
        if ($form->isSubmitted() && $form->isValid()) {
            foreach ($resume->getWorkPlace() as $workPlace) {
                $workPlace->setResume($resume);
            }

            foreach ($resume->getEducation() as $education) {
                $education->setResume($resume);
            }
            $entityManager->persist($resume);
            $entityManager->flush();
            return $this->handleView($this->view($resume, Response::HTTP_CREATED));
        }
        return $this->handleView($this->view($form->getErrors(), Response::HTTP_BAD_REQUEST));
    }

    #[Rest\Get('/user/personal', name: 'personal')]
    public function userResume(
        ResumeRepository $resumeRepository,
    ): Response
    {
        $data = $resumeRepository->findBy(['user' => $this->getUser()->getId()]);
        return $this->handleView($this->view($data, Response::HTTP_OK));
    }

    #[Rest\Put('/edit_resume/{id}', name: 'edit_resume')]
    public function editResume(
        Resume $resume,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response
    {
        $form = $this->createForm(ResumeApiType::class, $resume);
        $data = json_decode($request->getContent(), true);
        $form->submit($data);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            return $this->handleView($this->view($resume, Response::HTTP_OK));
        }

        return $this->handleView($this->view($form->getErrors(), Response::HTTP_BAD_REQUEST));
    }
}