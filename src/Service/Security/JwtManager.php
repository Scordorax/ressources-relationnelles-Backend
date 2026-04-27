<?php

namespace App\Service\Security;

use App\Entity\User;

class JwtManager
{
    public function __construct(private string $secret) {}

    public function createToken(User $user): string
    {
        $header  = $this->base64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = $this->base64url(json_encode([
            'sub'   => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'iat'   => time(),
            'exp'   => time() + 3600, // 1h
        ]));

        $sig = $this->base64url(
            hash_hmac('sha256', "$header.$payload", $this->secret, true)
        );

        return "$header.$payload.$sig";
    }

    public function decode(string $token): array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new \Exception('Token malformé');
        }

        [$header, $payload, $sig] = $parts;

        $expected = $this->base64url(
            hash_hmac('sha256', "$header.$payload", $this->secret, true)
        );

        if (!hash_equals($expected, $sig)) {
            throw new \Exception('Signature invalide');
        }

        $data = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);

        if (!isset($data['exp']) || $data['exp'] < time()) {
            throw new \Exception('Token expiré');
        }

        return $data;
    }

    private function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
