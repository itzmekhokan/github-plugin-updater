<?php
/**
 * Builds a normalized PluginConfig from the various supported config sources.
 *
 * @package Itzmekhokan\GitHubPluginUpdater
 */

declare( strict_types=1 );

namespace Itzmekhokan\GitHubPluginUpdater\Config;

/**
 * Resolves configuration from (in order of precedence):
 *   1. An explicit config array passed by the developer.
 *   2. The plugin's `Update URI` / `Version` headers.
 *   3. A `composer.json` "extra.github-plugin-updater" block in the plugin dir.
 */
final class ConfigLoader {

	private const HEADER_MAP = array(
		'name'         => 'Plugin Name',
		'version'      => 'Version',
		'update_uri'   => 'Update URI',
		// Optional dedicated header for repos that differ from Update URI.
		'github_repo'  => 'GitHub Plugin URI',
	);

	/**
	 * Build config from the plugin's main file, merging header data with any
	 * explicit overrides the developer passed to Bootstrap::boot().
	 *
	 * @param string              $plugin_file Absolute path to the plugin main file.
	 * @param array<string,mixed> $overrides   Explicit config values (win over headers).
	 */
	public static function load( string $plugin_file, array $overrides = array() ): PluginConfig {
		$headers = self::read_headers( $plugin_file );

		$repository = $overrides['repository']
			?? self::normalize_repository( $headers['github_repo'] ?? $headers['update_uri'] ?? '' );

		$composer = self::read_composer_extra( \dirname( $plugin_file ) );

		$data = array(
			'plugin_file'       => $plugin_file,
			'basename'          => self::basename( $plugin_file ),
			'slug'              => self::slug( $plugin_file ),
			'current_version'   => $overrides['version'] ?? $headers['version'] ?? '0.0.0',
			'repository'        => $overrides['repository'] ?? $composer['repository'] ?? $repository,
			'source'            => $overrides['source'] ?? $composer['source'] ?? 'release',
			'prefer_asset'      => $overrides['prefer_asset'] ?? $composer['prefer-asset'] ?? true,
			'allow_prereleases' => $overrides['allow_prereleases'] ?? $composer['allow-prereleases'] ?? false,
			'token'             => $overrides['token'] ?? $composer['token'] ?? null,
		);

		return new PluginConfig( $data );
	}

	/**
	 * Read the relevant plugin headers. Uses WP's get_file_data when available.
	 *
	 * @return array<string,string>
	 */
	private static function read_headers( string $plugin_file ): array {
		if ( function_exists( 'get_file_data' ) ) {
			return get_file_data( $plugin_file, self::HEADER_MAP, 'plugin' );
		}

		// Minimal fallback parser for non-WP contexts (e.g. unit tests).
		$contents = \is_readable( $plugin_file ) ? (string) \file_get_contents( $plugin_file ) : '';
		$out      = array();
		foreach ( self::HEADER_MAP as $key => $label ) {
			if ( \preg_match( '/^[ \t\/*#@]*' . \preg_quote( $label, '/' ) . ':(.*)$/mi', $contents, $m ) ) {
				$out[ $key ] = \trim( $m[1] );
			} else {
				$out[ $key ] = '';
			}
		}
		return $out;
	}

	/**
	 * Read the optional composer.json "extra.github-plugin-updater" block.
	 *
	 * @return array<string,mixed>
	 */
	private static function read_composer_extra( string $plugin_dir ): array {
		$path = \rtrim( $plugin_dir, '/\\' ) . '/composer.json';
		if ( ! \is_readable( $path ) ) {
			return array();
		}
		$json = \json_decode( (string) \file_get_contents( $path ), true );
		if ( ! \is_array( $json ) ) {
			return array();
		}
		$extra = $json['extra']['github-plugin-updater'] ?? array();
		return \is_array( $extra ) ? $extra : array();
	}

	/**
	 * Normalize an "Update URI" like https://github.com/owner/repo into "owner/repo".
	 */
	private static function normalize_repository( string $value ): string {
		$value = \trim( $value );
		if ( '' === $value ) {
			return '';
		}
		if ( \preg_match( '#github\.com[/:]([^/]+/[^/#?]+)#i', $value, $m ) ) {
			return \rtrim( \preg_replace( '/\.git$/', '', $m[1] ), '/' );
		}
		// Already in owner/repo form.
		if ( \preg_match( '#^[^/\s]+/[^/\s]+$#', $value ) ) {
			return $value;
		}
		return '';
	}

	private static function basename( string $plugin_file ): string {
		if ( function_exists( 'plugin_basename' ) ) {
			return plugin_basename( $plugin_file );
		}
		$dir  = \basename( \dirname( $plugin_file ) );
		$file = \basename( $plugin_file );
		return $dir . '/' . $file;
	}

	private static function slug( string $plugin_file ): string {
		return \basename( \dirname( $plugin_file ) );
	}
}
