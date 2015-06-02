Google Custom Search 
==================

A Craft CMS plugin that incorporates a Google Custom Search into your website.

Google offers [free](https://cse.google.com/cse) and [paid](https://www.google.com/work/search/products/gss.html) tiers for accessing their search results.

Installation
-----

0. Download & unzip
0. Move the /googlecustomsearch directory into craft/plugins
0. Install the plugin in the Craft Plugins control panel
0. Click into the Google Custom Search settings and fill in your credentials

Credentials
-----

**Search Engine ID** - On the [Custom Search Engine Control Panel](http://www.google.com/cse/manage/all), create a new search engine for the site you would like to integrate. Once created, you can retrieve your Search Engine ID from the *Setup* tab.

**API Key** - If you're using the free tier, visit the [Google Developers Console](https://console.developers.google.com) and create a project for your search engine. Within your project, you’ll need to enable the *Custom Search API* from the *APIs* tab. Finally, on the *Credentials* tab, you will need to create a Public API access key by selecting the *Create new Key* option and choosing *Server key*. The API Key will now be available.

If you're using a paid tier, you can find your API key in the [Control Panel](http://www.google.com/cse/manage/all) in the *Business > XML & JSON* tab.

Usage
-----

In your twig template, retrieve search results from Google by passing it your search query, which you can then iterate over to display:

```(twig)
{% set response = craft.googleCustomSearch.performSearch(query) %}
```

Template
-----

Here's an example search template with pagination which you can use to get up and running:

```(twig)
{% extends "_layout" %}

{% set query = craft.request.getParam('q') %}
{% set page = craft.request.getParam('page') ?: '1' %}
{% set title = "Search" %}

{% if query %}
	{% set response = craft.googleCustomSearch.performSearch(query, page) %}
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