<?php
// copy this file to config/sitesearch.php if you
// need to customize per environment with env vars

return [
    // Per-site settings (recommended for multisite installs).
    // Keys are site handles; values are merged with CP settings.
    'siteSettings' => [
        // Google Custom Search:
        // 'default' => [
        //     'provider' => 'gcs',
        //     'apiKey' => getenv('GOOGLE_SEARCH_API_KEY'),
        //     'searchEngineId' => getenv('GOOGLE_SEARCH_ENGINE_ID'),
        // ],

        // Google Vertex AI Search:
        // 'default' => [
        //     'provider' => 'vertex',
        //     'projectId' => getenv('GOOGLE_CLOUD_PROJECT'),
        //     'location' => 'global',
        //     'engineId' => getenv('VERTEX_SEARCH_ENGINE_ID'),
        //     'serviceAccountFile' => getenv('GOOGLE_APPLICATION_CREDENTIALS'),
        // ],

        // AddSearch:
        // 'default' => [
        //     'provider' => 'addsearch',
        //     'siteKey' => getenv('ADDSEARCH_SITE_KEY'),
        //     'addsearchApiKey' => getenv('ADDSEARCH_API_KEY'), // optional, private indices only
        // ],
    ],

    // Legacy single-site format (still supported, Google Custom Search only).
    'apiKey' => getenv('GOOGLE_SEARCH_API_KEY'),
    'searchEngineId' => getenv('GOOGLE_SEARCH_ENGINE_ID'),
];
