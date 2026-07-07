# Site Search plugin for Craft CMS

A Craft CMS plugin that adds external site search to your website, with three supported providers:

- **[Google Programmable Search Engine](https://programmablesearchengine.google.com/)** (Custom Search JSON API)
- **[Google Vertex AI Search](https://cloud.google.com/enterprise-search)** (Discovery Engine)
- **[AddSearch](https://www.addsearch.com/)**

The provider is chosen per site, so multisite installs can mix providers. All providers return the same result shape, so your templates don't change when you switch.

*This plugin was previously published as `imarc/craft-googlecustomsearch`. See [Upgrading from v3](#upgrading-from-v3) below. If you are looking for the Craft 2 version, [see the `craft2` branch.](https://github.com/imarc/craft-sitesearch/tree/craft2)*

## Requirements

This plugin requires Craft CMS 3.0.0, 4.x, or 5.x and PHP 8.0+ (PHP 8.2+ recommended for Craft 5).

## Installation

1. Open your terminal and go to your Craft project:

        cd /path/to/project

2. Then tell Composer to load the plugin:

        composer require imarc/craft-sitesearch

3. In the Control Panel, go to Settings → Plugins and click the “Install” button for Site Search.

## Configuring

Go to **Settings → Plugins → Site Search**, pick a provider for each site (use the site menu to switch between sites on multisite installs), and fill in that provider's credentials. Every field accepts environment variables (`$MY_VAR`). Credentials can also be set in `config/sitesearch.php` (see [Config file](#config-file)).

### Google Custom Search setup

You will need a **Search Engine ID** and **API Key**.

1. **Search Engine ID** — On the [Programmable Search Engine control panel](https://programmablesearchengine.google.com/controlpanel/all), create a search engine for the site you would like to integrate. Once created, copy the Search Engine ID from the *Basics* tab.
2. **API Key** — In the [Google Cloud console](https://console.cloud.google.com/), create (or pick) a project, enable the **Custom Search API** (APIs & Services → Library), then create an **API key** under APIs & Services → Credentials. Restrict the key to the Custom Search API.

### Google Vertex AI Search setup

Vertex AI Search (in the Google Cloud console as "AI Applications", formerly Agent Builder / Discovery Engine) provides Google-quality search over a crawled website index.

1. In the [Google Cloud console](https://console.cloud.google.com/), create (or pick) a project and note its **Project ID**.
2. Enable the **Discovery Engine API** (APIs & Services → Library → "Discovery Engine API").
3. Go to [AI Applications](https://console.cloud.google.com/gen-app-builder/engines) and create a **Search app**:
   - Type: **Search**, content: **Website content** (create a website data store pointing at your site's domain; verify the domain for advanced indexing if prompted).
   - Note the app's **ID** — this is the plugin's **App / Engine ID** — and its **location** (usually `global`).
4. Create credentials for the plugin:
   - Go to IAM & Admin → Service Accounts, create a service account (e.g. `craft-site-search`).
   - Grant it the **Discovery Engine Viewer** role (`roles/discoveryengine.viewer`).
   - Create a **JSON key** for it (Keys → Add key → JSON) and store the file on your server *outside the web root*.
   - In the plugin settings, set **Service Account Key File** to the file path (an env var like `$GOOGLE_APPLICATION_CREDENTIALS` works well).
   - **On Google Cloud hosting** (Cloud Run, GCE, App Engine): leave the key file blank and grant the runtime service account the Discovery Engine Viewer role — the plugin uses Application Default Credentials automatically.

Note: it can take a while after creating the app for the website index to populate.

### AddSearch setup

1. Sign up at [addsearch.com](https://www.addsearch.com/) and create an index for your site (AddSearch crawls it for you).
2. In the [AddSearch dashboard](https://app.addsearch.com/), find your index's **public Site Key** (Setup → Keywords & API).
3. Enter the Site Key in the plugin settings. If your index is private, also enter your secret **API Key**.

### Config file

Copy `src/config.php` to `config/sitesearch.php` to configure per environment. Keys under `siteSettings` are site handles:

```php
<?php
return [
    'siteSettings' => [
        // Google Custom Search
        'default' => [
            'provider' => 'gcs',
            'apiKey' => getenv('GOOGLE_SEARCH_API_KEY'),
            'searchEngineId' => getenv('GOOGLE_SEARCH_ENGINE_ID'),
        ],
        // Google Vertex AI Search
        'fr' => [
            'provider' => 'vertex',
            'projectId' => getenv('GOOGLE_CLOUD_PROJECT'),
            'location' => 'global',
            'engineId' => getenv('VERTEX_SEARCH_ENGINE_ID'),
            'serviceAccountFile' => getenv('GOOGLE_APPLICATION_CREDENTIALS'),
        ],
        // AddSearch
        'de' => [
            'provider' => 'addsearch',
            'siteKey' => getenv('ADDSEARCH_SITE_KEY'),
            'addsearchApiKey' => getenv('ADDSEARCH_API_KEY'), // optional, private indices only
        ],
    ],
];
```

The legacy single-site format (top-level `apiKey`/`searchEngineId`, Google Custom Search only) is still supported.

## Usage

In your twig template, retrieve search results by passing your search query, then iterate over them:

```twig
{% set response = craft.siteSearch.performSearch('query terms') %}
```

The response has the same shape for every provider:

| Property | Description |
| --- | --- |
| `page`, `perPage`, `start`, `end`, `totalResults` | Pagination info |
| `results` | Array of results: `title`, `snippet`, `htmlSnippet`, `link`, `image`, `thumbnail` |
| `raw` | The provider's decoded raw response, for provider-specific data |

Full signature: `performSearch(terms, page = 1, perPage = 10, extra = [])`. `extra` is merged into the provider request (e.g. Vertex [request fields](https://cloud.google.com/generative-ai-app-builder/docs/reference/rest/v1/projects.locations.collections.engines.servingConfigs/search), AddSearch or Custom Search query params).

By default an exception is thrown if the provider returns an error; call `craft.siteSearch.setThrowOnFailure(false)` first to log a warning and get the error response back instead.

Here is a complete example with pagination:

```twig
{% extends "_layout" %}

{% set query = craft.request.getParam('q') %}
{% set page = craft.request.getParam('page') ?: '1' %}
{% set title = "Search" %}

{% if query %}
	{% set response = craft.siteSearch.performSearch(query, page) %}
	{% set title = query ~ " - Search" %}
	{% set totalPages = ceil(response.totalResults / response.perPage) %}
{% endif %}

{% block content %}
	<div class="main">
		<h1>Search</h1>
		<form class="search">
			<div class="text">
				<input type="search" name="q" placeholder="Search" value="{{ query }}">
			</div>
			<div class="submit">
				<input type="submit" value="Search">
			</div>
		</form>

		{% if query %}
			{% if response.results | length %}
				<div class="intro">
					<p>
						Showing {{ response.start }}–{{ response.end }} of {{ response.totalResults }} results for <strong>{{ query }}</strong>
					</p>
				</div>
				<ul class="listing">
					{% for result in response.results %}
						<li>
							<h3>
								<a href="{{ result.link }}">
									{{ result.title | raw }}
								</a>
							</h3>
							{% if result.thumbnail | length %}
								<img src="{{ result.thumbnail }}" width="80" style="float: left; margin: 0 1em 1em 0" />
							{% endif %}
							<a class="url" href="{{ result.link }}">{{ result.link }}</a>
							<p class="summary">
								{{ result.htmlSnippet | raw }}
							</p>
						</li>
					{% endfor %}
				</ul>

				{% if totalPages > 1 %}
					<div class="meta paginator">
						{% if page > 1 %}
							<a href="{{ url('search', {q:query, page:(page-1)}) }}" class="prev">Previous</a>
						{% endif %}

						{% if page < totalPages %}
							<a href="{{ url('search', {q:query, page:(page+1)}) }}" class="next">Next</a>
						{% endif %}
					</div>
				{% endif %}

			{% else %}
				<div class="info">
					<p>
						Your search for “{{ query }}” didn’t return any results.
					</p>
				</div>
			{% endif %}
		{% endif %}
	</div>
{% endblock %}
```

## Upgrading from v3

v4 renames the plugin from **Google Custom Search** (`imarc/craft-googlecustomsearch`) to **Site Search** (`imarc/craft-sitesearch`):

1. `composer remove imarc/craft-googlecustomsearch && composer require imarc/craft-sitesearch`
2. Install the **Site Search** plugin in the Control Panel (or `php craft plugin/install sitesearch`). Your existing Google Custom Search settings are copied over automatically during install.
3. `craft.googlecustomsearch` still works but is deprecated — switch templates to `craft.siteSearch` at your convenience. The result shape is unchanged (plus a new `raw` property).
4. If you had a `config/googlecustomsearch.php`, rename it to `config/sitesearch.php`.
5. Uninstall/remove the old plugin if it's still listed.

## Credits

Brought to you by [Imarc](https://www.imarc.com)
