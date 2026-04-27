<?php

namespace App\Service\SuperAdmin;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class SuperAdminService
{
    private const PRIVILEGED_ROLES = ['ROLE_MODERATOR', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN'];

    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $hasher
    ) {}

    public function createPrivilegedAccount(array $data): User
    {
        foreach (['email', 'password', 'firstname', 'lastname', 'role'] as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Le champ '$field' est requis");
            }
        }

        if (!in_array($data['role'], self::PRIVILEGED_ROLES)) {
            throw new \InvalidArgumentException('Rôle non autorisé');
        }

        if ($this->userRepository->findOneBy(['email' => $data['email']])) {
            throw new \InvalidArgumentException('Cet email est déjà utilisé');
        }

        $user = new User();
        $user->setEmail($data['email']);
        $user->setFirstname($data['firstname']);
        $user->setLastname($data['lastname']);
        $user->setRoles([$data['role']]);
        $user->setIsVerified(true);
        $user->setPassword($this->hasher->hashPassword($user, $data['password']));

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    public function getPrivilegedAccounts(): array
    {
        // Retourne tous les comptes non-citoyens
        return array_filter(
            $this->userRepository->findAll(),
            fn(User $u) => count(array_intersect($u->getRoles(), self::PRIVILEGED_ROLES)) > 0
        );
    }

    public function changeRole(int $id, string $role): User
    {
        if (!in_array($role, self::PRIVILEGED_ROLES)) {
            throw new \InvalidArgumentException('Rôle non autorisé');
        }

        $user = $this->userRepository->find($id);

        if (!$user) {
            throw new \Exception('Utilisateur introuvable');
        }

        $user->setRoles([$role]);
        $this->em->flush();

        return $user;
    }

    public function deleteAccount(int $id): void
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            throw new \Exception('Utilisateur introuvable');
        }

        $this->em->remove($user);
        $this->em->flush();
    }
}
