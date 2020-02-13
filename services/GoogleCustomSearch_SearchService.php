<?php
namespace Craft;

class GoogleCustomSearch_SearchService extends BaseApplicationComponent
{
    /**
     * Sends search request to Google
     *
     * @param array params The parameters of the search request
     * @return object The raw results of the search request
     **/
    private function request($params) 
    {
        $settings = craft()->plugins->getPlugin('googlecustomsearch')->getSettings();

        $params = array_merge(
            array(
                'key' => $settings->apiKey,
                'cx' => $settings->searchEngineId
            ),
            $params
        );

        $context = stream_context_create(array(
            'http' => array(
                'ignore_errors' => true
            )
        ));

        return json_decode(
            file_get_contents('https://www.googleapis.com/customsearch/v1?' . http_build_query($params), false, $context)
        );
    }

    /**
     * Perform search
     *
     * Returns an object with the following properties:
     *
     *   page
     *   perPage
     *   start
     *   end
     *   totalResults
     *   results
     *     title
     *     snippet
     *     htmlSnippet
     *     link
     *     image
     *     thumbnail
     *
     * @param string terms The search terms
     * @param integer page The page to return
     * @param integer per_page How many results to dispaly per page
     * @param array extra Extra parameters to pass to Google
     * @return object The results of the search
     * @throws Exception If error is returned from Google
     **/
    public function performSearch($terms, $page, $per_page, $extra)
    {
        // Google only allows 10 results at a time
        $per_page = ($per_page > 10) ? 10 : $per_page;

        $params = array(
            'q' => $terms,
            'start' => (($page - 1) * $per_page) + 1,
            'num' => $per_page
        );
        if (sizeof($extra)) {
            $params = array_merge($params, $extra);
        }

        $response = $this->request($params);

        if (isset($response->error)) {
            throw new \Exception($response->error->message);
        }

        $request_info = $response->queries->request[0];

        $results = new \stdClass();
        $results->page = $page;
        $results->perPage = $per_page;
        $results->start = $request_info->startIndex;
        $results->end = ($request_info->startIndex + $request_info->count) - 1;
        $results->totalResults = $request_info->totalResults ?? 0;

        // Google allows only 100 results to be fetched for a search query over the API
        // so we cap the totalResults to 100 if it exceeds that number
        if ($results->totalResults > 100) {
            $results->totalResults = 100;
        }

        $results->results = array();

        if (isset($response->items)) {
            foreach ($response->items as $result) {
                $results->results[] = (object) array(
                    'title' => $result->title,
                    'snippet' => $result->snippet,
                    'htmlSnippet' => $result->htmlSnippet,
                    'link' => $result->link,
                    'image' => isset($result->pagemap->cse_image) ? $result->pagemap->cse_image[0]->src : '',
                    'thumbnail' => isset($result->pagemap->cse_thumbnail) ? $result->pagemap->cse_thumbnail[0]->src : '',
                );
            }
        }

        return $results;
    }

    /**
     * Test connection with an empty search request
     *
     * @return array The result of the connection test
     **/
    public function testConnection()
    {
        $settings = craft()->request->getQuery('settings');
        $response = $this->request(array(
            'q' => '',
            'key' => $settings['apiKey'],
            'cx' => $settings['searchEngineId']
        ));

        $result = array(
            'success' => true
        );

        if (!$response) {
            $result = array(
                'success' => false,
                'error' => Craft::t('No response')
            );
        } elseif (isset($response->error)) {
            $result = array(
                'success' => false,
                'error' => $response->error->code . ' - ' . $response->error->message
            );
        }

        return $result;
    }
}
