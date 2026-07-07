<?php

namespace imarc\sitesearch\adapters;

use Craft;

/**
 * AddSearch (https://www.addsearch.com) Search API.
 *
 * Uses the public site key. For private indices, the secret API key is sent
 * via HTTP basic auth per AddSearch's API documentation.
 */
class AddSearchAdapter implements AdapterInterface
{
    private string $siteKey;
    private string $apiKey;

    public function __construct(array $settings)
    {
        $this->siteKey = $settings['siteKey'] ?? '';
        $this->apiKey = $settings['addsearchApiKey'] ?? '';
    }

    public function search(string $terms, int $page, int $perPage, array $extra): object
    {
        $params = array_merge([
            'term' => $terms,
            'page' => $page,
            'limit' => $perPage,
        ], $extra);

        $response = $this->request($params);

        if (isset($response->error)) {
            return $response;
        }

        return self::mapResponse($response, $page, $perPage);
    }

    public function testConnection(): array
    {
        $response = $this->request(['term' => 'test', 'limit' => 1]);

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
        $results->totalResults = $response->total_hits ?? 0;
        $results->results = [];
        $results->raw = $response;

        foreach ($response->hits ?? [] as $hit) {
            $htmlSnippet = $hit->highlight ?? '';
            $snippet = $htmlSnippet !== ''
                ? html_entity_decode(strip_tags($htmlSnippet))
                : ($hit->meta_description ?? '');

            $results->results[] = (object) [
                'title' => $hit->title ?? '',
                'snippet' => $snippet,
                'htmlSnippet' => $htmlSnippet !== '' ? $htmlSnippet : htmlspecialchars($snippet),
                'link' => $hit->url ?? '',
                'image' => $hit->images->main ?? '',
                'thumbnail' => $hit->images->capture ?? '',
            ];
        }

        $results->end = $results->start + count($results->results) - 1;

        return $results;
    }

    private function request(array $params): object
    {
        $options = [
            'headers' => [
                'Referer' => Craft::$app->getRequest()->getHostInfo(),
            ],
        ];

        if ($this->apiKey !== '') {
            $options['auth'] = [$this->siteKey, $this->apiKey];
        }

        $client = Craft::createGuzzleClient($options);

        try {
            $response = $client->get('https://api.addsearch.com/v1/search/' . $this->siteKey, [
                'query' => $params,
            ]);

            return json_decode($response->getBody());
        } catch (\Throwable $e) {
            if ($e instanceof \GuzzleHttp\Exception\ClientException) {
                $decoded = json_decode($e->getResponse()->getBody());

                if (isset($decoded->error)) {
                    return $decoded;
                }

                return (object) [
                    'error' => (object) [
                        'code' => $e->getResponse()->getStatusCode(),
                        'message' => (string) $e->getResponse()->getBody() ?: $e->getMessage(),
                    ],
                ];
            }

            return (object) [
                'error' => (object) [
                    'code' => $e->getCode(),
                    'message' => $e->getMessage(),
                ],
            ];
        }
    }
}
