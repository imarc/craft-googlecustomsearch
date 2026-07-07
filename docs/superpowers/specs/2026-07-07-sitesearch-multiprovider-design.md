# Site Search Plugin v4 — Multi-Provider Design

Date: 2026-07-07
Status: Approved (design), pending implementation

## Goal

Rebrand `imarc/craft-googlecustomsearch` as **Site Search** (`imarc/craft-sitesearch`) and add two new search providers alongside Google Custom Search: **Google Vertex AI Search** and **AddSearch**. Provider is selected per site. Templates keep working with a unified result shape.

## Rebrand (same repo, new major v4.0)

- Composer package renamed to `imarc/craft-sitesearch`, with `"replace": {"imarc/craft-googlecustomsearch": "self.version"}`. Old Packagist name gets a deprecation notice pointing at the new package.
- Plugin handle: `sitesearch`. Namespace: `imarc\sitesearch`. Display name: "Site Search".
- Install migration: if project config contains `plugins.googlecustomsearch` settings, copy them into `plugins.sitesearch` (mapping legacy `apiKey`/`searchEngineId` and `siteSettings` into the new structure with `provider: gcs`).
- Template variable: `craft.siteSearch`. `craft.googleCustomSearch` remains as a deprecated alias that logs a Craft deprecation warning; removed in the next major.

## Architecture — provider adapters

- `src/adapters/AdapterInterface.php`:
  - `search(SearchRequest $request): SearchResults`
  - `testConnection(): array` — `['success' => bool, 'error' => ?string]`
- `SearchRequest`: terms, page, perPage, extra params (assoc array passed through to provider).
- Three adapters:
  - `GoogleCustomSearchAdapter` — current `SearchService` logic moved here unchanged in behavior (10/page cap, 100 total cap, pagemap image/thumbnail extraction).
  - `VertexSearchAdapter` — Google Vertex AI Search (Discovery Engine).
  - `AddSearchAdapter` — AddSearch.
- `SearchService` resolves the adapter from the current (or given) site's settings and delegates. Public API unchanged: `performSearch($terms, $page, $perPage, $extra)`, `testConnection()`, `throwOnFailure` behavior preserved.

## Unified result shape (+ raw)

Same object shape as v3, plus `raw`:

```
page, perPage, start, end, totalResults
results[]: title, snippet, htmlSnippet, link, image, thumbnail
raw: provider's decoded response (stdClass/array), for advanced template use
```

Error handling as today: provider error → throw `\Exception` when `throwOnFailure` (default), else log warning and return error response object.

## Settings (per-site, extends existing `siteSettings`)

Each site entry:

- `provider`: `gcs` | `vertex` | `addsearch` (default `gcs`)
- **gcs**: `apiKey`, `searchEngineId` (unchanged)
- **vertex**: `projectId`, `location` (default `global`), `engineId`, `serviceAccountFile` (path to service account JSON; env-parseable; blank = Application Default Credentials)
- **addsearch**: `siteKey` (public index key), optional `apiKey` (private indices)

All fields support Craft env-variable syntax (`$VAR`) via `App::parseEnv`. Legacy top-level `apiKey`/`searchEngineId` fallback preserved (treated as gcs).

Settings CP template: per-site provider dropdown toggles the matching field group. Connection test button works per provider.

## Vertex AI Search details

- Auth: `google/auth` composer package. `ServiceAccountCredentials` with scope `https://www.googleapis.com/auth/cloud-platform` when `serviceAccountFile` set; otherwise `ApplicationDefaultCredentials` (zero-config on GCP hosting). Access token cached in Craft's cache with TTL slightly under token expiry.
- Search call: `POST https://discoveryengine.googleapis.com/v1/projects/{projectId}/locations/{location}/collections/default_collection/engines/{engineId}/servingConfigs/default_search:search`
  - Body: `query`, `pageSize` (perPage), `offset` ((page-1)*perPage), plus `extra` merged in (e.g. filter). Request snippet/summary content spec so snippets come back.
- Mapping: `results[].document.derivedStructData` → `title`, `link`, snippet from `snippets[0].snippet` (html) with tag-stripped plain variant; image/thumbnail from `pagemap`-equivalent fields when present, else empty string. `totalSize` → totalResults.

## AddSearch details

- Search call: `GET https://api.addsearch.com/v1/search/{siteKey}` with `term`, `page`, `limit` params. Private indices: HTTP basic/API-key header per AddSearch docs. Verify exact current endpoint/params against AddSearch docs at implementation time.
- Mapping: `hits[]` → `title`, `url` → link, `highlight`/`meta_description` → snippet/htmlSnippet, `images.main` → image, `images.main_b64`/thumbnail equivalent → thumbnail (empty string when absent). `total_hits` → totalResults.

## Docs (README rewrite)

- Intro: multi-provider site search plugin.
- Setup walkthroughs:
  - **Google Custom Search**: as today (Programmable Search Engine + API key).
  - **Vertex AI Search**: create GCP project → enable Discovery Engine API → create data store (website) + search app → note project/location/engine IDs → create service account with `roles/discoveryengine.viewer` → download JSON key (or use ADC on GCP hosting) → plugin settings.
  - **AddSearch**: create account, get index/site key, optional API key for private index.
- Template usage examples incl. `raw`.
- Upgrade-from-v3 section: composer require new package, settings auto-migrate, `craft.googleCustomSearch` deprecated.

## Testing / success criteria

- Unit tests: each adapter's response mapping against fixture JSON; settings resolution (provider + fields per site, env parsing, legacy fallback); install migration mapping.
- Manual: CP connection test succeeds per provider; template search returns unified results for each provider.
- Success: existing v3 GCS installs upgrade with no template changes; Vertex and AddSearch sites return correctly mapped results.

## Out of scope

- Autocomplete/suggestions, analytics, indexing control, other providers (Algolia etc.).
