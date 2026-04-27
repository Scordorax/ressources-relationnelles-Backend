<?php

namespace App\Service\Security;

/**
 * Gère le protocole OAuth2/OIDC de FranceConnect.
 *
 * FranceConnect suit le protocole OpenID Connect (OIDC) :
 *  1. On redirige l'utilisateur vers FranceConnect avec un "state" et un "nonce"
 *  2. FranceConnect renvoie un "code" sur notre callback
 *  3. On échange le code contre un access_token + id_token
 *  4. On vérifie le id_token (JWT signé par FranceConnect)
 *  5. On récupère les infos utilisateur via /userinfo
 */
class FranceConnectProvider
{
    public function __construct(
        private string $clientId,
        private string $clientSecret,
        private string $redirectUri,
        private string $baseUrl
    ) {}

    /**
     * Génère l'URL de redirection vers FranceConnect
     */
    public function getAuthorizationUrl(string $state, string $nonce): string
    {
        $params = http_build_query([
            'response_type' => 'code',
            'client_id'     => $this->clientId,
            'redirect_uri'  => $this->redirectUri,
            'scope'         => 'openid given_name family_name email',
            'state'         => $state,
            'nonce'         => $nonce,
            'acr_values'    => 'eidas1', // Niveau de garantie FranceConnect
        ]);

        return $this->baseUrl . '/api/v1/authorize?' . $params;
    }

    /**
     * Échange le code d'autorisation contre les tokens
     */
    public function getTokens(string $code): array
    {
        $response = $this->httpPost($this->baseUrl . '/api/v1/token', [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => $this->redirectUri,
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
        ]);

        if (!isset($response['access_token'])) {
            throw new \Exception('Impossible d\'obtenir les tokens FranceConnect');
        }

        return $response;
    }

    /**
     * Récupère les informations utilisateur depuis FranceConnect
     */
    public function getUserInfo(string $accessToken): array
    {
        $ch = curl_init($this->baseUrl . '/api/v1/userinfo');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $accessToken,
                'Accept: application/json',
            ],
        ]);

        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200) {
            throw new \Exception('Impossible de récupérer le profil FranceConnect');
        }

        $data = json_decode($body, true);

        if (!isset($data['sub'])) {
            throw new \Exception('Réponse FranceConnect invalide');
        }

        return $data;
    }

    /**
     * Génère et retourne l'URL de déconnexion FranceConnect
     */
    public function getLogoutUrl(string $idToken, string $state): string
    {
        $params = http_build_query([
            'id_token_hint'            => $idToken,
            'state'                    => $state,
            'post_logout_redirect_uri' => $this->redirectUri,
        ]);

        return $this->baseUrl . '/api/v1/logout?' . $params;
    }

    private function httpPost(string $url, array $data): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($data),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200) {
            throw new \Exception("Erreur HTTP $code depuis FranceConnect");
        }

        return json_decode($body, true) ?? [];
    }
}
