<?php

declare(strict_types=1);

namespace MisterCo\Reports\Services\Meta;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response as Psr7Response;
use RuntimeException;

/**
 * Cliente HTTP para Meta Marketing API (v20.0).
 *
 * - Reintentos con backoff exponencial para 5xx y errores transitorios.
 * - Detecta tokens inválidos (codes 190/102/467) y rate limit (4/17/32).
 * - Iteradores que recorren paginación cursor-based automáticamente.
 */
final class MetaApiClient
{
    private const BASE_URI = 'https://graph.facebook.com';
    private const MAX_REINTENTOS = 3;
    private const TIMEOUT_SEGUNDOS = 30;

    /** Códigos Meta que indican rate limit. */
    private const CODIGOS_RATE_LIMIT = [4, 17, 32, 613];

    /** Códigos Meta transitorios (vale la pena reintentar). */
    private const CODIGOS_TRANSITORIOS = [1, 2];

    private Client $http;

    public function __construct(
        private readonly string $token,
        private readonly string $apiVersion,
    ) {
        $this->http = new Client([
            'base_uri' => self::BASE_URI,
            'timeout' => self::TIMEOUT_SEGUNDOS,
            'http_errors' => false,
            'headers' => ['Accept' => 'application/json'],
        ]);
    }

    /**
     * GET a `/{version}/{endpoint}` con query params. Reintenta automáticamente.
     *
     * @param array<string, scalar|array<int,string>> $query
     * @return array<string, mixed>
     */
    public function get(string $endpoint, array $query = []): array
    {
        $endpoint = ltrim($endpoint, '/');
        $url = '/' . $this->apiVersion . '/' . $endpoint;
        $query['access_token'] = $this->token;

        // Convertir arrays a CSV (Meta espera "fields=a,b,c").
        foreach ($query as $k => $v) {
            if (is_array($v)) {
                $query[$k] = implode(',', $v);
            }
        }

        return $this->ejecutarConReintentos($url, $query);
    }

    /**
     * Itera todas las páginas de un endpoint paginado.
     * Yield-ea cada elemento del array `data` de cada página.
     *
     * @param array<string, scalar|array<int,string>> $query
     * @return \Generator<int, array<string, mixed>>
     */
    public function paginar(string $endpoint, array $query = []): \Generator
    {
        $query['limit'] = $query['limit'] ?? 100;
        $cursor = null;

        do {
            if ($cursor !== null) {
                $query['after'] = $cursor;
            }

            $response = $this->get($endpoint, $query);

            foreach ($response['data'] ?? [] as $item) {
                yield $item;
            }

            $cursor = $response['paging']['cursors']['after'] ?? null;
            $hasNext = isset($response['paging']['next']);
        } while ($hasNext && $cursor !== null);
    }

    /**
     * Valida el token contra `me/adaccounts` y retorna las cuentas accesibles.
     *
     * @return list<array<string, mixed>>
     */
    public function validarTokenYListarCuentas(): array
    {
        $cuentas = [];
        foreach ($this->paginar('me/adaccounts', [
            'fields' => ['id', 'account_id', 'name', 'currency', 'timezone_name', 'account_status', 'business'],
            'limit' => 200,
        ]) as $cuenta) {
            $cuentas[] = $cuenta;
        }

        return $cuentas;
    }

    /**
     * @param array<string, scalar> $query
     * @return array<string, mixed>
     */
    private function ejecutarConReintentos(string $url, array $query): array
    {
        $intento = 0;
        $ultimoError = null;

        while ($intento < self::MAX_REINTENTOS) {
            $intento++;
            try {
                $response = $this->http->get($url, ['query' => $query]);
            } catch (ConnectException | RequestException $e) {
                $ultimoError = $e;
                $this->dormir($intento);
                continue;
            }

            $status = $response->getStatusCode();
            $body = (string) $response->getBody();
            $payload = $this->decodificar($body);

            if ($status >= 200 && $status < 300) {
                return $payload;
            }

            $errorMeta = $payload['error'] ?? null;
            $code = is_array($errorMeta) ? (int) ($errorMeta['code'] ?? 0) : 0;
            $type = is_array($errorMeta) ? (string) ($errorMeta['type'] ?? '') : '';
            $subcode = is_array($errorMeta) ? ($errorMeta['error_subcode'] ?? null) : null;
            $msg = is_array($errorMeta) ? (string) ($errorMeta['message'] ?? 'Error desconocido') : 'Sin detalle';

            if (in_array($code, self::CODIGOS_RATE_LIMIT, true)) {
                $retryAfter = $this->extraerRetryAfter($response);
                throw new MetaRateLimitException(
                    "Meta API rate limit (code={$code}): {$msg}",
                    retryAfterSegundos: $retryAfter
                );
            }

            $esTransitorio = $status >= 500 || in_array($code, self::CODIGOS_TRANSITORIOS, true);
            if ($esTransitorio && $intento < self::MAX_REINTENTOS) {
                $this->dormir($intento);
                continue;
            }

            throw new MetaApiException(
                "Meta API {$status} (code={$code}, type={$type}): {$msg}",
                httpStatus: $status,
                metaCode: $code !== 0 ? $code : null,
                metaType: $type !== '' ? $type : null,
                metaSubcode: $subcode !== null ? (string) $subcode : null,
            );
        }

        throw new MetaApiException(
            'Fallaron todos los reintentos: ' . ($ultimoError !== null ? $ultimoError->getMessage() : 'desconocido'),
            previous: $ultimoError instanceof \Throwable ? $ultimoError : null,
        );
    }

    /** @return array<string, mixed> */
    private function decodificar(string $body): array
    {
        if ($body === '') {
            return [];
        }
        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

            return $decoded;
        } catch (\JsonException $e) {
            throw new MetaApiException('Respuesta Meta no es JSON válido: ' . $e->getMessage());
        }
    }

    private function dormir(int $intento): void
    {
        // Backoff exponencial con jitter: 1s, 2s, 4s + 0-500ms.
        $base = (2 ** ($intento - 1));
        $jitter = random_int(0, 500) / 1000;
        usleep((int) (($base + $jitter) * 1_000_000));
    }

    private function extraerRetryAfter(Psr7Response $response): ?int
    {
        $header = $response->getHeader('Retry-After');
        if ($header === []) {
            return null;
        }

        return (int) $header[0] ?: null;
    }
}
