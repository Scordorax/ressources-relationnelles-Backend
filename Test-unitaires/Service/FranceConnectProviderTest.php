<?php

namespace App\Tests\Service\Security;

use App\Service\Security\FranceConnectProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour FranceConnectProvider
 *
 * IMPORTANT : getTokens() et getUserInfo() font des appels HTTP réels via curl.
 * Ces méthodes NE PEUVENT PAS être testées en test unitaire pur sans
 * remplacer curl par Symfony HttpClientInterface (voir comments.md, problème #5).
 *
 * Ce fichier couvre uniquement les méthodes de construction d'URL :
 *   - getAuthorizationUrl()
 *   - getLogoutUrl()
 *
 * Les tests d'intégration pour getTokens() et getUserInfo() doivent
 * être écrits séparément avec un mock HTTP.
 */
class FranceConnectProviderTest extends TestCase
{
    private FranceConnectProvider $provider;

    private string $clientId     = 'test_client_id';
    private string $clientSecret = 'test_client_secret';
    private string $redirectUri  = 'https://myapp.fr/api/auth/france-connect/callback';
    private string $baseUrl      = 'https://fcp.integ01.dev-franceconnect.fr';

    protected function setUp(): void
    {
        $this->provider = new FranceConnectProvider(
            $this->clientId,
            $this->clientSecret,
            $this->redirectUri,
            $this->baseUrl
        );
    }

    // ----------------------------------------------------------------
    //  getAuthorizationUrl()
    // ----------------------------------------------------------------

    public function testGetAuthorizationUrlStartsWithBaseUrl(): void
    {
        $url = $this->provider->getAuthorizationUrl('state_abc', 'nonce_xyz');

        $this->assertStringStartsWith(
            $this->baseUrl,
            $url,
            'L\'URL d\'autorisation doit commencer par le baseUrl FranceConnect.'
        );
    }

    public function testGetAuthorizationUrlContainsResponseTypeCode(): void
    {
        $url = $this->provider->getAuthorizationUrl('s', 'n');

        $this->assertStringContainsString('response_type=code', $url);
    }

    public function testGetAuthorizationUrlContainsClientId(): void
    {
        $url = $this->provider->getAuthorizationUrl('s', 'n');

        $this->assertStringContainsString('client_id=test_client_id', $url);
    }

    public function testGetAuthorizationUrlContainsRedirectUri(): void
    {
        $url = $this->provider->getAuthorizationUrl('s', 'n');

        $this->assertStringContainsString(urlencode($this->redirectUri), $url);
    }

    public function testGetAuthorizationUrlContainsOpenIdScope(): void
    {
        $url = $this->provider->getAuthorizationUrl('s', 'n');

        $this->assertStringContainsString('openid', $url);
    }

    public function testGetAuthorizationUrlContainsGivenNameScope(): void
    {
        $url = $this->provider->getAuthorizationUrl('s', 'n');

        $this->assertStringContainsString('given_name', $url);
    }

    public function testGetAuthorizationUrlContainsFamilyNameScope(): void
    {
        $url = $this->provider->getAuthorizationUrl('s', 'n');

        $this->assertStringContainsString('family_name', $url);
    }

    public function testGetAuthorizationUrlContainsEmailScope(): void
    {
        $url = $this->provider->getAuthorizationUrl('s', 'n');

        $this->assertStringContainsString('email', $url);
    }

    public function testGetAuthorizationUrlContainsState(): void
    {
        $url = $this->provider->getAuthorizationUrl('my_csrf_state', 'nonce');

        $this->assertStringContainsString('state=my_csrf_state', $url);
    }

    public function testGetAuthorizationUrlContainsNonce(): void
    {
        $url = $this->provider->getAuthorizationUrl('state', 'my_replay_nonce');

        $this->assertStringContainsString('nonce=my_replay_nonce', $url);
    }

    public function testGetAuthorizationUrlContainsEidas1AcrValue(): void
    {
        $url = $this->provider->getAuthorizationUrl('state', 'nonce');

        $this->assertStringContainsString('eidas1', $url, 'Le niveau de garantie eIDAS 1 doit être présent.');
    }

    public function testGetAuthorizationUrlPointsToAuthorizeEndpoint(): void
    {
        $url = $this->provider->getAuthorizationUrl('state', 'nonce');

        $this->assertStringContainsString('/api/v1/authorize', $url);
    }

    public function testGetAuthorizationUrlStateAndNonceAreInjectedFromParameters(): void
    {
        $state = 'unique_state_token_abc123';
        $nonce = 'unique_nonce_token_xyz789';

        $url = $this->provider->getAuthorizationUrl($state, $nonce);

        $this->assertStringContainsString("state=$state", $url);
        $this->assertStringContainsString("nonce=$nonce", $url);
    }

    // ----------------------------------------------------------------
    //  getLogoutUrl()
    // ----------------------------------------------------------------

    public function testGetLogoutUrlStartsWithBaseUrl(): void
    {
        $url = $this->provider->getLogoutUrl('some_id_token', 'logout_state');

        $this->assertStringStartsWith($this->baseUrl, $url);
    }

    public function testGetLogoutUrlPointsToLogoutEndpoint(): void
    {
        $url = $this->provider->getLogoutUrl('id_token', 'state');

        $this->assertStringContainsString('/api/v1/logout', $url);
    }

    public function testGetLogoutUrlContainsIdTokenHint(): void
    {
        $url = $this->provider->getLogoutUrl('my_id_token_value', 'state');

        $this->assertStringContainsString('id_token_hint=my_id_token_value', $url);
    }

    public function testGetLogoutUrlContainsState(): void
    {
        $url = $this->provider->getLogoutUrl('id_token', 'logout_state_abc');

        $this->assertStringContainsString('state=logout_state_abc', $url);
    }

    public function testGetLogoutUrlContainsPostLogoutRedirectUri(): void
    {
        $url = $this->provider->getLogoutUrl('id_token', 'state');

        $this->assertStringContainsString('post_logout_redirect_uri=', $url);
        $this->assertStringContainsString(urlencode($this->redirectUri), $url);
    }

    public function testGetLogoutUrlIsAValidUrl(): void
    {
        $url = $this->provider->getLogoutUrl('id_token', 'state');

        $this->assertNotFalse(filter_var($url, FILTER_VALIDATE_URL), 'L\'URL de déconnexion doit être une URL valide.');
    }
}
