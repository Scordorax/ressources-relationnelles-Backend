<?php

namespace App\Service\Auth;

use App\Entity\User;
use App\Entity\RefreshToken;
use App\Repository\UserRepository;
use App\Service\Security\JwtManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AuthService
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
        private JwtManager $jwt,
        private UserRepository $userRepository
    ) {}

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
        $user->setPassword($this->hasher->hashPassword($user, $data['password']));

        $this->em->persist($user);
        $this->em->flush();

        return [
            'message' => 'Compte créé avec succès',
            'user'    => $this->serializeUser($user),
        ];
    }

    public function login(array $data): array
    {
        $user = $this->userRepository->findOneBy(['email' => $data['email'] ?? '']);

        if (!$user || !$this->hasher->isPasswordValid($user, $data['password'] ?? '')) {
            throw new \Exception('Identifiants invalides');
        }

        $accessToken  = $this->jwt->createToken($user);
        $refreshToken = $this->createRefreshToken($user);

        return [
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'user'          => $this->serializeUser($user),
        ];
    }

    public function refresh(string $token): array
    {
        $refresh = $this->em->getRepository(RefreshToken::class)
            ->findOneBy(['token' => $token]);

        if (!$refresh || $refresh->isRevoked() || $refresh->getExpiresAt() < new \DateTimeImmutable()) {
            throw new \Exception('Refresh token invalide ou expiré');
        }

        $user = $this->em->getRepository(User::class)->find($refresh->getUserId());

        if (!$user) {
            throw new \Exception('Utilisateur introuvable');
        }

        return ['access_token' => $this->jwt->createToken($user)];
    }

    public function logout(string $token): void
    {
        $refresh = $this->em->getRepository(RefreshToken::class)
            ->findOneBy(['token' => $token]);

        if ($refresh) {
            $refresh->setRevoked(true);
            $this->em->flush();
        }
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
            throw new \InvalidArgumentException('Mot de passe trop court (min 8 caractères)');
        }
    }

    private function serializeUser(User $user): array
    {
        return [
            'id'        => $user->getId(),
            'email'     => $user->getEmail(),
            'firstname' => $user->getFirstname(),
            'lastname'  => $user->getLastname(),
            'roles'     => $user->getRoles(),
        ];
    }
}
