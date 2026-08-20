# Site Search Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## 3.0.0 - 2026-08-20

### Added
- Google Vertex AI Search provider (Discovery Engine), authenticated via service account JSON key or Application Default Credentials
- AddSearch provider
- Per-site provider selection
- `raw` property on search results with the provider's decoded raw response

### Changed
- Plugin renamed from Google Custom Search (`imarc/craft-googlecustomsearch`) to Site Search (`imarc/craft-sitesearch`); handle is now `sitesearch`
- Template variable is now `craft.siteSearch`
- Requires Craft CMS 5.x and PHP 8.2+

### Deprecated
- `craft.googlecustomsearch` (still works as an alias of `craft.siteSearch`)

## 2.0.0 - 2018-08-20

### Added
- Initial release. Updated for Craft 3
