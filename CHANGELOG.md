# Site Search Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## 4.0.0 - Unreleased

### Added
- Google Vertex AI Search provider (Discovery Engine), authenticated via service account JSON key or Application Default Credentials
- AddSearch provider
- Per-site provider selection
- `raw` property on search results with the provider's decoded raw response

### Changed
- Plugin renamed from Google Custom Search (`imarc/craft-googlecustomsearch`) to Site Search (`imarc/craft-sitesearch`); handle is now `sitesearch`
- Template variable is now `craft.siteSearch`

### Deprecated
- `craft.googlecustomsearch` (still works as an alias of `craft.siteSearch`)

## Unreleased (3.x)

### Added
- Per-site plugin settings for Craft multisite installs, with a site menu on the plugin settings page

### Changed
- Supports Craft CMS 3.x, 4.x, and 5.x (PHP 8.0+)
- Plugin settings UI uses the native site menu on Craft 4/5 when available

## 2.0.0 - 2018-08-20

### Added
- Initial release. Updated for Craft 3
