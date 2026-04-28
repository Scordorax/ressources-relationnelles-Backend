<?php

namespace App\Tests\Service\Security;

use App\Entity\User;
use App\Service\Security\JwtManager;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour JwtManager
 *
 * Couvre : createToken(), decode()
 * Cas testés : token valide, token malformé, signature invalide,
 *              token expiré, payload falsifié, contenu du payload.
 *
 * NOTE : JwtManager est une implémentation JWT MAISON.
 * Voir comments.md pour les problèmes de sécurité associés.
 */
class JwtManagerTest extends TestCase
{
    private JwtManager $jwtManager;
    private string $secret = 'test_secret_key_for_unit_tests_minimum32chars';

    protected function setUp(): void
    {
        $this->jwtManager = new JwtManager($this->secret);
    }

    // ----------------------------------------------------------------
    //  Helpers
    // ----------------------------------------------------------------

    private function buildUser(string $email = 'test@example.com', array $roles = ['ROLE_USER']): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setFirstname('Jean');
        $user->setLastname('Dupont');
        $user->setRoles($roles);

        return $user;
    }

    /**
     * Construit manuellement un JWT signé avec le secret du test.
     */
    private function buildToken(array $payload): string
    {
        $b64 = fn(string $d) => rtrim(strtr(base64_encode($d), '+/', '-_'), '=');

        $header  = $b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $body    = $b64(json_encode($payload));
        $sig     = $b64(hash_hmac('sha256', "$header.$body", $this->secret, true));

        return "$header.$body.$sig";
    }

    // ----------------------------------------------------------------
    //  createToken()
    // ----------------------------------------------------------------

    public function testCreateTokenReturnsStringWithThreeParts(): void
    {
        $user  = $this->buildUser();
        $token = $this->jwtManager->createToken($user);

        $this->assertIsString($token);
        $this->assertCount(3, explode('.', $token), 'Un JWT doit avoir exactement 3 parties séparées par des points.');
    }

    public function testCreateTokenPayloadContainsEmail(): void
    {
        $user    = $this->buildUser('alice@example.com');
        $token   = $this->jwtManager->createToken($user);
        $payload = $this->jwtManager->decode($token);

        $this->assertSame('alice@example.com', $payload['email']);
    }

    public function testCreateTokenPayloadContainsRoles(): void
    {
        $user    = $this->buildUser('bob@example.com', ['ROLE_ADMIN']);
        $token   = $this->jwtManager->createToken($user);
        $payload = $this->jwtManager->decode($token);

        $this->assertContains('ROLE_ADMIN', $payload['roles']);
    }

    public function testCreateTokenPayloadContainsIatAndExp(): void
    {
        $user    = $this->buildUser();
        $before  = time();
        $token   = $this->jwtManager->createToken($user);
        $after   = time();
        $payload = $this->jwtManager->decode($token);

        $this->assertArrayHasKey('iat', $payload, 'Le payload doit contenir iat (issued at).');
        $this->assertArrayHasKey('exp', $payload, 'Le payload doit contenir exp (expiration).');

        // L'expiration doit être ~1h dans le futur
        $this->assertGreaterThanOrEqual($before + 3600, $payload['exp']);
        $this->assertLessThanOrEqual($after + 3600, $payload['exp']);
    }

    public function testCreateTokenPayloadContainsSub(): void
    {
        $user    = $this->buildUser();
        $token   = $this->jwtManager->createToken($user);
        $payload = $this->jwtManager->decode($token);

        $this->assertArrayHasKey('sub', $payload, 'Le payload doit contenir sub (user id).');
    }

    // ----------------------------------------------------------------
    //  decode() — cas valides
    // ----------------------------------------------------------------

    public function testDecodeValidTokenReturnsPayload(): void
    {
        $user    = $this->buildUser('carol@example.com');
        $token   = $this->jwtManager->createToken($user);
        $payload = $this->jwtManager->decode($token);

        $this->assertIsArray($payload);
        $this->assertSame('carol@example.com', $payload['email']);
    }

    // ----------------------------------------------------------------
    //  decode() — cas d'erreur
    // ----------------------------------------------------------------

    public function testDecodeThrowsOnMalformedTokenTwoParts(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Token malformé');

        $this->jwtManager->decode('only.twoparts');
    }

    public function testDecodeThrowsOnMalformedTokenFourParts(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Token malformé');

        $this->jwtManager->decode('a.b.c.d');
    }

    public function testDecodeThrowsOnInvalidSignature(): void
    {
        $user  = $this->buildUser();
        $token = $this->jwtManager->createToken($user);

        $parts    = explode('.', $token);
        $parts[2] = 'invalidsignatureXXXXXXXXXXXXXX';
        $tampered = implode('.', $parts);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Signature invalide');

        $this->jwtManager->decode($tampered);
    }

    public function testDecodeThrowsWhenPayloadIsAlteredWithoutResigning(): void
    {
        $user  = $this->buildUser('honest@example.com');
        $token = $this->jwtManager->createToken($user);

        // Falsifie le payload (élévation de privilèges)
        $parts    = explode('.', $token);
        $parts[1] = rtrim(strtr(base64_encode(json_encode([
            'sub'   => 999,
            'email' => 'hacker@evil.com',
            'roles' => ['ROLE_SUPER_ADMIN'],
            'iat'   => time(),
            'exp'   => time() + 3600,
        ])), '+/', '-_'), '=');
        $tampered = implode('.', $parts);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Signature invalide');

        $this->jwtManager->decode($tampered);
    }

    public function testDecodeThrowsOnExpiredToken(): void
    {
        // Construit un token expiré il y a 1 heure
        $expiredToken = $this->buildToken([
            'sub'   => 1,
            'email' => 'test@example.com',
            'roles' => ['ROLE_USER'],
            'iat'   => time() - 7200,
            'exp'   => time() - 3600,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Token expiré');

        $this->jwtManager->decode($expiredToken);
    }

    public function testDecodeThrowsOnTokenWithNoExp(): void
    {
        // Token sans champ exp (exp = 0 < time())
        $tokenWithoutExp = $this->buildToken([
            'sub'   => 1,
            'email' => 'test@example.com',
            'roles' => ['ROLE_USER'],
            'iat'   => time(),
            // exp absent → isset($data['exp']) sera false → exception "Token expiré"
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Token expiré');

        $this->jwtManager->decode($tokenWithoutExp);
    }

    public function testDecodeRejectsTokenSignedWithDifferentSecret(): void
    {
        $otherManager = new JwtManager('completely_different_secret_key!!');
        $user         = $this->buildUser();
        $foreignToken = $otherManager->createToken($user);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Signature invalide');

        // Notre JwtManager doit rejeter un token signé avec un autre secret
        $this->jwtManager->decode($foreignToken);
    }

    public function testDecodeRejectsEmptyString(): void
    {
        $this->expectException(\Exception::class);

        $this->jwtManager->decode('');
    }
}
