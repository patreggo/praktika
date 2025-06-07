<?php

namespace Api;

use App\Entity\User;
use App\Entity\UserRoles;
use App\Form\ApiRegistrationFormType;
use App\Repository\UserRepository;
use App\Repository\UserRolesRepository;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class UserApiController
 * @package Api\Controller
 */
#[Route('/api')]
class UserApiController extends AbstractFOSRestController
{
    #[Rest\Post('/registration', name: 'api_registration', methods: ['POST'])]
    public function userRegistration(
        Request                $request,
        UserRolesRepository    $userRolesRepository,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
    ): Response
    {
        $user = new User();

        $form = $this->createForm(ApiRegistrationFormType::class, $user);
        $data = json_decode($request->getContent(), true);
        $form->submit($data);
        if ($form->isSubmitted() && $form->isValid()) {
            $hashedPassword = $passwordHasher->hashPassword($user, $user->getPassword());
            $user->setPassword($hashedPassword);
            $entityManager->persist($user);
            $entityManager->flush();
            return $this->handleView($this->view($user, Response::HTTP_CREATED));
        }
        return $this->handleView($this->view($form->getErrors(), Response::HTTP_BAD_REQUEST));
    }

    #[Rest\Get('/auth_user', name: 'get_auth_user', methods: ['GET'])]
    public function getAuthUserInfo(
        UserRepository $repository,
    ): Response
    {
        $user = $this->container->get('security.token_storage')->getToken()->getUser();
        return $this->handleView($this->view($user, Response::HTTP_OK));
    }

    #[Rest\Get('/user_roles', name: 'api_auth_user', methods: ['GET'])]
    public function getUserRoles(UserRolesRepository $repository
    ): Response
    {
        $data = $repository->findAll();
        return $this->handleView($this->view($data, Response::HTTP_OK));
    }

}
