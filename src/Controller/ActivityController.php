<?php

namespace App\Controller;

use App\Service\Activity\ActivityService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/activities')]
class ActivityController extends AbstractController
{
    public function __construct(private ActivityService $service) {}

    // CONNECTÉ — liste des activités/jeux disponibles
    #[Route('', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        return $this->json($this->service->getAll());
    }

    // CONNECTÉ — démarrer une activité
    #[Route('/{id}/start', methods: ['POST'])]
    public function start(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        try {
            $result = $this->service->start($id, $this->getUser());
            return $this->json($result);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }
}
