# github-plugin-updater

Give any **GitHub-hosted WordPress plugin** the same "update available" notice
and one-click update experience as wp.org — without publishing to wp.org.
Works with **public**, **private**, and **organization** repositories.

This is a Composer **library** you require inside your own plugin. There is no
separate plugin for site owners to install and nothing to configure at the site
level (beyond a token for private repos).

## Why

Composer VCS repositories can install a GitHub plugin, but updating still means
a developer running `composer update`. This library instead checks GitHub
**releases** (or tags) at runtime and surfaces updates directly in the WordPress
admin, so site owners update with one click like any wp.org plugin.

## Install

```bash
composer require itzmekhokan/github-plugin-updater
```

## Usage

### Zero-config (headers)

Add an `Update URI` header pointing at your repo, then boot from your main file:

```php
/**
 * Plugin Name: Awesome Plugin
 * Version:     1.4.0
 * Update URI:  https://github.com/acme/awesome-plugin
 */

require __DIR__ . '/vendor/autoload.php';

\Itzmekhokan\GitHubPluginUpdater\Bootstrap::auto( __FILE__ );
```

### Explicit config

```php
\Itzmekhokan\GitHubPluginUpdater\Bootstrap::boot( __FILE__, array(
	'repository'        => 'acme/awesome-plugin',
	'source'            => 'release',   // 'release' (default) or 'tag'
	'prefer-asset'      => true,        // use an uploaded .zip asset over the source zipball
	'allow-prereleases' => false,
	'token'             => 'env:GH_UPDATER_TOKEN', // omit entirely for public repos
) );
```

### composer.json (extra)

Instead of passing config in PHP, declare it once in the plugin's
`composer.json` and call `Bootstrap::auto( __FILE__ )`:

```jsonc
{
	"extra": {
		"github-plugin-updater": {
			"repository": "acme/awesome-plugin",
			"source": "release",
			"prefer-asset": true,
			"allow-prereleases": false,
			"token": "env:GH_UPDATER_TOKEN"
		}
	}
}
```

## Private & organization repositories

Provide a GitHub Personal Access Token with `repo` (or fine-grained *Contents:
read*) scope. The `token` value is a **spec**, resolved in this order:

| Spec              | Source                                                        |
|-------------------|--------------------------------------------------------------|
| `env:VAR`         | Environment variable `VAR`                                    |
| `constant:NAME`   | PHP constant `NAME` (define it in `wp-config.php`)            |
| `option`          | WordPress option (see note)                                   |

If no per-plugin token is set, a global constant is used as a fallback so a site
owner can configure one token for all managed plugins:

```php
// wp-config.php
define( 'GITHUB_PLUGIN_UPDATER_TOKEN', 'ghp_xxx' );
```

> **Note:** the `option` source stores the token as a plain option in v1.0.
> Prefer `env`/`constant` in production. Encrypted at-rest storage lands in v1.1.

## How it works

Four WordPress hooks:

- `pre_set_site_transient_update_plugins` — injects the "update available" record.
- `plugins_api` — serves the "View details" modal from the GitHub release notes.
- `upgrader_source_selection` — renames GitHub's `owner-repo-<hash>/` folder to
  your plugin slug.
- `upgrader_pre_download` — downloads private archives with the correct two-step
  auth dance (send auth to the API host, drop it on the signed redirect).

Responses are cached for 12 hours and revalidated with ETags, so the checker
stays well under GitHub's 60 req/hr anonymous rate limit.

## Requirements

- PHP 7.4+
- WordPress 5.8+

## Roadmap

- **v1.1** — encrypted token storage + settings UI, `wp gpu check` WP-CLI command, branch/prerelease tracking.
- **v1.2** — provider abstraction (GitLab, Bitbucket), release-asset checksum/attestation verification.

## License

GPL-2.0-or-later
