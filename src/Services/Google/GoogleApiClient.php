<?php

declare(strict_types=1);

namespace MisterCo\Reports\Services\Google;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;

/**
 * Cliente HTTP (REST) para Google Ads API, GA4 Admin API y GA4 Data API.
 *
 * - Reintentos con backoff exponencial para 5xx y 429 (cuota).
 * - Paginación automática (pageToken en Ads/Admin, offset en Data API).
 * - Ads devuelve los detalles del error en error.details[].errors[]: los
 *   priorizamos porque el `message` general suele ser genérico.
 */
final class GoogleApiClient
{
    private const ADS_URI = 'https://googleads.googleapis.com';
    private const GA_ADMIN_URI = 'https://analyticsadmin.googleapis.com/v1beta';
    private const GA_DATA_URI = 'https://analyticsdata.googleapis.com/v1beta';
    private const MAX_REINTENTOS = 3;
    private const FILAS_POR_PAGINA_GA = 10000;

    private Client $http;

    public function __construct(
        private readonly string $accessToken,
        private readonly string $developerToken,
        private readonly string $adsApiVersion,
    ) {
        $this->http = new Client(['timeout' => 60, 'http_errors' => false]);
    }

    /**
     * IDs de cuentas de Google Ads accesibles directamente por el usuario conectado.
     *
     * @return list<string>
     */
    public function adsCuentasAccesibles(): array
    {
        $resp = $this->pedir('GET', self::ADS_URI . "/{$this->adsApiVersion}/customers:listAccessibleCustomers", $this->headersAds(null));

        return array_map(
            static fn (string $rn): string => substr($rn, strlen('customers/')),
            array_map('strval', $resp['resourceNames'] ?? [])
        );
    }

    /**
     * Ejecuta una consulta GAQL y recorre todas las páginas.
     *
     * @return \Generator<int, array<string, mixed>>
     */
    public function adsBuscar(string $customerId, string $gaql, ?string $loginCustomerId): \Generator
    {
        $url = self::ADS_URI . "/{$this->adsApiVersion}/customers/{$customerId}/googleAds:search";
        $pageToken = null;
        do {
            $body = ['query' => $gaql];
            if ($pageToken !== null) {
                $body['pageToken'] = $pageToken;
            }
            $resp = $this->pedir('POST', $url, $this->headersAds($loginCustomerId), $body);
            foreach ($resp['results'] ?? [] as $fila) {
                yield $fila;
            }
            $pageToken = isset($resp['nextPageToken']) && $resp['nextPageToken'] !== '' ? (string) $resp['nextPageToken'] : null;
        } while ($pageToken !== null);
    }

    /**
     * Cuentas de Analytics con sus propiedades GA4.
     *
     * @return \Generator<int, array<string, mixed>>
     */
    public function gaResumenCuentas(): \Generator
    {
        $pageToken = null;
        do {
            $query = ['pageSize' => 200];
            if ($pageToken !== null) {
                $query['pageToken'] = $pageToken;
            }
            $resp = $this->pedir('GET', self::GA_ADMIN_URI . '/accountSummaries?' . http_build_query($query), $this->headersBase());
            foreach ($resp['accountSummaries'] ?? [] as $cuenta) {
                yield $cuenta;
            }
            $pageToken = isset($resp['nextPageToken']) && $resp['nextPageToken'] !== '' ? (string) $resp['nextPageToken'] : null;
        } while ($pageToken !== null);
    }

    /** @return array<string, mixed> Datos de la propiedad (currencyCode, timeZone…). */
    public function gaPropiedad(string $propertyId): array
    {
        return $this->pedir('GET', self::GA_ADMIN_URI . "/properties/{$propertyId}", $this->headersBase());
    }

    /**
     * runReport con paginación por offset. Devuelve cada fila como
     * [dimensiones => list<string>, metricas => list<string>].
     *
     * @param array<string, mixed> $body
     * @return \Generator<int, array{dimensiones: list<string>, metricas: list<string>}>
     */
    public function gaReporte(string $propertyId, array $body): \Generator
    {
        $url = self::GA_DATA_URI . "/properties/{$propertyId}:runReport";
        $offset = 0;
        do {
            $resp = $this->pedir('POST', $url, $this->headersBase(), $body + [
                'limit' => self::FILAS_POR_PAGINA_GA,
                'offset' => $offset,
            ]);
            $filas = $resp['rows'] ?? [];
            foreach ($filas as $fila) {
                yield [
                    'dimensiones' => array_map(static fn ($v) => (string) ($v['value'] ?? ''), $fila['dimensionValues'] ?? []),
                    'metricas' => array_map(static fn ($v) => (string) ($v['value'] ?? '0'), $fila['metricValues'] ?? []),
                ];
            }
            $offset += count($filas);
        } while ($filas !== [] && $offset < (int) ($resp['rowCount'] ?? 0));
    }

    /** @return array<string, string> */
    private function headersBase(): array
    {
        return ['Authorization' => 'Bearer ' . $this->accessToken, 'Accept' => 'application/json'];
    }

    /** @return array<string, string> */
    private function headersAds(?string $loginCustomerId): array
    {
        if ($this->developerToken === '') {
            throw new GoogleApiException('Falta GOOGLE_ADS_DEVELOPER_TOKEN en el .env: sin él no se puede consultar Google Ads.');
        }
        $headers = $this->headersBase() + ['developer-token' => $this->developerToken];
        if ($loginCustomerId !== null && $loginCustomerId !== '') {
            $headers['login-customer-id'] = $loginCustomerId;
        }

        return $headers;
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed>|null $json
     * @return array<string, mixed>
     */
    private function pedir(string $metodo, string $url, array $headers, ?array $json = null): array
    {
        $opciones = ['headers' => $headers];
        if ($json !== null) {
            $opciones['json'] = $json;
        }

        $ultimoError = null;
        for ($intento = 1; $intento <= self::MAX_REINTENTOS; $intento++) {
            try {
                $resp = $this->http->request($metodo, $url, $opciones);
            } catch (ConnectException | RequestException $e) {
                $ultimoError = $e;
                $this->dormir($intento);
                continue;
            }

            $status = $resp->getStatusCode();
            $payload = json_decode((string) $resp->getBody(), true);
            $payload = is_array($payload) ? $payload : [];

            if ($status >= 200 && $status < 300) {
                return $payload;
            }

            if (($status >= 500 || $status === 429) && $intento < self::MAX_REINTENTOS) {
                $this->dormir($intento);
                continue;
            }

            $error = is_array($payload['error'] ?? null) ? $payload['error'] : [];
            throw new GoogleApiException(
                "Google API {$status}: " . $this->mensajeDeError($error),
                httpStatus: $status,
                googleStatus: isset($error['status']) ? (string) $error['status'] : null,
            );
        }

        throw new GoogleApiException(
            'Fallaron todos los reintentos: ' . ($ultimoError?->getMessage() ?? 'desconocido'),
            previous: $ultimoError,
        );
    }

    /** @param array<string, mixed> $error */
    private function mensajeDeError(array $error): string
    {
        foreach ($error['details'] ?? [] as $detalle) {
            foreach ($detalle['errors'] ?? [] as $e) {
                if (isset($e['message'])) {
                    return (string) $e['message'];
                }
            }
        }

        return (string) ($error['message'] ?? 'Error desconocido');
    }

    private function dormir(int $intento): void
    {
        usleep((int) (((2 ** ($intento - 1)) + random_int(0, 500) / 1000) * 1_000_000));
    }
}
