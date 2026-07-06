<?php
/**
 * Public entry point developers call from their plugin.
 *
 * @package Itzmekhokan\GitHubPluginUpdater
 */

declare( strict_types=1 );

namespace Itzmekhokan\GitHubPluginUpdater;

use Itzmekhokan\GitHubPluginUpdater\Config\ConfigLoader;

/**
 * Convenience facade for registering an updater.
 *
 * Usage — explicit config:
 *
 *     \Itzmekhokan\GitHubPluginUpdater\Bootstrap::boot( __FILE__, array(
 *         'repository' => 'acme/awesome-plugin',
 *         'token'      => 'env:GH_UPDATER_TOKEN', // omit for public repos
 *     ) );
 *
 * Usage — zero config (reads the plugin's `Update URI` header and any
 * composer.json "extra.github-plugin-updater" block in the plugin folder):
 *
 *     \Itzmekhokan\GitHubPluginUpdater\Bootstrap::auto( __FILE__ );
 */
final class Bootstrap {

	/**
	 * Registered updaters keyed by plugin basename, to avoid double-registration.
	 *
	 * @var array<string,Updater>
	 */
	private static array $registered = array();

	/**
	 * Boot with an explicit config array merged over header/composer values.
	 *
	 * @param string              $plugin_file Absolute path to the plugin main file (__FILE__).
	 * @param array<string,mixed> $config      Explicit config overrides.
	 */
	public static function boot( string $plugin_file, array $config = array() ): ?Updater {
		$plugin_config = ConfigLoader::load( $plugin_file, $config );
		$key           = $plugin_config->basename();

		if ( isset( self::$registered[ $key ] ) ) {
			return self::$registered[ $key ];
		}

		$updater = new Updater( $plugin_config );
		$updater->register();

		self::$registered[ $key ] = $updater;
		return $updater;
	}

	/**
	 * Boot using only the plugin's headers / composer.json extra block.
	 *
	 * @param string $plugin_file Absolute path to the plugin main file (__FILE__).
	 */
	public static function auto( string $plugin_file ): ?Updater {
		return self::boot( $plugin_file, array() );
	}

	/**
	 * @return array<string,Updater>
	 */
	public static function registered(): array {
		return self::$registered;
	}
}
