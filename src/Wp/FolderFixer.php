<?php
/**
 * Renames the extracted GitHub archive folder to the plugin slug.
 *
 * @package Itzmekhokan\GitHubPluginUpdater
 */

declare( strict_types=1 );

namespace Itzmekhokan\GitHubPluginUpdater\Wp;

use Itzmekhokan\GitHubPluginUpdater\Config\PluginConfig;

/**
 * GitHub source archives extract to a directory named like
 * "owner-repo-<hash>/". WordPress requires the extracted folder to match the
 * plugin slug, so we rename it during upgrader_source_selection. Scoped by the
 * in-flight package so it only touches this plugin's own update.
 */
final class FolderFixer {

	private PluginConfig $config;

	public function __construct( PluginConfig $config ) {
		$this->config = $config;
	}

	/**
	 * Hook: upgrader_source_selection.
	 *
	 * @param string       $source        Path to the extracted source.
	 * @param string       $remote_source Top-level extraction path.
	 * @param mixed        $upgrader      WP_Upgrader instance.
	 * @param array<mixed> $hook_extra    Extra context (contains the plugin basename).
	 * @return string|\WP_Error
	 */
	public function fix( $source, $remote_source = '', $upgrader = null, $hook_extra = array() ) {
		// Only act on the update for this specific plugin.
		$plugin = \is_array( $hook_extra ) ? ( $hook_extra['plugin'] ?? '' ) : '';
		if ( '' !== $plugin && $plugin !== $this->config->basename() ) {
			return $source;
		}

		$desired = \trailingslashit( $remote_source ) . $this->config->slug();
		$desired = \trailingslashit( $desired );

		if ( \untrailingslashit( $source ) === \untrailingslashit( $desired ) ) {
			return $source; // Already correctly named.
		}

		global $wp_filesystem;
		if ( $wp_filesystem && $wp_filesystem->move( $source, $desired, true ) ) {
			return $desired;
		}

		return new \WP_Error(
			'gpu_rename_failed',
			'Could not rename the extracted plugin folder to "' . $this->config->slug() . '".'
		);
	}
}
