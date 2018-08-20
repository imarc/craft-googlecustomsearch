# Google Custom Search plugin for Craft CMS 3.x

A Craft CMS plugin that incorporates a Google Custom Search into your website.

Google offers [free](https://cse.google.com/cse) and [paid](https://www.google.com/work/search/products/gss.html) tiers for accessing their search results.

*If you are looking for the Craft 2 version of this plugin, [see the `craft2` branch.](https://github.com/imarc/craft-googlecustomsearch/tree/craft2)*

## Requirements

This plugin requires Craft CMS 3.0.0 or later.

## Installation

To install the plugin, follow these instructions.

1. Open your terminal and go to your Craft project:

        cd /path/to/project

2. Then tell Composer to load the plugin:

        composer require imarc/craft-googlecustomsearch

3. In the Control Panel, go to Settings → Plugins and click the “Install” button for googlecustomsearch.

## Configuring

You will need a **Search Engine ID** and **API Key** from Google.

**Search Engine ID** - On the [Custom Search Engine Control Panel](http://www.google.com/cse/manage/all), create a new search engine for the site you would like to integrate. Once created, you can retrieve your Search Engine ID from the *Setup* tab.

**API Key** - **If you're using the free tier**, visit the [Google Developers Console](https://console.developers.google.com) and create a project for your search engine. Within your project, you’ll need to enable the *Custom Search API* from the *APIs* tab. Finally, on the *Credentials* tab, you will need to create a Public API access key by selecting the *Create new Key* option and choosing *Server key*. The API Key will now be available. **If you're using a paid tier**, you can find your API key in the [Control Panel](http://www.google.com/cse/manage/all) in the *Business > XML & JSON* tab.

The credentials can either be added from Craft's plugin settings or within `config/googlecustomsearch.php`.

```(php)
<?php
return [
    "apiKey" => getenv('GOOGLE_SEARCH_API_KEY'),
    "searchEngineId" => getenv('GOOGLE_SEARCH_ENGINE_ID'),
];
```

## Usage

In your twig template, retrieve search results from Google by passing it your search query, which you can then iterate over to display:

```(twig)
{% set response = craft.googlecustomsearch.performSearch('google') %}
```

Here is a complete example with pagination:

```(twig)
{% extends "_layout" %}

{% set query = craft.request.getParam('q') %}
{% set page = craft.request.getParam('page') ?: '1' %}
{% set title = "Search" %}

{% if query %}
	{% set response = craft.googlecustomsearch.performSearch(query, page) %}
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
							<a href="{{ url('search', {q:query, page:(page-1)}) }}" class="prev"></i>Previous</a>
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

## Credits

Brought to you by [Imarc](https://www.imarc.com)
