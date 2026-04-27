<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/users')]
class UserController extends AbstractController
{
    // CONNECTÉ — profil de l'utilisateur courant
    #[Route('/profile', methods: ['GET'])]
    public function profile(): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        return $this->json($this->getUser());
    }
}
