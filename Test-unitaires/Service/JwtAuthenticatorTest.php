<?php

namespace App\Tests\Service\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Security\JwtAuthenticator;
use App\Service\Security\JwtManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Tests unitaires pour JwtAuthenticator
 *
 * Couvre : supports(), authenticate(), onAuthenticationSuccess(),
 *          onAuthenticationFailure()
 */
class JwtAuthenticatorTest extends TestCase
{
    private JwtManager $jwtManager;
    private UserRepository $userRepository;
    private JwtAuthenticator $authenticator;

    protected function setUp(): void
    {
        $this->jwtManager     = $this->createMock(JwtManager::class);
        $this->userRepository = $this->createMock(UserRepository::class);

        $this->authenticator = new JwtAuthenticator(
            $this->jwtManager,
            $this->userRepository
        );
    }

    // ----------------------------------------------------------------
    //  supports()
    // ----------------------------------------------------------------

    public function testSupportsTrueWhenBearerTokenPresent(): void
    {
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer some_valid_token');

        $this->assertTrue($this->authenticator->supports($request));
    }

    public function testSupportsFalseWhenNoAuthorizationHeader(): void
    {
        $request = new Request();

        $this->assertFalse($this->authenticator->supports($request));
    }

    public function testSupportsFalseWhenBasicAuthHeader(): void
    {
        $request = new Request();
        $request->headers->set('Authorization', 'Basic dXNlcjpwYXNz');

        $this->assertFalse($this->authenticator->supports($request));
    }

    public function testSupportsFalseWhenAuthorizationHeaderIsEmpty(): void
    {
        $request = new Request();
        $request->headers->set('Authorization', '');

        $this->assertFalse($this->authenticator->supports($request));
    }

    public function testSupportsFalseWhenBearerWithoutSpace(): void
    {
        // "Bearer" collé, sans espace — ne doit pas déclencher l'authenticator
        $request = new Request();
        $request->headers->set('Authorization', 'Bearertoken');

        $this->assertFalse($this->authenticator->supports($request));
    }

    // ----------------------------------------------------------------
    //  authenticate()
    // ----------------------------------------------------------------

    public function testAuthenticateReturnsPassportOnValidToken(): void
    {
        $user = new User();
        $user->setEmail('alice@example.com');

        $this->jwtManager
            ->method('decode')
            ->willReturn([
                'sub'   => 1,
                'email' => 'alice@example.com',
                'roles' => ['ROLE_USER'],
                'iat'   => time(),
                'exp'   => time() + 3600,
            ]);

        $this->userRepository
            ->method('findOneBy')
            ->with(['email' => 'alice@example.com'])
            ->willReturn($user);

        $request = new Request();
        $request->headers->set('Authorization', 'Bearer valid.jwt.token');

        $passport = $this->authenticator->authenticate($request);

        $this->assertInstanceOf(SelfValidatingPassport::class, $passport);
    }

    public function testAuthenticateThrowsOnInvalidJwtSignature(): void
    {
        $this->jwtManager
            ->method('decode')
            ->willThrowException(new \Exception('Signature invalide'));

        $request = new Request();
        $request->headers->set('Authorization', 'Bearer tampered.jwt.token');

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('Signature invalide');

        $this->authenticator->authenticate($request);
    }

    public function testAuthenticateThrowsOnExpiredToken(): void
    {
        $this->jwtManager
            ->method('decode')
            ->willThrowException(new \Exception('Token expiré'));

        $request = new Request();
        $request->headers->set('Authorization', 'Bearer expired.jwt.token');

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('Token expiré');

        $this->authenticator->authenticate($request);
    }

    public function testAuthenticateThrowsOnMalformedToken(): void
    {
        $this->jwtManager
            ->method('decode')
            ->willThrowException(new \Exception('Token malformé'));

        $request = new Request();
        $request->headers->set('Authorization', 'Bearer malformed');

        $this->expectException(CustomUserMessageAuthenticationException::class);

        $this->authenticator->authenticate($request);
    }

    // ----------------------------------------------------------------
    //  onAuthenticationSuccess()
    // ----------------------------------------------------------------

    public function testOnAuthenticationSuccessReturnsNull(): void
    {
        $request = new Request();
        $token   = $this->createMock(TokenInterface::class);

        $response = $this->authenticator->onAuthenticationSuccess($request, $token, 'main');

        $this->assertNull($response, 'Succès : la requête doit continuer sans réponse précoce.');
    }

    // ----------------------------------------------------------------
    //  onAuthenticationFailure()
    // ----------------------------------------------------------------

    public function testOnAuthenticationFailureReturns401JsonResponse(): void
    {
        $request   = new Request();
        $exception = new AuthenticationException('Token invalide');

        $response = $this->authenticator->onAuthenticationFailure($request, $exception);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testOnAuthenticationFailureBodyContainsErrorKey(): void
    {
        $request   = new Request();
        $exception = new AuthenticationException('An authentication exception occurred.');

        $response = $this->authenticator->onAuthenticationFailure($request, $exception);
        $body     = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('error', $body, 'La réponse d\'erreur doit contenir la clé "error".');
    }
}
