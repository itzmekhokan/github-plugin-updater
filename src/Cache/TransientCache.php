<?php
/**
 * Thin transient wrapper storing an API payload alongside its ETag.
 *
 * @package Itzmekhokan\GitHubPluginUpdater
 */

declare( strict_types=1 );

namespace Itzmekhokan\GitHubPluginUpdater\Cache;

/**
 * Caches GitHub API responses to stay well under the 60 req/hr anonymous
 * rate limit. Stores both the decoded payload and the response ETag so the
 * client can revalidate cheaply with a conditional request (a 304 is free
 * and does not count against the rate limit).
 */
final class TransientCache {

	/**
	 * Default cache lifetime in seconds.
	 */
	public const DEFAULT_TTL = 12 * HOUR_IN_SECONDS;

	private string $key;

	public function __construct( string $namespace ) {
		$this->key = 'gpu_' . \md5( $namespace );
	}

	/**
	 * @return array{payload:mixed,etag:string|null}|null
	 */
	public function get(): ?array {
		if ( ! function_exists( 'get_transient' ) ) {
			return null;
		}
		$data = get_transient( $this->key );
		if ( ! \is_array( $data ) || ! \array_key_exists( 'payload', $data ) ) {
			return null;
		}
		return array(
			'payload' => $data['payload'],
			'etag'    => $data['etag'] ?? null,
		);
	}

	/**
	 * @param mixed       $payload Decoded API payload.
	 * @param string|null $etag    Response ETag, if any.
	 * @param int|null    $ttl     Lifetime override in seconds.
	 */
	public function set( $payload, ?string $etag, ?int $ttl = null ): void {
		if ( ! function_exists( 'set_transient' ) ) {
			return;
		}
		set_transient(
			$this->key,
			array(
				'payload' => $payload,
				'etag'    => $etag,
			),
			$ttl ?? self::DEFAULT_TTL
		);
	}

	public function delete(): void {
		if ( function_exists( 'delete_transient' ) ) {
			delete_transient( $this->key );
		}
	}
}
