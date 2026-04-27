<?php

namespace App\Controller;

use App\Service\Auth\AuthService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/auth')]
class AuthController extends AbstractController
{
    public function __construct(private AuthService $authService) {}

    // =========================================================
    //  AUTH MAISON
    // =========================================================

    /**
     * POST /api/auth/register
     * Inscription citoyen (public)
     * Body: { email, password, firstname, lastname }
     */
    #[Route('/register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        try {
            $result = $this->authService->register($data);
            return $this->json($result, Response::HTTP_CREATED);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * POST /api/auth/login
     * Connexion par email + mot de passe (public)
     * Body: { email, password }
     * Retourne: { access_token, refresh_token, user }
     */
    #[Route('/login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        try {
            return $this->json($this->authService->login($data));
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], JsonResponse::HTTP_UNAUTHORIZED);
        }
    }

    /**
     * POST /api/auth/refresh
     * Renouvelle le access_token (public)
     * Body: { refresh_token }
     * Retourne: { access_token }
     */
    #[Route('/refresh', methods: ['POST'])]
    public function refresh(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        try {
            return $this->json($this->authService->refresh($data['refresh_token'] ?? ''));
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNAUTHORIZED);
        }
    }

    /**
     * POST /api/auth/logout
     * Révocation du refresh token (connecté)
     * Body: { refresh_token }
     */
    #[Route('/logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $data = json_decode($request->getContent(), true);
        $this->authService->logout($data['refresh_token'] ?? '');

        return $this->json(['message' => 'Déconnecté avec succès']);
    }

    /**
     * GET /api/auth/me
     * Profil de l'utilisateur connecté
     */
    #[Route('/me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        return $this->json($this->getUser());
    }

    // =========================================================
    //  FRANCE CONNECT
    // =========================================================

    /**
     * GET /api/auth/france-connect
     * Étape 1 : récupère l'URL de redirection FranceConnect (public)
     * Le front redirige l'utilisateur vers response.url
     * Retourne: { url, state, nonce }
     */
    #[Route('/france-connect', methods: ['GET'])]
    public function franceConnectRedirect(): JsonResponse
    {
        try {
            $data = $this->authService->getFranceConnectRedirectUrl();
            return $this->json($data);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * GET /api/auth/france-connect/callback
     * Étape 2 : callback après authentification FranceConnect (public)
     * Query params: ?code=xxx&state=yyy
     * Header requis: X-FC-State: <state stocké par le front>
     * Retourne: { access_token, refresh_token, user, fc_id_token }
     */
    #[Route('/france-connect/callback', methods: ['GET'])]
    public function franceConnectCallback(Request $request): JsonResponse
    {
        $code          = $request->query->get('code');
        $state         = $request->query->get('state');
        // Le front envoie le state original dans un header pour vérification CSRF
        $expectedState = $request->headers->get('X-FC-State', '');

        if (!$code || !$state) {
            return $this->json(['error' => 'Paramètres manquants'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = $this->authService->handleFranceConnectCallback($code, $state, $expectedState);
            return $this->json($result);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNAUTHORIZED);
        }
    }

    /**
     * POST /api/auth/france-connect/logout
     * Déconnexion FranceConnect (connecté)
     * Body: { fc_id_token }
     * Retourne: { url } → le front redirige vers cette URL
     */
    #[Route('/france-connect/logout', methods: ['POST'])]
    public function franceConnectLogout(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $data = json_decode($request->getContent(), true);

        if (empty($data['fc_id_token'])) {
            return $this->json(['error' => 'fc_id_token requis'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = $this->authService->getFranceConnectLogoutUrl($data['fc_id_token']);
            return $this->json($result);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
