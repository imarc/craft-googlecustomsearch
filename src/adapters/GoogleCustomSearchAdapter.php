<?php

namespace imarc\sitesearch\adapters;

use Craft;

/**
 * Google Programmable Search Engine (Custom Search JSON API).
 */
class GoogleCustomSearchAdapter implements AdapterInterface
{
    private string $apiKey;
    private string $searchEngineId;

    public function __construct(array $settings)
    {
        $this->apiKey = $settings['apiKey'] ?? '';
        $this->searchEngineId = $settings['searchEngineId'] ?? '';
    }

    public function search(string $terms, int $page, int $perPage, array $extra): object
    {
        // Google only allows 10 results at a time
        $perPage = min($perPage, 10);

        $params = array_merge([
            'q' => $terms,
            'start' => (($page - 1) * $perPage) + 1,
            'num' => $perPage,
        ], $extra);

        $response = $this->request($params);

        if (isset($response->error)) {
            return $response;
        }

        return self::mapResponse($response, $page, $perPage);
    }

    public function testConnection(): array
    {
        $response = $this->request(['q' => '']);

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
        $requestInfo = $response->queries->request[0];

        $results = new \stdClass();
        $results->page = $page;
        $results->perPage = $perPage;
        $results->start = $requestInfo->startIndex;
        $results->end = ($requestInfo->startIndex + $requestInfo->count) - 1;
        $results->totalResults = $requestInfo->totalResults ?? 0;

        // Google's totalResults is the (estimated) total number of matches, but the
        // Custom Search JSON API refuses to serve results past the 100th (requests
        // with start > 91 return a 400). We cap totalResults at 100 so pagination
        // built from it never links to unfetchable pages. The uncapped estimate is
        // still available via raw.queries.request[0].totalResults.
        if ($results->totalResults > 100) {
            $results->totalResults = 100;
        }

        $results->results = [];
        $results->raw = $response;

        foreach ($response->items ?? [] as $result) {
            $results->results[] = (object) [
                'title' => $result->title,
                'snippet' => $result->snippet ?? '',
                'htmlSnippet' => $result->htmlSnippet ?? '',
                'link' => $result->link,
                'image' => $result->pagemap->cse_image[0]->src ?? '',
                'thumbnail' => $result->pagemap->cse_thumbnail[0]->src ?? '',
            ];
        }

        return $results;
    }

    private function request(array $params): object
    {
        $params = array_merge([
            'key' => $this->apiKey,
            'cx' => $this->searchEngineId,
        ], $params);

        $client = Craft::createGuzzleClient([
            'headers' => [
                'Referer' => Craft::$app->getRequest()->getHostInfo(),
            ],
        ]);

        try {
            $response = $client->get('https://www.googleapis.com/customsearch/v1', [
                'query' => $params,
            ]);

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
}
