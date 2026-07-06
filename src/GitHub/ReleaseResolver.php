<?php
/**
 * Determines the newest eligible release/tag and how to download it.
 *
 * @package Itzmekhokan\GitHubPluginUpdater
 */

declare( strict_types=1 );

namespace Itzmekhokan\GitHubPluginUpdater\GitHub;

use Itzmekhokan\GitHubPluginUpdater\Config\PluginConfig;

/**
 * Talks to the GitHub client to resolve the update candidate for a plugin,
 * honouring the configured source (release|tag), prerelease policy, and the
 * asset-vs-zipball download preference.
 */
final class ReleaseResolver {

	private Client $client;
	private PluginConfig $config;

	public function __construct( Client $client, PluginConfig $config ) {
		$this->client = $client;
		$this->config = $config;
	}

	/**
	 * Resolve the newest eligible candidate, or null if none is usable.
	 */
	public function resolve(): ?RemoteInfo {
		return 'tag' === $this->config->source()
			? $this->resolve_from_tags()
			: $this->resolve_from_releases();
	}

	private function resolve_from_releases(): ?RemoteInfo {
		$repo = $this->config->repository();

		// When prereleases are allowed we must scan the list; otherwise the
		// dedicated "latest" endpoint already excludes prereleases and drafts.
		if ( $this->config->allow_prereleases() ) {
			$releases = $this->client->get( "/repos/{$repo}/releases?per_page=10" );
			$release  = $this->pick_release( \is_array( $releases ) ? $releases : array() );
		} else {
			$release = $this->client->get( "/repos/{$repo}/releases/latest" );
		}

		if ( ! \is_array( $release ) || empty( $release['tag_name'] ) ) {
			return $this->resolve_from_tags(); // Graceful fallback.
		}

		$tag     = (string) $release['tag_name'];
		$version = self::normalize_version( $tag );

		return new RemoteInfo(
			array(
				'version'      => $version,
				'download_url' => $this->pick_download_url( $release, $tag ),
				'changelog'    => (string) ( $release['body'] ?? '' ),
				'html_url'     => (string) ( $release['html_url'] ?? '' ),
				'published_at' => (string) ( $release['published_at'] ?? '' ),
			)
		);
	}

	private function resolve_from_tags(): ?RemoteInfo {
		$repo = $this->config->repository();
		$tags = $this->client->get( "/repos/{$repo}/tags?per_page=20" );
		if ( ! \is_array( $tags ) || array() === $tags ) {
			return null;
		}

		// Choose the highest semantic version among the tags.
		$best = null;
		foreach ( $tags as $tag ) {
			if ( empty( $tag['name'] ) ) {
				continue;
			}
			$candidate = self::normalize_version( (string) $tag['name'] );
			if ( null === $best || \version_compare( $candidate, self::normalize_version( (string) $best['name'] ), '>' ) ) {
				$best = $tag;
			}
		}
		if ( null === $best ) {
			return null;
		}

		$name = (string) $best['name'];
		return new RemoteInfo(
			array(
				'version'      => self::normalize_version( $name ),
				'download_url' => (string) ( $best['zipball_url'] ?? "https://api.github.com/repos/{$repo}/zipball/{$name}" ),
				'changelog'    => '',
				'html_url'     => "https://github.com/{$repo}/releases/tag/{$name}",
				'published_at' => '',
			)
		);
	}

	/**
	 * Pick the newest non-draft release, filtered by prerelease policy.
	 *
	 * @param array<int,array<string,mixed>> $releases Release list.
	 * @return array<string,mixed>|null
	 */
	private function pick_release( array $releases ): ?array {
		$best = null;
		foreach ( $releases as $release ) {
			if ( ! empty( $release['draft'] ) ) {
				continue;
			}
			if ( ! $this->config->allow_prereleases() && ! empty( $release['prerelease'] ) ) {
				continue;
			}
			if ( empty( $release['tag_name'] ) ) {
				continue;
			}
			if ( null === $best
				|| \version_compare(
					self::normalize_version( (string) $release['tag_name'] ),
					self::normalize_version( (string) $best['tag_name'] ),
					'>'
				)
			) {
				$best = $release;
			}
		}
		return $best;
	}

	/**
	 * Choose the download URL: an uploaded .zip asset when preferred and
	 * present, otherwise the source zipball for the tag.
	 *
	 * @param array<string,mixed> $release Release payload.
	 */
	private function pick_download_url( array $release, string $tag ): string {
		if ( $this->config->prefer_asset() && ! empty( $release['assets'] ) && \is_array( $release['assets'] ) ) {
			foreach ( $release['assets'] as $asset ) {
				$name = (string) ( $asset['name'] ?? '' );
				if ( '' !== $name && \preg_match( '/\.zip$/i', $name ) ) {
					// api.github.com/.../assets/{id} URL works for private repos
					// with a token; browser_download_url does not.
					return (string) ( $asset['url'] ?? $asset['browser_download_url'] ?? '' );
				}
			}
		}

		if ( ! empty( $release['zipball_url'] ) ) {
			return (string) $release['zipball_url'];
		}

		$repo = $this->config->repository();
		return "https://api.github.com/repos/{$repo}/zipball/{$tag}";
	}

	/**
	 * Strip a leading "v" and surrounding whitespace from a tag.
	 */
	public static function normalize_version( string $tag ): string {
		return \ltrim( \trim( $tag ), 'vV' );
	}
}
