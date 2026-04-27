<?php

namespace App\Service\Auth;

use App\Entity\User;
use App\Entity\RefreshToken;

use App\Repository\UserRepository;
use App\Service\Security\FranceConnectProvider;
use App\Service\Security\JwtManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AuthService
{
    public function __construct(
        private EntityManagerInterface       $em,
        private UserPasswordHasherInterface  $hasher,
        private JwtManager                   $jwt,
        private FranceConnectProvider        $fcProvider,
        private UserRepository               $userRepository,
    ) {}

    // =========================================================
    //  AUTH MAISON
    // =========================================================

    /**
     * Inscription d'un citoyen (compte local)
     */
    public function register(array $data): array
    {
        $this->validateRegisterData($data);

        if ($this->userRepository->findOneBy(['email' => $data['email']])) {
            throw new \InvalidArgumentException('Cet email est déjà utilisé');
        }

        $user = new User();
        $user->setEmail($data['email']);
        $user->setFirstname($data['firstname']);
        $user->setLastname($data['lastname']);
        $user->setRoles(['ROLE_USER']);
        $user->setAuthProvider('local');
        $user->setIsVerified(false); // Email à vérifier si besoin
        $user->setPassword($this->hasher->hashPassword($user, $data['password']));

        $this->em->persist($user);
        $this->em->flush();

        return [
            'message' => 'Compte créé avec succès',
            'user'    => $this->serializeUser($user),
        ];
    }

    /**
     * Connexion via email + mot de passe
     */
    public function login(array $data): array
    {
        $user = $this->userRepository->findOneBy(['email' => $data['email'] ?? '']);

        if (!$user) {
            throw new \Exception('Identifiants invalides');
        }

        // Empêche la connexion locale sur un compte FranceConnect pur
        if ($user->isFranceConnectAccount() && $user->getPassword() === null) {
            throw new \Exception('Ce compte utilise FranceConnect. Connectez-vous via FranceConnect.');
        }

        if (!$this->hasher->isPasswordValid($user, $data['password'] ?? '')) {
            throw new \Exception('Identifiants invalides');
        }

        return $this->buildTokenResponse($user);
    }

    /**
     * Renouvellement du access_token via refresh_token
     */
    public function refresh(string $token): array
    {
        $refresh = $this->em->getRepository(RefreshToken::class)
            ->findOneBy(['token' => $token]);

        if (!$refresh || $refresh->isRevoked() || $refresh->getExpiresAt() < new \DateTimeImmutable()) {
            throw new \Exception('Refresh token invalide ou expiré');
        }

        $user = $this->userRepository->find($refresh->getUserId());

        if (!$user) {
            throw new \Exception('Utilisateur introuvable');
        }

        return ['access_token' => $this->jwt->createToken($user)];
    }

    /**
     * Déconnexion : révocation du refresh token
     */
    public function logout(string $token): void
    {
        $refresh = $this->em->getRepository(RefreshToken::class)
            ->findOneBy(['token' => $token]);

        if ($refresh) {
            $refresh->setRevoked(true);
            $this->em->flush();
        }
    }

    // =========================================================
    //  FRANCE CONNECT
    // =========================================================

    /**
     * Étape 1 : génère l'URL de redirection vers FranceConnect
     * Le front redirige l'utilisateur vers cette URL
     */
    public function getFranceConnectRedirectUrl(): array
    {
        // state : protection CSRF (à stocker en session ou vérifier côté front)
        $state = bin2hex(random_bytes(16));
        // nonce : protection replay attack
        $nonce = bin2hex(random_bytes(16));

        return [
            'url'   => $this->fcProvider->getAuthorizationUrl($state, $nonce),
            'state' => $state,
            'nonce' => $nonce,
        ];
    }

    /**
     * Étape 2 : traitement du callback FranceConnect
     * FranceConnect renvoie ?code=xxx&state=yyy
     */
    public function handleFranceConnectCallback(string $code, string $state, string $expectedState): array
    {
        // Vérification CSRF
        if (!hash_equals($expectedState, $state)) {
            throw new \Exception('State invalide — possible attaque CSRF');
        }

        // Échange code → tokens
        $tokens   = $this->fcProvider->getTokens($code);

        // Récupération du profil utilisateur
        $fcProfile = $this->fcProvider->getUserInfo($tokens['access_token']);

        // Trouve ou crée l'utilisateur
        $user = $this->findOrCreateFranceConnectUser($fcProfile);

        $response          = $this->buildTokenResponse($user);
        // On renvoie aussi le id_token FC pour la déconnexion FC
        $response['fc_id_token'] = $tokens['id_token'] ?? null;

        return $response;
    }

    /**
     * Déconnexion FranceConnect (nécessite le id_token FC)
     */
    public function getFranceConnectLogoutUrl(string $fcIdToken): array
    {
        $state = bin2hex(random_bytes(16));

        return [
            'url'   => $this->fcProvider->getLogoutUrl($fcIdToken, $state),
            'state' => $state,
        ];
    }

    // =========================================================
    //  PRIVÉ
    // =========================================================

    private function findOrCreateFranceConnectUser(array $fcProfile): User
    {
        // 1. Cherche par franceConnectId (sub)
        $user = $this->userRepository->findOneBy(['franceConnectId' => $fcProfile['sub']]);

        if ($user) {
            return $user; // Compte existant → connexion directe
        }

        // 2. Cherche par email (fusion de compte si même email)
        if (!empty($fcProfile['email'])) {
            $user = $this->userRepository->findOneBy(['email' => $fcProfile['email']]);

            if ($user) {
                // Lie le compte FranceConnect à ce compte existant
                $user->setFranceConnectId($fcProfile['sub']);
                $this->em->flush();
                return $user;
            }
        }

        // 3. Crée un nouveau compte citoyen
        $user = new User();
        $user->setEmail($fcProfile['email'] ?? $fcProfile['sub'] . '@franceconnect.fr');
        $user->setFirstname($fcProfile['given_name'] ?? '');
        $user->setLastname($fcProfile['family_name'] ?? '');
        $user->setRoles(['ROLE_USER']);
        $user->setFranceConnectId($fcProfile['sub']);
        $user->setAuthProvider('france_connect');
        $user->setIsVerified(true); // FranceConnect garantit l'identité
        // Pas de mot de passe pour les comptes FC purs

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function buildTokenResponse(User $user): array
    {
        $accessToken  = $this->jwt->createToken($user);
        $refreshToken = $this->createRefreshToken($user);

        return [
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'user'          => $this->serializeUser($user),
        ];
    }

    private function createRefreshToken(User $user): string
    {
        $token = bin2hex(random_bytes(64));

        $refresh = new RefreshToken();
        $refresh->setToken($token);
        $refresh->setUserId($user->getId());
        $refresh->setExpiresAt(new \DateTimeImmutable('+7 days'));
        $refresh->setRevoked(false);

        $this->em->persist($refresh);
        $this->em->flush();

        return $token;
    }

    private function validateRegisterData(array $data): void
    {
        foreach (['email', 'password', 'firstname', 'lastname'] as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Le champ '$field' est requis");
            }
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Email invalide');
        }

        if (strlen($data['password']) < 8) {
            throw new \InvalidArgumentException('Mot de passe trop court (minimum 8 caractères)');
        }
    }

    private function serializeUser(User $user): array
    {
        return [
            'id'           => $user->getId(),
            'email'        => $user->getEmail(),
            'firstname'    => $user->getFirstname(),
            'lastname'     => $user->getLastname(),
            'roles'        => $user->getRoles(),
            'authProvider' => $user->getAuthProvider(),
            'isVerified'   => $user->isVerified(),
        ];
    }
}
