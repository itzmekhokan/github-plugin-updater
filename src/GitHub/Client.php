<?php
/**
 * Minimal GitHub REST client with ETag revalidation and token auth.
 *
 * @package Itzmekhokan\GitHubPluginUpdater
 */

declare( strict_types=1 );

namespace Itzmekhokan\GitHubPluginUpdater\GitHub;

use Itzmekhokan\GitHubPluginUpdater\Cache\TransientCache;

/**
 * Performs authenticated, cached GET requests against the GitHub REST API.
 *
 * Caching strategy: every response's ETag is stored with its payload. On the
 * next call we send `If-None-Match`; a 304 response returns the cached payload
 * for free (no rate-limit cost) and refreshes the cache TTL.
 */
final class Client {

	private const API_BASE  = 'https://api.github.com';
	private const API_VER   = '2022-11-28';
	private const USER_AGENT = 'wp-github-plugin-updater';

	private ?string $token;

	public function __construct( ?string $token ) {
		$this->token = $token;
	}

	/**
	 * GET a GitHub API path (e.g. "/repos/owner/repo/releases/latest").
	 *
	 * @return array<string,mixed>|null Decoded JSON body, or null on failure/no-content.
	 */
	public function get( string $path ): ?array {
		$url   = self::API_BASE . $path;
		$cache = new TransientCache( ( $this->token ? 'auth:' : 'anon:' ) . $url );
		$prev  = $cache->get();

		$headers = array(
			'Accept'               => 'application/vnd.github+json',
			'X-GitHub-Api-Version' => self::API_VER,
			'User-Agent'           => self::USER_AGENT,
		);
		if ( null !== $this->token ) {
			$headers['Authorization'] = 'Bearer ' . $this->token;
		}
		if ( $prev && ! empty( $prev['etag'] ) ) {
			$headers['If-None-Match'] = $prev['etag'];
		}

		$response = $this->request( $url, $headers );
		if ( null === $response ) {
			// Network error: fall back to any cached payload we still hold.
			return $prev['payload'] ?? null;
		}

		// 304 Not Modified: reuse cache and refresh its lifetime.
		if ( 304 === $response['code'] && $prev ) {
			$cache->set( $prev['payload'], $prev['etag'] );
			return \is_array( $prev['payload'] ) ? $prev['payload'] : null;
		}

		if ( $response['code'] < 200 || $response['code'] >= 300 ) {
			// On rate-limit or error, prefer stale cache over nothing.
			return $prev['payload'] ?? null;
		}

		$payload = \json_decode( $response['body'], true );
		if ( ! \is_array( $payload ) ) {
			return null;
		}

		$cache->set( $payload, $response['etag'] );
		return $payload;
	}

	/**
	 * Execute the HTTP request via WP HTTP when available.
	 *
	 * @param array<string,string> $headers Request headers.
	 * @return array{code:int,body:string,etag:string|null}|null
	 */
	private function request( string $url, array $headers ): ?array {
		if ( function_exists( 'wp_remote_get' ) ) {
			$res = wp_remote_get(
				$url,
				array(
					'headers' => $headers,
					'timeout' => 15,
				)
			);
			if ( function_exists( 'is_wp_error' ) && is_wp_error( $res ) ) {
				return null;
			}
			return array(
				'code' => (int) wp_remote_retrieve_response_code( $res ),
				'body' => (string) wp_remote_retrieve_body( $res ),
				'etag' => wp_remote_retrieve_header( $res, 'etag' ) ?: null,
			);
		}

		return $this->request_curl( $url, $headers );
	}

	/**
	 * cURL fallback for non-WP contexts.
	 *
	 * @param array<string,string> $headers Request headers.
	 * @return array{code:int,body:string,etag:string|null}|null
	 */
	private function request_curl( string $url, array $headers ): ?array {
		if ( ! function_exists( 'curl_init' ) ) {
			return null;
		}
		$flat = array();
		foreach ( $headers as $k => $v ) {
			$flat[] = $k . ': ' . $v;
		}
		$etag = null;
		$ch   = \curl_init( $url );
		\curl_setopt_array(
			$ch,
			array(
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HTTPHEADER     => $flat,
				CURLOPT_TIMEOUT        => 15,
				CURLOPT_HEADERFUNCTION => function ( $curl, $header ) use ( &$etag ) {
					if ( 0 === \stripos( $header, 'etag:' ) ) {
						$etag = \trim( \substr( $header, 5 ) );
					}
					return \strlen( $header );
				},
			)
		);
		$body = \curl_exec( $ch );
		if ( false === $body ) {
			\curl_close( $ch );
			return null;
		}
		$code = (int) \curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
		\curl_close( $ch );
		return array(
			'code' => $code,
			'body' => (string) $body,
			'etag' => $etag,
		);
	}
}
