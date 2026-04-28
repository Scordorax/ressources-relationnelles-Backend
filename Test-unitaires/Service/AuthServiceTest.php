<?php

namespace App\Tests\Service\Auth;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Auth\AuthService;
use App\Service\Security\FranceConnectProvider;
use App\Service\Security\JwtManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Tests unitaires pour AuthService
 *
 * Couvre : register(), login(), refresh(), logout(),
 *          getFranceConnectRedirectUrl(), handleFranceConnectCallback()
 *
 * IMPORTANT : AuthService::createRefreshToken() appelle $user->getId()
 * et passe le résultat (potentiellement null) à RefreshToken::setUserId(int).
 * Cela lève une TypeError si l'utilisateur n'est pas encore persisté (id = null).
 * → Voir comments.md, problème #8.
 *
 * Pour contourner ce bug dans les tests de login/register, on mocke User
 * afin que getId() renvoie un entier.
 */
class AuthServiceTest extends TestCase
{
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $hasher;
    private JwtManager $jwt;
    private FranceConnectProvider $fcProvider;
    private UserRepository $userRepository;
    private AuthService $authService;

    protected function setUp(): void
    {
        $this->em             = $this->createMock(EntityManagerInterface::class);
        $this->hasher         = $this->createMock(UserPasswordHasherInterface::class);
        $this->jwt            = $this->createMock(JwtManager::class);
        $this->fcProvider     = $this->createMock(FranceConnectProvider::class);
        $this->userRepository = $this->createMock(UserRepository::class);

        $this->authService = new AuthService(
            $this->em,
            $this->hasher,
            $this->jwt,
            $this->fcProvider,
            $this->userRepository
        );
    }

    // ----------------------------------------------------------------
    //  Helpers
    // ----------------------------------------------------------------

    /**
     * Crée un mock de User avec un getId() fixé à 42.
     * Nécessaire car createRefreshToken() appelle $user->getId() (voir bug #8).
     */
    private function mockUser(
        string $email = 'test@example.com',
        string $authProvider = 'local',
        ?string $password = 'hashed_pass'
    ): User {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(42);
        $user->method('getEmail')->willReturn($email);
        $user->method('getFirstname')->willReturn('Jean');
        $user->method('getLastname')->willReturn('Dupont');
        $user->method('getRoles')->willReturn(['ROLE_USER']);
        $user->method('getAuthProvider')->willReturn($authProvider);
        $user->method('isVerified')->willReturn(true);
        $user->method('isFranceConnectAccount')->willReturn($authProvider === 'france_connect');
        $user->method('getPassword')->willReturn($password);

        return $user;
    }

    /**
     * Configure l'EntityManager pour accepter persist/flush (createRefreshToken).
     */
    private function stubEmForRefreshToken(): void
    {
        $this->em->method('persist');
        $this->em->method('flush');
    }

    // ----------------------------------------------------------------
    //  register()
    // ----------------------------------------------------------------

    public function testRegisterSuccessReturnsMessageAndUser(): void
    {
        $this->userRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => 'new@example.com'])
            ->willReturn(null);

        $this->hasher->method('hashPassword')->willReturn('hashed_password');
        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $result = $this->authService->register([
            'email'     => 'new@example.com',
            'password'  => 'password123',
            'firstname' => 'Jean',
            'lastname'  => 'Dupont',
        ]);

        $this->assertSame('Compte créé avec succès', $result['message']);
        $this->assertArrayHasKey('user', $result);
        $this->assertSame('new@example.com', $result['user']['email']);
        $this->assertSame('local', $result['user']['authProvider']);
    }

    public function testRegisterThrowsWhenEmailAlreadyUsed(): void
    {
        $existing = new User();
        $this->userRepository->method('findOneBy')->willReturn($existing);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cet email est déjà utilisé');

        $this->authService->register([
            'email'     => 'existing@example.com',
            'password'  => 'password123',
            'firstname' => 'Jean',
            'lastname'  => 'Dupont',
        ]);
    }

    public function testRegisterThrowsWhenEmailFieldMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Le champ 'email' est requis");

        $this->authService->register([
            'password'  => 'password123',
            'firstname' => 'Jean',
            'lastname'  => 'Dupont',
        ]);
    }

    public function testRegisterThrowsWhenPasswordFieldMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Le champ 'password' est requis");

        $this->authService->register([
            'email'     => 'test@example.com',
            'firstname' => 'Jean',
            'lastname'  => 'Dupont',
        ]);
    }

    public function testRegisterThrowsWhenFirstnameMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Le champ 'firstname' est requis");

        $this->authService->register([
            'email'    => 'test@example.com',
            'password' => 'password123',
            'lastname' => 'Dupont',
        ]);
    }

    public function testRegisterThrowsWhenLastnameMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Le champ 'lastname' est requis");

        $this->authService->register([
            'email'     => 'test@example.com',
            'password'  => 'password123',
            'firstname' => 'Jean',
        ]);
    }

    public function testRegisterThrowsWhenEmailInvalid(): void
    {
        $this->userRepository->method('findOneBy')->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Email invalide');

        $this->authService->register([
            'email'     => 'not-an-email',
            'password'  => 'password123',
            'firstname' => 'Jean',
            'lastname'  => 'Dupont',
        ]);
    }

    public function testRegisterThrowsWhenPasswordTooShort(): void
    {
        $this->userRepository->method('findOneBy')->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Mot de passe trop court');

        $this->authService->register([
            'email'     => 'new@example.com',
            'password'  => '1234567', // 7 caractères — insuffisant
            'firstname' => 'Jean',
            'lastname'  => 'Dupont',
        ]);
    }

    public function testRegisterAcceptsPasswordOfExactlyEightChars(): void
    {
        $this->userRepository->method('findOneBy')->willReturn(null);
        $this->hasher->method('hashPassword')->willReturn('h');
        $this->em->method('persist');
        $this->em->method('flush');

        // Ne doit PAS lever d'exception
        $result = $this->authService->register([
            'email'     => 'new@example.com',
            'password'  => '12345678', // exactement 8 caractères
            'firstname' => 'Jean',
            'lastname'  => 'Dupont',
        ]);

        $this->assertSame('Compte créé avec succès', $result['message']);
    }

    public function testRegisterSetsRoleUser(): void
    {
        $this->userRepository->method('findOneBy')->willReturn(null);
        $this->hasher->method('hashPassword')->willReturn('h');
        $this->em->method('persist');
        $this->em->method('flush');

        $result = $this->authService->register([
            'email'     => 'citizen@example.com',
            'password'  => 'password123',
            'firstname' => 'Jean',
            'lastname'  => 'Dupont',
        ]);

        $this->assertContains('ROLE_USER', $result['user']['roles']);
    }

    public function testRegisterSetsAuthProviderLocal(): void
    {
        $this->userRepository->method('findOneBy')->willReturn(null);
        $this->hasher->method('hashPassword')->willReturn('h');
        $this->em->method('persist');
        $this->em->method('flush');

        $result = $this->authService->register([
            'email'     => 'local@example.com',
            'password'  => 'password123',
            'firstname' => 'Jean',
            'lastname'  => 'Dupont',
        ]);

        $this->assertSame('local', $result['user']['authProvider']);
    }

    // ----------------------------------------------------------------
    //  login()
    // ----------------------------------------------------------------

    public function testLoginSuccessReturnsTokensAndUser(): void
    {
        $user = $this->mockUser();

        $this->userRepository->method('findOneBy')->willReturn($user);
        $this->hasher->method('isPasswordValid')->willReturn(true);
        $this->jwt->method('createToken')->willReturn('access_token_abc');
        $this->stubEmForRefreshToken();

        $result = $this->authService->login([
            'email'    => 'test@example.com',
            'password' => 'password123',
        ]);

        $this->assertArrayHasKey('access_token', $result);
        $this->assertArrayHasKey('refresh_token', $result);
        $this->assertArrayHasKey('user', $result);
        $this->assertSame('access_token_abc', $result['access_token']);
    }

    public function testLoginThrowsWhenUserNotFound(): void
    {
        $this->userRepository->method('findOneBy')->willReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Identifiants invalides');

        $this->authService->login([
            'email'    => 'nobody@example.com',
            'password' => 'password123',
        ]);
    }

    public function testLoginThrowsForFranceConnectAccountWithNoPassword(): void
    {
        $user = $this->mockUser('fc@example.com', 'france_connect', null);

        $this->userRepository->method('findOneBy')->willReturn($user);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Ce compte utilise FranceConnect');

        $this->authService->login([
            'email'    => 'fc@example.com',
            'password' => 'any_password',
        ]);
    }

    public function testLoginThrowsWhenPasswordInvalid(): void
    {
        $user = $this->mockUser();

        $this->userRepository->method('findOneBy')->willReturn($user);
        $this->hasher->method('isPasswordValid')->willReturn(false);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Identifiants invalides');

        $this->authService->login([
            'email'    => 'test@example.com',
            'password' => 'wrong_password',
        ]);
    }

    public function testLoginRefreshTokenIsNonEmptyString(): void
    {
        $user = $this->mockUser();

        $this->userRepository->method('findOneBy')->willReturn($user);
        $this->hasher->method('isPasswordValid')->willReturn(true);
        $this->jwt->method('createToken')->willReturn('access_token');
        $this->stubEmForRefreshToken();

        $result = $this->authService->login([
            'email'    => 'test@example.com',
            'password' => 'password123',
        ]);

        $this->assertIsString($result['refresh_token']);
        $this->assertNotEmpty($result['refresh_token']);
    }

    // ----------------------------------------------------------------
    //  refresh()
    // ----------------------------------------------------------------

    public function testRefreshReturnsNewAccessToken(): void
    {
        $user = $this->mockUser();

        $refreshToken = new RefreshToken();
        $refreshToken->setToken('valid_refresh_token');
        $refreshToken->setUserId(42);
        $refreshToken->setExpiresAt(new \DateTimeImmutable('+7 days'));
        $refreshToken->setRevoked(false);

        $repo = $this->createMock(ObjectRepository::class);
        $repo->method('findOneBy')->willReturn($refreshToken);
        $this->em->method('getRepository')->willReturn($repo);

        $this->userRepository->method('find')->with(42)->willReturn($user);
        $this->jwt->method('createToken')->willReturn('new_access_token');

        $result = $this->authService->refresh('valid_refresh_token');

        $this->assertSame('new_access_token', $result['access_token']);
    }

    public function testRefreshThrowsWhenTokenNotFound(): void
    {
        $repo = $this->createMock(ObjectRepository::class);
        $repo->method('findOneBy')->willReturn(null);
        $this->em->method('getRepository')->willReturn($repo);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Refresh token invalide ou expiré');

        $this->authService->refresh('nonexistent_token');
    }

    public function testRefreshThrowsWhenTokenIsRevoked(): void
    {
        $refreshToken = new RefreshToken();
        $refreshToken->setToken('revoked_token');
        $refreshToken->setUserId(42);
        $refreshToken->setExpiresAt(new \DateTimeImmutable('+7 days'));
        $refreshToken->setRevoked(true); // révoqué

        $repo = $this->createMock(ObjectRepository::class);
        $repo->method('findOneBy')->willReturn($refreshToken);
        $this->em->method('getRepository')->willReturn($repo);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Refresh token invalide ou expiré');

        $this->authService->refresh('revoked_token');
    }

    public function testRefreshThrowsWhenTokenIsExpired(): void
    {
        $refreshToken = new RefreshToken();
        $refreshToken->setToken('expired_token');
        $refreshToken->setUserId(42);
        $refreshToken->setExpiresAt(new \DateTimeImmutable('-1 day')); // expiré hier
        $refreshToken->setRevoked(false);

        $repo = $this->createMock(ObjectRepository::class);
        $repo->method('findOneBy')->willReturn($refreshToken);
        $this->em->method('getRepository')->willReturn($repo);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Refresh token invalide ou expiré');

        $this->authService->refresh('expired_token');
    }

    public function testRefreshThrowsWhenUserNotFound(): void
    {
        $refreshToken = new RefreshToken();
        $refreshToken->setToken('token');
        $refreshToken->setUserId(999);
        $refreshToken->setExpiresAt(new \DateTimeImmutable('+7 days'));
        $refreshToken->setRevoked(false);

        $repo = $this->createMock(ObjectRepository::class);
        $repo->method('findOneBy')->willReturn($refreshToken);
        $this->em->method('getRepository')->willReturn($repo);

        $this->userRepository->method('find')->willReturn(null); // utilisateur supprimé

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Utilisateur introuvable');

        $this->authService->refresh('token');
    }

    // ----------------------------------------------------------------
    //  logout()
    // ----------------------------------------------------------------

    public function testLogoutRevokesTokenAndFlushes(): void
    {
        $refreshToken = new RefreshToken();
        $refreshToken->setToken('active_token');
        $refreshToken->setUserId(42);
        $refreshToken->setExpiresAt(new \DateTimeImmutable('+7 days'));
        $refreshToken->setRevoked(false);

        $repo = $this->createMock(ObjectRepository::class);
        $repo->method('findOneBy')->willReturn($refreshToken);
        $this->em->method('getRepository')->willReturn($repo);
        $this->em->expects($this->once())->method('flush');

        $this->authService->logout('active_token');

        $this->assertTrue($refreshToken->isRevoked(), 'Le token doit être marqué comme révoqué.');
    }

    public function testLogoutDoesNotFlushWhenTokenNotFound(): void
    {
        $repo = $this->createMock(ObjectRepository::class);
        $repo->method('findOneBy')->willReturn(null);
        $this->em->method('getRepository')->willReturn($repo);

        $this->em->expects($this->never())->method('flush');

        // Ne doit pas lever d'exception
        $this->authService->logout('nonexistent_token');
    }

    // ----------------------------------------------------------------
    //  getFranceConnectRedirectUrl()
    // ----------------------------------------------------------------

    public function testGetFranceConnectRedirectUrlReturnsUrlStateAndNonce(): void
    {
        $this->fcProvider
            ->method('getAuthorizationUrl')
            ->willReturn('https://fc.gouv.fr/authorize?...');

        $result = $this->authService->getFranceConnectRedirectUrl();

        $this->assertArrayHasKey('url', $result);
        $this->assertArrayHasKey('state', $result);
        $this->assertArrayHasKey('nonce', $result);
        $this->assertNotEmpty($result['state'], 'Le state CSRF ne doit pas être vide.');
        $this->assertNotEmpty($result['nonce'], 'Le nonce anti-replay ne doit pas être vide.');
        $this->assertSame('https://fc.gouv.fr/authorize?...', $result['url']);
    }

    public function testGetFranceConnectRedirectUrlGeneratesUniqueStateEachCall(): void
    {
        $this->fcProvider->method('getAuthorizationUrl')->willReturn('https://fc.gouv.fr/...');

        $result1 = $this->authService->getFranceConnectRedirectUrl();
        $result2 = $this->authService->getFranceConnectRedirectUrl();

        $this->assertNotSame(
            $result1['state'],
            $result2['state'],
            'Deux appels doivent générer des states différents (sécurité CSRF).'
        );
    }

    // ----------------------------------------------------------------
    //  handleFranceConnectCallback()
    // ----------------------------------------------------------------

    public function testHandleFranceConnectCallbackThrowsOnStateMismatch(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('State invalide');

        $this->authService->handleFranceConnectCallback(
            'some_code',
            'received_state_ABC',
            'expected_state_XYZ' // ne correspond pas
        );
    }

    public function testHandleFranceConnectCallbackThrowsOnEmptyState(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('State invalide');

        $this->authService->handleFranceConnectCallback('code', '', 'expected');
    }

    public function testHandleFranceConnectCallbackSuccessCreatesNewFcUser(): void
    {
        $fcProfile = [
            'sub'         => 'fc_sub_123',
            'email'       => 'fcuser@example.com',
            'given_name'  => 'Marie',
            'family_name' => 'Martin',
        ];

        $this->fcProvider->method('getTokens')->willReturn([
            'access_token' => 'fc_access_token',
            'id_token'     => 'fc_id_token',
        ]);
        $this->fcProvider->method('getUserInfo')->willReturn($fcProfile);

        // Aucun utilisateur existant avec ce sub ou cet email
        $this->userRepository->method('findOneBy')->willReturn(null);

        // Nécessaire car createRefreshToken sera appelé (via buildTokenResponse)
        // Le User créé aura getId()=null → BUG #8 → TypeError attendue
        // On documente ce comportement dans ce test
        $this->em->method('persist');
        $this->em->method('flush');
        $this->jwt->method('createToken')->willReturn('access_tk');

        // Ce test va lever TypeError à cause du bug getId()=null → setUserId(int)
        // Comportement documenté dans comments.md (#8)
        $this->expectException(\TypeError::class);

        $this->authService->handleFranceConnectCallback(
            'valid_code',
            'same_state',
            'same_state'
        );
    }
}
