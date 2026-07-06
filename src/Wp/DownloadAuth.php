<?php
/**
 * Handles authenticated downloads of private release archives.
 *
 * @package Itzmekhokan\GitHubPluginUpdater
 */

declare( strict_types=1 );

namespace Itzmekhokan\GitHubPluginUpdater\Wp;

/**
 * Downloads private GitHub archives with the correct two-step auth dance.
 *
 * GitHub's API download URLs (zipball or asset `url`) return a 302 redirect to
 * a signed codeload/S3 URL. The `Authorization` header must be sent to the API
 * host but MUST NOT be forwarded onto the signed URL, or S3 rejects the request
 * with "only one auth mechanism allowed". WP's own redirect following would
 * forward the header, so we perform the request without auto-redirects, then
 * fetch the signed location cleanly.
 */
final class DownloadAuth {

	/**
	 * @var array<string,string> Map of download URL => token, registered per plugin.
	 */
	private array $authenticated_urls = array();

	/**
	 * Register a download URL that requires a token.
	 */
	public function register( string $download_url, ?string $token ): void {
		if ( null !== $token && '' !== $download_url ) {
			$this->authenticated_urls[ $download_url ] = $token;
		}
	}

	/**
	 * Hook: upgrader_pre_download. Returns false to let WP proceed normally,
	 * or a local file path / WP_Error when we handle the download ourselves.
	 *
	 * @param bool   $reply   Short-circuit value (default false).
	 * @param string $package The package URL being downloaded.
	 * @return bool|string|\WP_Error
	 */
	public function maybe_download( $reply, $package = '' ) {
		if ( ! isset( $this->authenticated_urls[ $package ] ) ) {
			return $reply; // Not ours (or public) — let WP handle it.
		}

		$token = $this->authenticated_urls[ $package ];

		// Accept: octet-stream is required so the asset API returns the binary,
		// not JSON metadata.
		$response = wp_remote_get(
			$package,
			array(
				'timeout'     => 300,
				'redirection' => 0, // Do NOT auto-follow; we must drop auth first.
				'headers'     => array(
					'Authorization' => 'Bearer ' . $token,
					'Accept'        => 'application/octet-stream',
					'User-Agent'    => 'wp-github-plugin-updater',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		// Redirect to the signed URL: fetch it WITHOUT the Authorization header.
		if ( $code >= 300 && $code < 400 ) {
			$location = wp_remote_retrieve_header( $response, 'location' );
			if ( '' === $location ) {
				return new \WP_Error( 'gpu_no_location', 'GitHub redirect had no Location header.' );
			}
			return $this->stream_to_temp( $location, array() );
		}

		// Some responses stream the archive directly (200 with body).
		if ( 200 === $code ) {
			$body = wp_remote_retrieve_body( $response );
			if ( '' !== $body ) {
				return $this->write_temp( $body );
			}
		}

		return new \WP_Error( 'gpu_download_failed', 'Unexpected response code ' . $code . ' from GitHub.' );
	}

	/**
	 * Download a URL with the given headers into a temp file.
	 *
	 * @param array<string,string> $headers Request headers.
	 * @return string|\WP_Error Temp file path or error.
	 */
	private function stream_to_temp( string $url, array $headers ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 300,
				'redirection' => 5,
				'headers'     => $headers,
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return new \WP_Error( 'gpu_signed_failed', 'Failed to download signed archive URL.' );
		}
		return $this->write_temp( wp_remote_retrieve_body( $response ) );
	}

	/**
	 * Write bytes to a WP temp file and return its path.
	 *
	 * @return string|\WP_Error
	 */
	private function write_temp( string $bytes ) {
		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$tmp = wp_tempnam( 'gpu-update.zip' );
		if ( ! $tmp ) {
			return new \WP_Error( 'gpu_tempnam', 'Could not create a temporary file.' );
		}
		if ( false === \file_put_contents( $tmp, $bytes ) ) {
			return new \WP_Error( 'gpu_write', 'Could not write the downloaded archive.' );
		}
		return $tmp;
	}
}
