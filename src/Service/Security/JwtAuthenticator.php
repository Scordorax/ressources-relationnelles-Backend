<?php

namespace App\Service\Security;

use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class JwtAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private JwtManager $jwt,
        private UserRepository $userRepository
    ) {}

    /**
     * Déclenché uniquement si le header Authorization: Bearer xxx est présent
     */
    public function supports(Request $request): ?bool
    {
        $header = $request->headers->get('Authorization', '');
        return str_starts_with($header, 'Bearer ');
    }

    public function authenticate(Request $request): Passport
    {
        $token = substr($request->headers->get('Authorization'), 7);

        try {
            $payload = $this->jwt->decode($token);
        } catch (\Exception $e) {
            throw new CustomUserMessageAuthenticationException($e->getMessage());
        }

        return new SelfValidatingPassport(
            new UserBadge($payload['email'], function (string $email) {
                $user = $this->userRepository->findOneBy(['email' => $email]);

                if (!$user) {
                    throw new CustomUserMessageAuthenticationException('Utilisateur introuvable');
                }

                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null; // Laisse passer la requête
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(
            ['error' => $exception->getMessageKey()],
            Response::HTTP_UNAUTHORIZED
        );
    }
}
