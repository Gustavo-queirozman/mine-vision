<?php
declare(strict_types=1);

namespace App\Infrastructure\Satellite;

use App\Domain\{ApiException,SatelliteProviderInterface};
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Copernicus Data Space Ecosystem adapter. Credentials remain server-side and
 * all imagery requests are constrained to an already-authorized monitoring AOI.
 */
final class CopernicusSatelliteProvider implements SatelliteProviderInterface
{
    private const TOKEN_URL = 'https://identity.dataspace.copernicus.eu/auth/realms/CDSE/protocol/openid-connect/token';
    private const API_URL = 'https://sh.dataspace.copernicus.eu';
    private ?string $accessToken = null;
    private int $tokenExpiresAt = 0;

    public function __construct(private ?Client $http = null)
    {
        $this->http ??= new Client(['timeout' => 40, 'connect_timeout' => 8, 'http_errors' => false]);
    }

    public function searchImages(array $area, string $start, string $end, array $options = []): array
    {
        $bbox = $this->boundingBox($area['geometry'] ?? null);
        $sources = $options['sources'] ?? ['SENTINEL-1', 'SENTINEL-2'];
        $collections = [];
        foreach ($sources as $source) {
            if ($source === 'SENTINEL-1') $collections['sentinel-1-grd'] = ['satellite' => 'SENTINEL-1', 'bands' => ['VV', 'VH'], 'radar' => true];
            if ($source === 'SENTINEL-2') $collections['sentinel-2-l2a'] = ['satellite' => 'SENTINEL-2', 'bands' => ['B02', 'B03', 'B04', 'B08', 'B11'], 'radar' => false];
        }
        $images = [];
        foreach ($collections as $collection => $definition) {
            $payload = [
                'collections' => [$collection], 'bbox' => $bbox,
                'datetime' => $start.'T00:00:00Z/'.$end.'T23:59:59Z', 'limit' => 50,
            ];
            $response = $this->request('POST', '/catalog/v1/search', ['json' => $payload]);
            $features = $response['features'] ?? [];
            if (!is_array($features)) continue;
            foreach ($features as $feature) {
                if (!is_array($feature) || !is_array($feature['properties'] ?? null)) continue;
                $properties = $feature['properties'];
                $datetime = (string)($properties['datetime'] ?? $properties['start_datetime'] ?? '');
                if ($datetime === '') continue;
                $cloud = $properties['eo:cloud_cover'] ?? null;
                $images[] = [
                    'mine_id' => (int)$area['mine_id'], 'monitoring_area_id' => (int)$area['id'],
                    'source' => 'COPERNICUS_CDSE', 'satellite' => $definition['satellite'],
                    'acquisition_date' => $datetime, 'cloud_percentage' => is_numeric($cloud) ? (float)$cloud : null,
                    'storage_path' => null, 'thumbnail_path' => null,
                    'metadata_json' => [
                        'provider' => 'Copernicus Data Space Ecosystem', 'collection' => $collection,
                        'product_id' => $feature['id'] ?? null, 'datetime' => $datetime, 'bbox' => $bbox,
                        'bands' => $definition['bands'], 'radar' => $definition['radar'],
                        'license' => 'Contains modified Copernicus Sentinel data',
                    ],
                    // The processing API renders previews directly from the source product.
                    'processing_status' => 'COMPLETED',
                ];
            }
        }
        usort($images, static fn(array $a, array $b): int => strcmp($a['acquisition_date'], $b['acquisition_date']));
        return $images;
    }

    public function getMetadata(array $image): array { return $image['metadata_json'] ?? []; }

    public function downloadImage(array $image): array
    {
        return ['mode' => 'copernicus_process_api', 'parameters' => $this->getMetadata($image)];
    }

    public function getBands(array $image): array
    {
        $bands = $image['metadata_json']['bands'] ?? [];
        return is_array($bands) ? $bands : [];
    }

    public function getPreview(array $image): string
    {
        $metadata = $this->getMetadata($image);
        $collection = $metadata['collection'] ?? null;
        $datetime = $metadata['datetime'] ?? $image['acquisition_date'] ?? null;
        $bbox = $metadata['bbox'] ?? null;
        if (!is_string($collection) || !is_string($datetime) || !is_array($bbox) || count($bbox) !== 4) {
            throw new ApiException(422, 'Metadados da imagem Copernicus estão incompletos.');
        }
        $isRadar = ($metadata['radar'] ?? false) === true;
        $evalscript = $isRadar ? <<<'JS'
//VERSION=3
function setup(){return {input:["VV","dataMask"],output:{bands:4}};}
function evaluatePixel(s){let v=Math.max(0,Math.min(1,(10*Math.log(Math.max(s.VV,0.000001))/Math.LN10+25)/25));return [v,v,v,s.dataMask];}
JS : <<<'JS'
//VERSION=3
function setup(){return {input:["B02","B03","B04","dataMask"],output:{bands:4}};}
function evaluatePixel(s){return [2.5*s.B04,2.5*s.B03,2.5*s.B02,s.dataMask];}
JS;
        try {
            $acquiredAt = new \DateTimeImmutable($datetime);
            // The Process API selects matching tiles within a range; a whole UTC
            // acquisition day is robust to the catalogue's timestamp precision.
            $from = $acquiredAt->setTime(0, 0)->format('Y-m-d\TH:i:s\Z');
            $to = $acquiredAt->setTime(23, 59, 59)->format('Y-m-d\TH:i:s\Z');
        } catch (\Exception) { throw new ApiException(422, 'A data de aquisição Copernicus é inválida.'); }
        $payload = [
            'input' => ['bounds' => ['bbox' => array_values($bbox), 'properties' => ['crs' => 'http://www.opengis.net/def/crs/OGC/1.3/CRS84']],
                'data' => [['type' => $isRadar ? 'sentinel-1-grd' : 'sentinel-2-l2a', 'dataFilter' => ['timeRange' => ['from' => $from, 'to' => $to], 'mosaickingOrder' => 'mostRecent']]]],
            'output' => ['width' => 768, 'height' => 768, 'responses' => [['identifier' => 'default', 'format' => ['type' => 'image/png']]]],
            'evalscript' => $evalscript,
        ];
        try {
            $response = $this->http->post(self::API_URL.'/process/v1', ['headers' => ['Authorization' => 'Bearer '.$this->token(), 'Accept' => 'image/png'], 'json' => $payload]);
        } catch (GuzzleException $exception) {
            throw new ApiException(502, 'Não foi possível obter a prévia do Copernicus.');
        }
        if ($response->getStatusCode() !== 200) throw new ApiException(502, 'O Copernicus não conseguiu gerar a prévia solicitada.');
        return (string)$response->getBody();
    }

    private function request(string $method, string $path, array $options): array
    {
        try {
            // Catalog returns a STAC GeoJSON response; an application/json Accept header
            // is rejected by CDSE, so allow its advertised representation.
            $response = $this->http->request($method, self::API_URL.$path, ['headers' => ['Authorization' => 'Bearer '.$this->token()], ...$options]);
        } catch (GuzzleException $exception) {
            throw new ApiException(502, 'Não foi possível consultar o catálogo Copernicus.');
        }
        if ($response->getStatusCode() !== 200) throw new ApiException(502, 'O catálogo Copernicus recusou a consulta.');
        try { return json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { throw new ApiException(502, 'O catálogo Copernicus retornou uma resposta inválida.'); }
    }

    private function token(): string
    {
        if ($this->accessToken !== null && time() < $this->tokenExpiresAt - 60) return $this->accessToken;
        $id = getenv('COPERNICUS_CLIENT_ID') ?: '';
        $secret = getenv('COPERNICUS_CLIENT_SECRET') ?: '';
        if ($id === '' || $secret === '') throw new ApiException(503, 'Credenciais do Copernicus não configuradas.');
        try {
            $response = $this->http->post(self::TOKEN_URL, ['form_params' => ['grant_type' => 'client_credentials', 'client_id' => $id, 'client_secret' => $secret]]);
        } catch (GuzzleException $exception) { throw new ApiException(502, 'Não foi possível autenticar no Copernicus.'); }
        if ($response->getStatusCode() !== 200) throw new ApiException(502, 'As credenciais do Copernicus foram recusadas.');
        try { $payload = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { throw new ApiException(502, 'Resposta de autenticação do Copernicus inválida.'); }
        if (!is_string($payload['access_token'] ?? null)) throw new ApiException(502, 'O Copernicus não retornou um token de acesso.');
        $this->accessToken = $payload['access_token'];
        $this->tokenExpiresAt = time() + (int)($payload['expires_in'] ?? 300);
        return $this->accessToken;
    }

    private function boundingBox(mixed $geometry): array
    {
        if (!is_array($geometry) || !in_array($geometry['type'] ?? null, ['Polygon', 'MultiPolygon'], true)) throw new ApiException(422, 'A área monitorada não possui uma geometria válida.');
        $coordinates = $geometry['coordinates'] ?? [];
        $points = [];
        $walk = function (mixed $value) use (&$walk, &$points): void {
            if (is_array($value) && count($value) >= 2 && is_numeric($value[0] ?? null) && is_numeric($value[1] ?? null)) { $points[] = [(float)$value[0], (float)$value[1]]; return; }
            if (is_array($value)) foreach ($value as $child) $walk($child);
        };
        $walk($coordinates);
        if ($points === []) throw new ApiException(422, 'A área monitorada não possui coordenadas válidas.');
        $longitudes = array_column($points, 0); $latitudes = array_column($points, 1);
        return [min($longitudes), min($latitudes), max($longitudes), max($latitudes)];
    }
}
