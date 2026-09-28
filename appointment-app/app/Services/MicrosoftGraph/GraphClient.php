<?php

namespace App\Services\MicrosoftGraph;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Client minimal pour Microsoft Graph, authentifié par le flux "client credentials"
 * (autorisations d'application, sans utilisateur connecté).
 */
class GraphClient
{
    public const BASE_URL = 'https://graph.microsoft.com/v1.0';

    public function __construct(
        private readonly ?string $tenantId,
        private readonly ?string $clientId,
        private readonly ?string $clientSecret,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            config('services.microsoft_graph.tenant_id'),
            config('services.microsoft_graph.client_id'),
            config('services.microsoft_graph.client_secret'),
        );
    }

    public function isConfigured(): bool
    {
        return filled($this->tenantId) && filled($this->clientId) && filled($this->clientSecret);
    }

    public function request(): PendingRequest
    {
        return Http::baseUrl(self::BASE_URL)
            ->withToken($this->accessToken())
            ->acceptJson()
            ->timeout(15)
            ->throw();
    }

    public static function userPath(string $user): string
    {
        return '/users/'.rawurlencode($user);
    }

    private function accessToken(): string
    {
        $cacheKey = 'ms-graph-token:'.sha1($this->tenantId.$this->clientId);

        if ($token = Cache::get($cacheKey)) {
            return $token;
        }

        try {
            $response = Http::asForm()
                ->timeout(15)
                ->post('https://login.microsoftonline.com/'.rawurlencode($this->tenantId).'/oauth2/v2.0/token', [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'scope' => 'https://graph.microsoft.com/.default',
                    'grant_type' => 'client_credentials',
                ])
                ->throw();
        } catch (RequestException $e) {
            // Le message d'erreur Azure AD ne contient jamais le secret : sûr à journaliser.
            Log::error('Échec de l\'authentification Microsoft Graph.', [
                'tenant_id' => $this->tenantId,
                'client_id' => $this->clientId,
                'status' => $e->response->status(),
                'body' => $e->response->json('error_description', $e->response->body()),
            ]);

            throw $e;
        }

        $token = $response->json('access_token');

        if (blank($token)) {
            throw new RuntimeException('Microsoft Graph n\'a renvoyé aucun jeton d\'accès.');
        }

        Cache::put($cacheKey, $token, max(60, (int) $response->json('expires_in', 3600) - 300));

        return $token;
    }
}
