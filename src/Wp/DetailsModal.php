<?php
/**
 * Serves the "View details" modal without a wp.org lookup.
 *
 * @package Itzmekhokan\GitHubPluginUpdater
 */

declare( strict_types=1 );

namespace Itzmekhokan\GitHubPluginUpdater\Wp;

use Itzmekhokan\GitHubPluginUpdater\Config\PluginConfig;
use Itzmekhokan\GitHubPluginUpdater\GitHub\RemoteInfo;

/**
 * Populates the plugin-information modal shown when a user clicks "View
 * version details", using GitHub release notes as the changelog.
 */
final class DetailsModal {

	private PluginConfig $config;

	/**
	 * @var callable():?RemoteInfo
	 */
	private $resolver;

	/**
	 * @param PluginConfig           $config   Plugin config.
	 * @param callable():?RemoteInfo $resolver Returns the remote info.
	 */
	public function __construct( PluginConfig $config, callable $resolver ) {
		$this->config   = $config;
		$this->resolver = $resolver;
	}

	/**
	 * Hook: plugins_api.
	 *
	 * @param false|object|array $result The result object or false.
	 * @param string             $action The requested action.
	 * @param object             $args   Request args (has ->slug).
	 * @return false|object|array
	 */
	public function provide( $result, $action = '', $args = null ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}
		if ( ! isset( $args->slug ) || $args->slug !== $this->config->slug() ) {
			return $result;
		}

		$remote = ( $this->resolver )();
		if ( ! $remote instanceof RemoteInfo ) {
			return $result;
		}

		$changelog = $remote->changelog();
		$sections  = array(
			'description' => \sprintf(
				/* translators: %s: repository slug */
				'Updates for this plugin are delivered from the GitHub repository <code>%s</code>.',
				\esc_html( $this->config->repository() )
			),
		);
		if ( '' !== $changelog ) {
			$sections['changelog'] = $this->render_changelog( $changelog );
		}

		return (object) array(
			'name'          => $this->config->slug(),
			'slug'          => $this->config->slug(),
			'version'       => $remote->version(),
			'author'        => '<a href="https://github.com/' . \esc_attr( $this->config->owner() ) . '">' . \esc_html( $this->config->owner() ) . '</a>',
			'homepage'      => 'https://github.com/' . $this->config->repository(),
			'download_link' => $remote->download_url(),
			'last_updated'  => $remote->published_at(),
			'sections'      => $sections,
		);
	}

	/**
	 * Render release-note markdown to a safe HTML subset. Full Markdown
	 * rendering is intentionally deferred; this covers the common cases.
	 */
	private function render_changelog( string $markdown ): string {
		$text  = \wp_kses_post( $markdown );
		$lines = \preg_split( '/\r\n|\r|\n/', $text );
		$html  = '';
		$in_ul = false;

		foreach ( (array) $lines as $line ) {
			$trimmed = \trim( $line );
			if ( '' === $trimmed ) {
				continue;
			}
			if ( \preg_match( '/^[-*]\s+(.*)$/', $trimmed, $m ) ) {
				if ( ! $in_ul ) {
					$html .= '<ul>';
					$in_ul = true;
				}
				$html .= '<li>' . $m[1] . '</li>';
				continue;
			}
			if ( $in_ul ) {
				$html .= '</ul>';
				$in_ul = false;
			}
			if ( \preg_match( '/^#{1,6}\s+(.*)$/', $trimmed, $m ) ) {
				$html .= '<h4>' . $m[1] . '</h4>';
			} else {
				$html .= '<p>' . $trimmed . '</p>';
			}
		}
		if ( $in_ul ) {
			$html .= '</ul>';
		}
		return $html;
	}
}
