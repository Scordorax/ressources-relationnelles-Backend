<?php

namespace App\Controller;

use App\Service\SuperAdmin\SuperAdminService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/superadmin')]
#[IsGranted('ROLE_SUPER_ADMIN')]
class SuperAdminController extends AbstractController
{
    public function __construct(private SuperAdminService $service) {}

    // Créer un compte modérateur / admin / super-admin
    #[Route('/accounts', methods: ['POST'])]
    public function createAccount(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $allowedRoles = ['ROLE_MODERATOR', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN'];

        if (!isset($data['role']) || !in_array($data['role'], $allowedRoles)) {
            return $this->json(['error' => 'Rôle invalide'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $user = $this->service->createPrivilegedAccount($data);
            return $this->json($user, Response::HTTP_CREATED);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    // Lister tous les comptes privilégiés
    #[Route('/accounts', methods: ['GET'])]
    public function listAccounts(): JsonResponse
    {
        return $this->json($this->service->getPrivilegedAccounts());
    }

    // Modifier le rôle d'un compte
    #[Route('/accounts/{id}/role', methods: ['PUT'])]
    public function changeRole(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        try {
            $user = $this->service->changeRole($id, $data['role']);
            return $this->json($user);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    // Supprimer un compte privilégié
    #[Route('/accounts/{id}', methods: ['DELETE'])]
    public function deleteAccount(int $id): JsonResponse
    {
        $this->service->deleteAccount($id);
        return $this->json(['message' => 'Compte supprimé']);
    }
}
