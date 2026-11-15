<?php

namespace App\Services\Kolesa;

use App\Exceptions\Kolesa\KolesaApiException;
use App\Exceptions\Kolesa\KolesaAuthExpiredException;
use App\Exceptions\Kolesa\KolesaNetworkException;
use App\Exceptions\Kolesa\KolesaServiceException;
use App\Models\KolesaSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class KolesaClient
{
    public function listLive(int $offset): array
    {
        return $this->send(
            'GET',
            config('kolesa.base_url_app').'/'.config('kolesa.version_prefix').'/user/adverts/live',
            array_merge(config('kolesa.query_defaults'), [
                'limit' => config('kolesa.page_size'),
                'offset' => $offset,
            ]),
        );
    }

    public function listArchive(int $offset): array
    {
        return $this->send(
            'GET',
            config('kolesa.base_url_app').'/'.config('kolesa.version_prefix').'/user/adverts/archive',
            array_merge(config('kolesa.query_defaults'), [
                'limit' => config('kolesa.page_size'),
                'offset' => $offset,
            ]),
        );
    }

    /**
     * Apply a paid service / package to an advert.
     *
     * Kolesa returns HTTP 200 for both success and business errors —
     * errors are expressed as `{"error_code": N, "error": "..."}` in the body.
     * Here we unwrap that and throw KolesaServiceException on business failure.
     *
     * @return array<string, mixed> raw response body on success (e.g. `{"status": "ok"}`)
     */
    public function setService(int $advertId, string $storageId, string $serviceName): array
    {
        $body = $this->send(
            'POST',
            config('kolesa.base_url_api').'/v1/user/setService.json',
            ['id' => $advertId, 'storageId' => $storageId],
            ['name' => $serviceName],
        );

        if (isset($body['error_code'])) {
            Log::warning('Kolesa setService business error', [
                'advert_id' => $advertId,
                'service' => $serviceName,
                'code' => $body['error_code'],
                'message' => $body['error'] ?? null,
            ]);

            throw KolesaServiceException::fromResponse($body);
        }

        if (($body['status'] ?? null) !== 'ok') {
            Log::warning('Kolesa setService unexpected response', [
                'advert_id' => $advertId,
                'service' => $serviceName,
                'body' => $body,
            ]);

            throw new KolesaApiException(
                'Unexpected setService response: '.json_encode($body, JSON_UNESCAPED_UNICODE),
            );
        }

        return $body;
    }

    private function send(string $method, string $url, array $query = [], ?array $form = null): array
    {
        $token = KolesaSetting::current()->token;

        if (empty($token)) {
            throw new KolesaAuthExpiredException('Token not configured');
        }

        $headers = array_filter(
            array_merge(
                (array) config('kolesa.headers'),
                ['X-AUTH-TOKEN' => $token, 'Cookie' => config('kolesa.cookie')],
            ),
            fn ($value) => $value !== null && $value !== '',
        );

        $fullUrl = $url.(empty($query) ? '' : '?'.http_build_query($query));

        try {
            $builder = Http::withHeaders($headers)
                ->timeout((int) config('kolesa.request_timeout'));

            $response = match ($method) {
                'GET' => $builder->get($fullUrl),
                'POST' => $builder->asForm()->post($fullUrl, $form ?? []),
                default => throw new KolesaApiException("Unsupported HTTP method: {$method}"),
            };
        } catch (ConnectionException $e) {
            Log::warning('Kolesa network error', ['url' => $url, 'error' => $e->getMessage()]);

            throw new KolesaNetworkException('Kolesa network error: '.$e->getMessage(), previous: $e);
        } catch (KolesaApiException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::warning('Kolesa unexpected request error', ['url' => $url, 'error' => $e->getMessage()]);

            throw new KolesaApiException('Kolesa request failed: '.$e->getMessage(), previous: $e);
        }

        if ($response->status() === 401) {
            Log::warning('Kolesa 401 Unauthorized', ['url' => $url]);

            throw new KolesaAuthExpiredException("HTTP 401 from {$url}");
        }

        if (! $response->successful()) {
            $body = $response->body();
            Log::warning('Kolesa non-2xx response', [
                'url' => $url,
                'status' => $response->status(),
                'body' => mb_substr($body, 0, 500),
            ]);

            throw new KolesaApiException(
                "HTTP {$response->status()} from {$url}: ".mb_substr($body, 0, 200),
                statusCode: $response->status(),
            );
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw new KolesaApiException('Invalid JSON from '.$url, statusCode: $response->status());
        }

        return $json;
    }
}
