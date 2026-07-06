<?php
/**
 * Resolves a GitHub token from the configured spec.
 *
 * @package Itzmekhokan\GitHubPluginUpdater
 */

declare( strict_types=1 );

namespace Itzmekhokan\GitHubPluginUpdater\Auth;

use Itzmekhokan\GitHubPluginUpdater\Config\PluginConfig;

/**
 * Resolves a Personal Access Token from, in order of the config spec:
 *   - "env:VAR"       an environment variable
 *   - "constant:NAME" a PHP constant (typically defined in wp-config.php)
 *   - "option"        a WordPress option (see note on encryption below)
 *
 * If no per-plugin spec is set, a global constant fallback is used so a site
 * owner can define one token for all managed plugins in wp-config.php.
 */
final class TokenResolver {

	/**
	 * Global fallback constant name.
	 */
	public const GLOBAL_CONSTANT = 'GITHUB_PLUGIN_UPDATER_TOKEN';

	/**
	 * Option name used when the spec is "option".
	 *
	 * NOTE: v1.0 stores this as-is. v1.1 will encrypt at rest using a key
	 * derived from WP salts. Prefer env/constant for production until then.
	 */
	public const OPTION_NAME = 'github_plugin_updater_token';

	private PluginConfig $config;

	public function __construct( PluginConfig $config ) {
		$this->config = $config;
	}

	/**
	 * Resolve the token, or null when none is available.
	 */
	public function resolve(): ?string {
		$spec = $this->config->token_spec();

		if ( null !== $spec ) {
			$token = $this->resolve_spec( $spec );
			if ( null !== $token ) {
				return $token;
			}
		}

		// Global fallback for site-wide configuration.
		if ( \defined( self::GLOBAL_CONSTANT ) ) {
			$value = \constant( self::GLOBAL_CONSTANT );
			return is_string_nonempty( $value ) ? $value : null;
		}

		return null;
	}

	private function resolve_spec( string $spec ): ?string {
		if ( 0 === \strpos( $spec, 'env:' ) ) {
			$value = \getenv( \substr( $spec, 4 ) );
			return is_string_nonempty( $value ) ? $value : null;
		}

		if ( 0 === \strpos( $spec, 'constant:' ) ) {
			$name = \substr( $spec, 9 );
			if ( \defined( $name ) ) {
				$value = \constant( $name );
				return is_string_nonempty( $value ) ? $value : null;
			}
			return null;
		}

		if ( 'option' === $spec ) {
			if ( function_exists( 'get_option' ) ) {
				$value = get_option( self::OPTION_NAME, '' );
				return is_string_nonempty( $value ) ? (string) $value : null;
			}
			return null;
		}

		return null;
	}
}

/**
 * Small helper kept in-namespace to avoid a bare function collision.
 *
 * @param mixed $value Candidate value.
 */
function is_string_nonempty( $value ): bool {
	return \is_string( $value ) && '' !== \trim( $value );
}
