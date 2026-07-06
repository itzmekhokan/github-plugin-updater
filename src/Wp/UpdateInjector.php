<?php
/**
 * Injects the "update available" record into WordPress's update transient.
 *
 * @package Itzmekhokan\GitHubPluginUpdater
 */

declare( strict_types=1 );

namespace Itzmekhokan\GitHubPluginUpdater\Wp;

use Itzmekhokan\GitHubPluginUpdater\Config\PluginConfig;
use Itzmekhokan\GitHubPluginUpdater\GitHub\RemoteInfo;

/**
 * Adds this plugin to the list WordPress uses to render the "update available"
 * badge and the Plugins-screen notice.
 */
final class UpdateInjector {

	private PluginConfig $config;

	/**
	 * @var callable():?RemoteInfo Lazily resolves the remote candidate.
	 */
	private $resolver;

	/**
	 * @param PluginConfig          $config   Plugin config.
	 * @param callable():?RemoteInfo $resolver Returns the remote info (cached upstream).
	 */
	public function __construct( PluginConfig $config, callable $resolver ) {
		$this->config   = $config;
		$this->resolver = $resolver;
	}

	/**
	 * Hook: pre_set_site_transient_update_plugins.
	 *
	 * @param mixed $transient The update_plugins transient.
	 * @return mixed
	 */
	public function inject( $transient ) {
		if ( ! \is_object( $transient ) ) {
			return $transient;
		}
		// WP calls this repeatedly; only act once the checked list is present.
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$remote = ( $this->resolver )();
		if ( ! $remote instanceof RemoteInfo || ! $remote->has_download() ) {
			return $transient;
		}

		$basename = $this->config->basename();
		$current  = $this->config->current_version();

		if ( \version_compare( $remote->version(), $current, '>' ) ) {
			$transient->response[ $basename ] = $this->build_update_object( $remote );
		} else {
			// No update: populate no_update so the row shows "up to date"
			// and WP doesn't fall back to a wp.org lookup for this slug.
			$transient->no_update[ $basename ] = $this->build_update_object( $remote, false );
		}

		return $transient;
	}

	/**
	 * Build the object WP expects in response/no_update.
	 */
	private function build_update_object( RemoteInfo $remote, bool $is_update = true ): object {
		return (object) array(
			'id'          => 'github.com/' . $this->config->repository(),
			'slug'        => $this->config->slug(),
			'plugin'      => $this->config->basename(),
			'new_version' => $is_update ? $remote->version() : $this->config->current_version(),
			'url'         => $remote->html_url(),
			'package'     => $is_update ? $remote->download_url() : '',
			'tested'      => '',
			'requires'    => '',
			'icons'       => array(),
			'banners'     => array(),
		);
	}
}
