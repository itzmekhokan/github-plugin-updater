<?php
/**
 * Wires the config, GitHub client, and WP hooks together for one plugin.
 *
 * @package Itzmekhokan\GitHubPluginUpdater
 */

declare( strict_types=1 );

namespace Itzmekhokan\GitHubPluginUpdater;

use Itzmekhokan\GitHubPluginUpdater\Auth\TokenResolver;
use Itzmekhokan\GitHubPluginUpdater\Config\PluginConfig;
use Itzmekhokan\GitHubPluginUpdater\GitHub\Client;
use Itzmekhokan\GitHubPluginUpdater\GitHub\ReleaseResolver;
use Itzmekhokan\GitHubPluginUpdater\GitHub\RemoteInfo;
use Itzmekhokan\GitHubPluginUpdater\Wp\DetailsModal;
use Itzmekhokan\GitHubPluginUpdater\Wp\DownloadAuth;
use Itzmekhokan\GitHubPluginUpdater\Wp\FolderFixer;
use Itzmekhokan\GitHubPluginUpdater\Wp\UpdateInjector;

/**
 * Registers the four WordPress hooks that provide the update experience for a
 * single GitHub-hosted plugin.
 */
final class Updater {

	private PluginConfig $config;
	private ?string $token;
	private ReleaseResolver $releases;
	private DownloadAuth $download_auth;

	/**
	 * Memoized remote lookup for the current request.
	 *
	 * @var RemoteInfo|false|null false means "resolved to nothing".
	 */
	private $remote_cache = null;

	public function __construct( PluginConfig $config ) {
		$this->config        = $config;
		$this->token         = ( new TokenResolver( $config ) )->resolve();
		$this->releases      = new ReleaseResolver( new Client( $this->token ), $config );
		$this->download_auth = new DownloadAuth();
	}

	/**
	 * Register all hooks. Safe to call once per plugin.
	 */
	public function register(): void {
		if ( ! $this->config->is_valid() || ! function_exists( 'add_filter' ) ) {
			return;
		}

		$resolver = array( $this, 'remote' );

		$injector = new UpdateInjector( $this->config, $resolver );
		add_filter( 'pre_set_site_transient_update_plugins', array( $injector, 'inject' ) );

		$modal = new DetailsModal( $this->config, $resolver );
		add_filter( 'plugins_api', array( $modal, 'provide' ), 10, 3 );

		$fixer = new FolderFixer( $this->config );
		add_filter( 'upgrader_source_selection', array( $fixer, 'fix' ), 10, 4 );

		// Register the download URL for private auth once we know it.
		add_filter( 'upgrader_pre_download', array( $this, 'prime_download_auth' ), 5, 2 );
		add_filter( 'upgrader_pre_download', array( $this->download_auth, 'maybe_download' ), 10, 2 );
	}

	/**
	 * Ensure the resolved download URL is registered for authenticated download
	 * just before WP downloads the package.
	 *
	 * @param bool   $reply   Short-circuit value.
	 * @param string $package Package URL.
	 * @return bool
	 */
	public function prime_download_auth( $reply, $package = '' ) {
		$remote = $this->remote();
		if ( $remote instanceof RemoteInfo && $remote->download_url() === $package ) {
			$this->download_auth->register( $package, $this->token );
		}
		return $reply;
	}

	/**
	 * Resolve (and memoize) the remote candidate for this request.
	 *
	 * @return RemoteInfo|null
	 */
	public function remote(): ?RemoteInfo {
		if ( null === $this->remote_cache ) {
			$this->remote_cache = $this->releases->resolve() ?? false;
		}
		return $this->remote_cache instanceof RemoteInfo ? $this->remote_cache : null;
	}

	public function config(): PluginConfig {
		return $this->config;
	}
}
