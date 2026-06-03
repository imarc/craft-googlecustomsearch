<?php
// copy this file to config/googlecustomsearch.php if you
// need to customize per environment with env vars

return [
    // Per-site settings (recommended for multisite installs).
    // Keys are site handles; values are merged with CP settings.
    'siteSettings' => [
        // 'default' => [
        //     'apiKey' => getenv('GOOGLE_SEARCH_API_KEY'),
        //     'searchEngineId' => getenv('GOOGLE_SEARCH_ENGINE_ID'),
        // ],
    ],

    // Legacy single-site format (still supported).
    // Values may also be indexed by site handle for multisite:
    // 'apiKey' => ['default' => '...', 'fr' => '...'],
    'apiKey' => getenv('GOOGLE_SEARCH_API_KEY'),
    'searchEngineId' => getenv('GOOGLE_SEARCH_ENGINE_ID'),
];
