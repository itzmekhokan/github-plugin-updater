<?php
/**
 * Normalized description of the newest available release/tag on GitHub.
 *
 * @package Itzmekhokan\GitHubPluginUpdater
 */

declare( strict_types=1 );

namespace Itzmekhokan\GitHubPluginUpdater\GitHub;

/**
 * Immutable snapshot of the remote update candidate.
 */
final class RemoteInfo {

	private string $version;
	private string $download_url;
	private string $changelog;
	private string $html_url;
	private string $published_at;

	/**
	 * @param array<string,string> $data Normalized values.
	 */
	public function __construct( array $data ) {
		$this->version      = (string) ( $data['version'] ?? '' );
		$this->download_url = (string) ( $data['download_url'] ?? '' );
		$this->changelog    = (string) ( $data['changelog'] ?? '' );
		$this->html_url     = (string) ( $data['html_url'] ?? '' );
		$this->published_at = (string) ( $data['published_at'] ?? '' );
	}

	public function version(): string {
		return $this->version;
	}

	public function download_url(): string {
		return $this->download_url;
	}

	public function changelog(): string {
		return $this->changelog;
	}

	public function html_url(): string {
		return $this->html_url;
	}

	public function published_at(): string {
		return $this->published_at;
	}

	public function has_download(): bool {
		return '' !== $this->version && '' !== $this->download_url;
	}
}
