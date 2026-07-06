<?php
/**
 * Unit tests for version normalization — pure logic, no WP required.
 *
 * @package Itzmekhokan\GitHubPluginUpdater
 */

declare( strict_types=1 );

namespace Itzmekhokan\GitHubPluginUpdater\Tests;

use Itzmekhokan\GitHubPluginUpdater\GitHub\ReleaseResolver;
use PHPUnit\Framework\TestCase;

final class ReleaseResolverVersionTest extends TestCase {

	/**
	 * @dataProvider tags
	 */
	public function test_normalize_version( string $tag, string $expected ): void {
		$this->assertSame( $expected, ReleaseResolver::normalize_version( $tag ) );
	}

	/**
	 * @return array<int,array{0:string,1:string}>
	 */
	public function tags(): array {
		return array(
			array( 'v1.2.3', '1.2.3' ),
			array( 'V2.0.0', '2.0.0' ),
			array( '1.0.0', '1.0.0' ),
			array( ' v3.4.5 ', '3.4.5' ),
		);
	}

	public function test_config_parses_owner_and_repo(): void {
		$config = new \Itzmekhokan\GitHubPluginUpdater\Config\PluginConfig(
			array(
				'basename'   => 'awesome-plugin/awesome-plugin.php',
				'slug'       => 'awesome-plugin',
				'repository' => 'acme/awesome-plugin',
			)
		);
		$this->assertTrue( $config->is_valid() );
		$this->assertSame( 'acme', $config->owner() );
		$this->assertSame( 'awesome-plugin', $config->repo_name() );
	}
}
