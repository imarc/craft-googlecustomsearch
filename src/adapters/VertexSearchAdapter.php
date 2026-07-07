<?php

namespace imarc\sitesearch\adapters;

use Craft;
use Google\Auth\ApplicationDefaultCredentials;
use Google\Auth\Credentials\ServiceAccountCredentials;

/**
 * Google Vertex AI Search (Discovery Engine).
 *
 * Authenticates with a service account JSON key file, or with Application
 * Default Credentials when no key file is configured (e.g. when hosted on GCP).
 */
class VertexSearchAdapter implements AdapterInterface
{
    private const SCOPE = 'https://www.googleapis.com/auth/cloud-platform';

    private string $projectId;
    private string $location;
    private string $engineId;
    private string $serviceAccountFile;

    public function __construct(array $settings)
    {
        $this->projectId = $settings['projectId'] ?? '';
        $this->location = $settings['location'] ?: 'global';
        $this->engineId = $settings['engineId'] ?? '';
        $this->serviceAccountFile = $settings['serviceAccountFile'] ?? '';
    }

    public function search(string $terms, int $page, int $perPage, array $extra): object
    {
        $body = array_merge([
            'query' => $terms,
            'pageSize' => $perPage,
            'offset' => ($page - 1) * $perPage,
            'contentSearchSpec' => [
                'snippetSpec' => [
                    'returnSnippet' => true,
                ],
            ],
        ], $extra);

        $response = $this->request($body);

        if (isset($response->error)) {
            return $response;
        }

        return self::mapResponse($response, $page, $perPage);
    }

    public function testConnection(): array
    {
        $response = $this->request(['query' => 'test', 'pageSize' => 1]);

        if (!$response) {
            return ['success' => false, 'error' => Craft::t('app', 'No response')];
        }

        if (isset($response->error)) {
            return ['success' => false, 'error' => $response->error->code . ' - ' . $response->error->message];
        }

        return ['success' => true];
    }

    public static function mapResponse(object $response, int $page, int $perPage): object
    {
        $results = new \stdClass();
        $results->page = $page;
        $results->perPage = $perPage;
        $results->start = (($page - 1) * $perPage) + 1;
        $results->totalResults = $response->totalSize ?? 0;
        $results->results = [];
        $results->raw = $response;

        foreach ($response->results ?? [] as $result) {
            $data = $result->document->derivedStructData ?? new \stdClass();
            $htmlSnippet = $data->snippets[0]->snippet ?? '';

            $results->results[] = (object) [
                'title' => $data->title ?? '',
                'snippet' => $htmlSnippet !== '' ? html_entity_decode(strip_tags($htmlSnippet)) : '',
                'htmlSnippet' => $htmlSnippet,
                'link' => $data->link ?? '',
                'image' => $data->pagemap->cse_image[0]->src ?? '',
                'thumbnail' => $data->pagemap->cse_thumbnail[0]->src ?? '',
            ];
        }

        $results->end = $results->start + count($results->results) - 1;

        return $results;
    }

    private function request(array $body): object
    {
        try {
            $token = $this->getAccessToken();
        } catch (\Throwable $e) {
            return (object) [
                'error' => (object) [
                    'code' => $e->getCode(),
                    'message' => 'Vertex AI authentication failed: ' . $e->getMessage(),
                ],
            ];
        }

        $url = sprintf(
            'https://discoveryengine.googleapis.com/v1/projects/%s/locations/%s/collections/default_collection/engines/%s/servingConfigs/default_search:search',
            $this->projectId,
            $this->location,
            $this->engineId
        );

        $client = Craft::createGuzzleClient([
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Referer' => Craft::$app->getRequest()->getHostInfo(),
            ],
        ]);

        try {
            $response = $client->post($url, ['json' => $body]);

            return json_decode($response->getBody());
        } catch (\Throwable $e) {
            if ($e instanceof \GuzzleHttp\Exception\ClientException) {
                return json_decode($e->getResponse()->getBody());
            }

            return (object) [
                'error' => (object) [
                    'code' => $e->getCode(),
                    'message' => $e->getMessage(),
                ],
            ];
        }
    }

    private function getAccessToken(): string
    {
        $cacheKey = 'sitesearch:vertex-token:' . md5($this->serviceAccountFile ?: 'adc');
        $cached = Craft::$app->getCache()->get($cacheKey);

        if ($cached !== false) {
            return $cached;
        }

        if ($this->serviceAccountFile !== '') {
            $credentials = new ServiceAccountCredentials(self::SCOPE, $this->serviceAccountFile);
        } else {
            $credentials = ApplicationDefaultCredentials::getCredentials(self::SCOPE);
        }

        $token = $credentials->fetchAuthToken();

        if (empty($token['access_token'])) {
            throw new \RuntimeException('No access token returned for Vertex AI Search credentials.');
        }

        // Cache for slightly less than the token lifetime (typically one hour)
        $ttl = max(60, ($token['expires_in'] ?? 3600) - 60);
        Craft::$app->getCache()->set($cacheKey, $token['access_token'], $ttl);

        return $token['access_token'];
    }
}
