<?php
// copy this file to config/googlecustomsearch.php if you
// need to customize per environment with env vars

return [
    "apiKey" => getenv('GOOGLE_SEARCH_API_KEY'),
    "searchEngineId" => getenv('GOOGLE_SEARCH_ENGINE_ID'),
];
