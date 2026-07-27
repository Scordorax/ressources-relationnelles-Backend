<?php

namespace App\Tests\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour l'entité User
 *
 * L'entité User porte la logique de rôles utilisée par le pare-feu
 * Symfony (role_hierarchy) et par les guards Angular. Une régression
 * sur getRoles() ouvrirait un risque d'élévation de privilège (R4).
 */
class UserTest extends TestCase
{
    // ----------------------------------------------------------------
    //  Construction
    // ----------------------------------------------------------------

    public function testNewUserIsNotVerifiedByDefault(): void
    {
        $user = new User();

        $this->assertFalse($user->isVerified());
    }

    public function testNewUserHasCreationTimestamp(): void
    {
        $user = new User();

        $this->assertInstanceOf(\DateTimeImmutable::class, $user->getCreatedAt());
    }

    public function testNewUserDefaultsToLocalAuthProvider(): void
    {
        $user = new User();

        $this->assertSame('local', $user->getAuthProvider());
        $this->assertFalse($user->isFranceConnectAccount());
    }

    public function testNewUserHasNoIdBeforePersistence(): void
    {
        $this->assertNull((new User())->getId());
    }

    // ----------------------------------------------------------------
    //  Rôles — cœur du contrôle d'accès
    // ----------------------------------------------------------------

    public function testEveryUserAlwaysHasRoleUser(): void
    {
        $user = new User();

        $this->assertContains('ROLE_USER', $user->getRoles());
    }

    public function testRoleUserIsAddedEvenWhenRolesAreSetExplicitly(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_ADMIN']);

        $roles = $user->getRoles();

        $this->assertContains('ROLE_ADMIN', $roles);
        $this->assertContains('ROLE_USER', $roles);
    }

    public function testRolesAreDeduplicated(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_USER', 'ROLE_USER', 'ROLE_ADMIN']);

        $roles = $user->getRoles();

        $this->assertCount(2, $roles);
    }

    public function testSetRolesReplacesPreviousRoles(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_ADMIN']);
        $user->setRoles(['ROLE_MODERATOR']);

        $roles = $user->getRoles();

        $this->assertContains('ROLE_MODERATOR', $roles);
        $this->assertNotContains('ROLE_ADMIN', $roles);
    }

    public function testSuperAdminRoleIsPreserved(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_SUPER_ADMIN']);

        $this->assertContains('ROLE_SUPER_ADMIN', $user->getRoles());
    }

    public function testEmptyRolesStillYieldsRoleUser(): void
    {
        $user = new User();
        $user->setRoles([]);

        $this->assertSame(['ROLE_USER'], array_values($user->getRoles()));
    }

    // ----------------------------------------------------------------
    //  Identité
    // ----------------------------------------------------------------

    public function testUserIdentifierIsTheEmail(): void
    {
        $user = new User();
        $user->setEmail('citoyen@example.fr');

        $this->assertSame('citoyen@example.fr', $user->getUserIdentifier());
    }

    public function testUserIdentifierIsEmptyStringWhenEmailIsNull(): void
    {
        $this->assertSame('', (new User())->getUserIdentifier());
    }

    public function testAccessorsAreFluent(): void
    {
        $user = new User();

        $result = $user
            ->setEmail('a@b.fr')
            ->setFirstname('Marie')
            ->setLastname('Dupont');

        $this->assertSame($user, $result);
        $this->assertSame('Marie', $user->getFirstname());
        $this->assertSame('Dupont', $user->getLastname());
    }

    // ----------------------------------------------------------------
    //  Mot de passe
    // ----------------------------------------------------------------

    public function testPasswordCanBeNullForFranceConnectAccounts(): void
    {
        $user = new User();
        $user->setPassword(null);

        $this->assertNull($user->getPassword());
    }

    public function testPasswordStoresTheHashedValue(): void
    {
        $user = new User();
        $hash = '$2y$13$abcdefghijklmnopqrstuv';
        $user->setPassword($hash);

        $this->assertSame($hash, $user->getPassword());
    }

    public function testEraseCredentialsDoesNotThrow(): void
    {
        $user = new User();
        $user->setPassword('hash');

        $user->eraseCredentials();

        $this->addToAssertionCount(1);
    }

    // ----------------------------------------------------------------
    //  FranceConnect
    // ----------------------------------------------------------------

    public function testFranceConnectAccountIsDetected(): void
    {
        $user = new User();
        $user->setAuthProvider('france_connect');
        $user->setFranceConnectId('sub-oidc-123');

        $this->assertTrue($user->isFranceConnectAccount());
        $this->assertSame('sub-oidc-123', $user->getFranceConnectId());
    }

    public function testLocalAccountIsNotFranceConnect(): void
    {
        $user = new User();
        $user->setAuthProvider('local');

        $this->assertFalse($user->isFranceConnectAccount());
    }

    public function testFranceConnectIdIsNullForLocalAccounts(): void
    {
        $this->assertNull((new User())->getFranceConnectId());
    }

    // ----------------------------------------------------------------
    //  Vérification du compte
    // ----------------------------------------------------------------

    public function testAccountCanBeMarkedAsVerified(): void
    {
        $user = new User();
        $user->setIsVerified(true);

        $this->assertTrue($user->isVerified());
    }

    public function testAccountCanBeUnverified(): void
    {
        $user = new User();
        $user->setIsVerified(true);
        $user->setIsVerified(false);

        $this->assertFalse($user->isVerified());
    }
}
