<?php

namespace App\Tests\Service\SuperAdmin;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\SuperAdmin\SuperAdminService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Tests unitaires pour SuperAdminService
 *
 * Couvre : createPrivilegedAccount(), getPrivilegedAccounts(),
 *          changeRole(), deleteAccount()
 *
 * Les rôles autorisés sont : ROLE_MODERATOR, ROLE_ADMIN, ROLE_SUPER_ADMIN
 * ROLE_USER ne peut PAS être assigné via ce service.
 */
class SuperAdminServiceTest extends TestCase
{
    private EntityManagerInterface $em;
    private UserRepository $userRepository;
    private UserPasswordHasherInterface $hasher;
    private SuperAdminService $service;

    protected function setUp(): void
    {
        $this->em             = $this->createMock(EntityManagerInterface::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->hasher         = $this->createMock(UserPasswordHasherInterface::class);

        $this->service = new SuperAdminService(
            $this->em,
            $this->userRepository,
            $this->hasher
        );
    }

    // ----------------------------------------------------------------
    //  createPrivilegedAccount()
    // ----------------------------------------------------------------

    public function testCreatePrivilegedAccountSuccessForRoleAdmin(): void
    {
        $this->userRepository->method('findOneBy')->willReturn(null);
        $this->hasher->method('hashPassword')->willReturn('hashed_pass');
        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $user = $this->service->createPrivilegedAccount([
            'email'     => 'admin@example.com',
            'password'  => 'SecurePass123!',
            'firstname' => 'Admin',
            'lastname'  => 'Test',
            'role'      => 'ROLE_ADMIN',
        ]);

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('admin@example.com', $user->getEmail());
    }

    public function testCreatePrivilegedAccountSuccessForRoleModerator(): void
    {
        $this->userRepository->method('findOneBy')->willReturn(null);
        $this->hasher->method('hashPassword')->willReturn('h');
        $this->em->method('persist');
        $this->em->method('flush');

        $user = $this->service->createPrivilegedAccount([
            'email'     => 'mod@example.com',
            'password'  => 'SecurePass123!',
            'firstname' => 'Mod',
            'lastname'  => 'Erator',
            'role'      => 'ROLE_MODERATOR',
        ]);

        $this->assertContains('ROLE_MODERATOR', $user->getRoles());
    }

    public function testCreatePrivilegedAccountSuccessForRoleSuperAdmin(): void
    {
        $this->userRepository->method('findOneBy')->willReturn(null);
        $this->hasher->method('hashPassword')->willReturn('h');
        $this->em->method('persist');
        $this->em->method('flush');

        $user = $this->service->createPrivilegedAccount([
            'email'     => 'superadmin@example.com',
            'password'  => 'SecurePass123!',
            'firstname' => 'Super',
            'lastname'  => 'Admin',
            'role'      => 'ROLE_SUPER_ADMIN',
        ]);

        $this->assertContains('ROLE_SUPER_ADMIN', $user->getRoles());
    }

    public function testCreatePrivilegedAccountSetsIsVerifiedTrue(): void
    {
        $this->userRepository->method('findOneBy')->willReturn(null);
        $this->hasher->method('hashPassword')->willReturn('h');
        $this->em->method('persist');
        $this->em->method('flush');

        $user = $this->service->createPrivilegedAccount([
            'email'     => 'admin@example.com',
            'password'  => 'SecurePass123!',
            'firstname' => 'Admin',
            'lastname'  => 'Test',
            'role'      => 'ROLE_ADMIN',
        ]);

        $this->assertTrue($user->isVerified(), 'Les comptes privilégiés doivent être vérifiés dès la création.');
    }

    public function testCreatePrivilegedAccountCallsHashPassword(): void
    {
        $this->userRepository->method('findOneBy')->willReturn(null);
        $this->hasher
            ->expects($this->once())
            ->method('hashPassword')
            ->willReturn('hashed_secure_pass');
        $this->em->method('persist');
        $this->em->method('flush');

        $this->service->createPrivilegedAccount([
            'email'     => 'admin@example.com',
            'password'  => 'SecurePass123!',
            'firstname' => 'Admin',
            'lastname'  => 'Test',
            'role'      => 'ROLE_ADMIN',
        ]);
    }

    public function testCreatePrivilegedAccountThrowsWhenEmailMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Le champ 'email' est requis");

        $this->service->createPrivilegedAccount([
            'password'  => 'pass',
            'firstname' => 'Admin',
            'lastname'  => 'Test',
            'role'      => 'ROLE_ADMIN',
        ]);
    }

    public function testCreatePrivilegedAccountThrowsWhenPasswordMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Le champ 'password' est requis");

        $this->service->createPrivilegedAccount([
            'email'     => 'admin@example.com',
            'firstname' => 'Admin',
            'lastname'  => 'Test',
            'role'      => 'ROLE_ADMIN',
        ]);
    }

    public function testCreatePrivilegedAccountThrowsWhenRoleMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Le champ 'role' est requis");

        $this->service->createPrivilegedAccount([
            'email'     => 'admin@example.com',
            'password'  => 'pass',
            'firstname' => 'Admin',
            'lastname'  => 'Test',
        ]);
    }

    public function testCreatePrivilegedAccountThrowsWhenRoleIsRoleUser(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Rôle non autorisé');

        $this->service->createPrivilegedAccount([
            'email'     => 'user@example.com',
            'password'  => 'pass',
            'firstname' => 'User',
            'lastname'  => 'Test',
            'role'      => 'ROLE_USER', // non autorisé via ce service
        ]);
    }

    public function testCreatePrivilegedAccountThrowsWhenRoleIsUnknown(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Rôle non autorisé');

        $this->service->createPrivilegedAccount([
            'email'     => 'hack@example.com',
            'password'  => 'pass',
            'firstname' => 'H',
            'lastname'  => 'H',
            'role'      => 'ROLE_HACKER',
        ]);
    }

    public function testCreatePrivilegedAccountThrowsWhenEmailAlreadyUsed(): void
    {
        $existing = new User();
        $this->userRepository->method('findOneBy')->willReturn($existing);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cet email est déjà utilisé');

        $this->service->createPrivilegedAccount([
            'email'     => 'existing@example.com',
            'password'  => 'pass',
            'firstname' => 'Admin',
            'lastname'  => 'Test',
            'role'      => 'ROLE_ADMIN',
        ]);
    }

    // ----------------------------------------------------------------
    //  getPrivilegedAccounts()
    // ----------------------------------------------------------------

    public function testGetPrivilegedAccountsFiltersOutCitizens(): void
    {
        $admin = new User();
        $admin->setRoles(['ROLE_ADMIN']);

        $mod = new User();
        $mod->setRoles(['ROLE_MODERATOR']);

        $citizen = new User();
        $citizen->setRoles(['ROLE_USER']);

        $this->userRepository->method('findAll')->willReturn([$admin, $mod, $citizen]);

        $result = array_values($this->service->getPrivilegedAccounts());

        $this->assertCount(2, $result, 'Seuls les comptes avec rôle privilégié doivent être retournés.');
    }

    public function testGetPrivilegedAccountsIncludesAllPrivilegedRoles(): void
    {
        $admin = new User();
        $admin->setRoles(['ROLE_ADMIN']);

        $mod = new User();
        $mod->setRoles(['ROLE_MODERATOR']);

        $superAdmin = new User();
        $superAdmin->setRoles(['ROLE_SUPER_ADMIN']);

        $this->userRepository->method('findAll')->willReturn([$admin, $mod, $superAdmin]);

        $result = $this->service->getPrivilegedAccounts();

        $this->assertCount(3, array_values($result));
    }

    public function testGetPrivilegedAccountsReturnsEmptyWhenNone(): void
    {
        $citizen = new User();
        $citizen->setRoles(['ROLE_USER']);

        $this->userRepository->method('findAll')->willReturn([$citizen]);

        $result = $this->service->getPrivilegedAccounts();

        $this->assertEmpty(array_values($result));
    }

    // ----------------------------------------------------------------
    //  changeRole()
    // ----------------------------------------------------------------

    public function testChangeRoleUpdatesUserRole(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_MODERATOR']);

        $this->userRepository->method('find')->with(1)->willReturn($user);
        $this->em->expects($this->once())->method('flush');

        $updated = $this->service->changeRole(1, 'ROLE_ADMIN');

        $this->assertContains('ROLE_ADMIN', $updated->getRoles());
    }

    public function testChangeRoleThrowsWhenRoleIsRoleUser(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Rôle non autorisé');

        $this->service->changeRole(1, 'ROLE_USER');
    }

    public function testChangeRoleThrowsWhenRoleIsUnknown(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Rôle non autorisé');

        $this->service->changeRole(1, 'ROLE_INVALID');
    }

    public function testChangeRoleThrowsWhenUserNotFound(): void
    {
        $this->userRepository->method('find')->willReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Utilisateur introuvable');

        $this->service->changeRole(999, 'ROLE_ADMIN');
    }

    public function testChangeRoleReturnsUpdatedUser(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_MODERATOR']);

        $this->userRepository->method('find')->willReturn($user);
        $this->em->method('flush');

        $result = $this->service->changeRole(1, 'ROLE_ADMIN');

        $this->assertInstanceOf(User::class, $result);
    }

    // ----------------------------------------------------------------
    //  deleteAccount()
    // ----------------------------------------------------------------

    public function testDeleteAccountRemovesAndFlushes(): void
    {
        $user = new User();

        $this->userRepository->method('find')->with(5)->willReturn($user);
        $this->em->expects($this->once())->method('remove')->with($user);
        $this->em->expects($this->once())->method('flush');

        $this->service->deleteAccount(5);
    }

    public function testDeleteAccountThrowsWhenUserNotFound(): void
    {
        $this->userRepository->method('find')->willReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Utilisateur introuvable');

        $this->service->deleteAccount(999);
    }

    public function testDeleteAccountReturnsVoid(): void
    {
        $user = new User();

        $this->userRepository->method('find')->willReturn($user);
        $this->em->method('remove');
        $this->em->method('flush');

        $result = $this->service->deleteAccount(1);

        $this->assertNull($result);
    }
}
