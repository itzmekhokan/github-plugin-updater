<?php
/**
 * Normalized configuration for a single GitHub-hosted plugin.
 *
 * @package Itzmekhokan\GitHubPluginUpdater
 */

declare( strict_types=1 );

namespace Itzmekhokan\GitHubPluginUpdater\Config;

/**
 * Immutable value object describing how one plugin should be updated from GitHub.
 */
final class PluginConfig {

	/**
	 * Absolute path to the plugin's main file.
	 *
	 * @var string
	 */
	private string $plugin_file;

	/**
	 * Plugin basename, e.g. "awesome-plugin/awesome-plugin.php".
	 *
	 * @var string
	 */
	private string $basename;

	/**
	 * Plugin slug, e.g. "awesome-plugin".
	 *
	 * @var string
	 */
	private string $slug;

	/**
	 * Currently installed version, read from the plugin header.
	 *
	 * @var string
	 */
	private string $current_version;

	/**
	 * GitHub repository in "owner/repo" form.
	 *
	 * @var string
	 */
	private string $repository;

	/**
	 * Update source: "release" or "tag".
	 *
	 * @var string
	 */
	private string $source;

	/**
	 * Prefer an uploaded release asset (.zip) over the raw source zipball.
	 *
	 * @var bool
	 */
	private bool $prefer_asset;

	/**
	 * Whether prereleases are eligible as updates.
	 *
	 * @var bool
	 */
	private bool $allow_prereleases;

	/**
	 * Token spec: "env:VAR", "constant:NAME", "option", or null for none.
	 *
	 * @var string|null
	 */
	private ?string $token_spec;

	/**
	 * @param array<string,mixed> $data Normalized config values.
	 */
	public function __construct( array $data ) {
		$this->plugin_file       = (string) ( $data['plugin_file'] ?? '' );
		$this->basename          = (string) ( $data['basename'] ?? '' );
		$this->slug              = (string) ( $data['slug'] ?? '' );
		$this->current_version   = (string) ( $data['current_version'] ?? '0.0.0' );
		$this->repository        = (string) ( $data['repository'] ?? '' );
		$this->source            = 'tag' === ( $data['source'] ?? '' ) ? 'tag' : 'release';
		$this->prefer_asset      = (bool) ( $data['prefer_asset'] ?? true );
		$this->allow_prereleases = (bool) ( $data['allow_prereleases'] ?? false );
		$this->token_spec        = isset( $data['token'] ) && '' !== $data['token'] ? (string) $data['token'] : null;
	}

	public function plugin_file(): string {
		return $this->plugin_file;
	}

	public function basename(): string {
		return $this->basename;
	}

	public function slug(): string {
		return $this->slug;
	}

	public function current_version(): string {
		return $this->current_version;
	}

	public function repository(): string {
		return $this->repository;
	}

	public function owner(): string {
		return explode( '/', $this->repository, 2 )[0] ?? '';
	}

	public function repo_name(): string {
		$parts = explode( '/', $this->repository, 2 );
		return $parts[1] ?? '';
	}

	public function source(): string {
		return $this->source;
	}

	public function prefer_asset(): bool {
		return $this->prefer_asset;
	}

	public function allow_prereleases(): bool {
		return $this->allow_prereleases;
	}

	public function token_spec(): ?string {
		return $this->token_spec;
	}

	/**
	 * Whether the config has the minimum required values to operate.
	 */
	public function is_valid(): bool {
		return '' !== $this->basename
			&& '' !== $this->slug
			&& false !== strpos( $this->repository, '/' );
	}
}
