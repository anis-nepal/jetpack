<?php
/**
 * Story Block.
 *
 * @since 8.6.1
 *
 * @package Jetpack
 */

namespace Automattic\Jetpack\Extensions\Story;

use Jetpack_Gutenberg;

const FEATURE_NAME = 'story';
const BLOCK_NAME   = 'jetpack/' . FEATURE_NAME;

/**
 * Registers the block for use in Gutenberg
 * This is done via an action so that we can disable
 * registration if we need to.
 */
function register_block() {
	jetpack_register_block(
		BLOCK_NAME,
		array( 'render_callback' => __NAMESPACE__ . '\render_block' )
	);
}
add_action( 'init', __NAMESPACE__ . '\register_block' );

/**
 * Render an image inside a slide
 *
 * @param array $media  Image information.
 *
 * @return string
 */
function render_image( $media ) {
	if ( empty( $media['id'] ) || empty( $media['url'] ) ) {
		return __( 'Error retrieving media', 'jetpack' );
	}
	return sprintf(
		'<img
			alt="%1$s"
			class="wp-block-jetpack-story_image wp-story-image wp-image-%2$s"
			data-id="%2$s"
			src="%3$s">',
		esc_attr( $media['alt'] ),
		$media['id'],
		esc_attr( $media['url'] )
	);
}

/**
 * Render a video inside a slide
 *
 * @param array $media  Video information.
 *
 * @return string
 */
function render_video( $media ) {
	if ( empty( $media['id'] ) || empty( $media['mime'] ) || empty( $media['url'] ) ) {
		return __( 'Error retrieving media', 'jetpack' );
	}
	return sprintf(
		'<video
			title="%1$s"
			type="%2$s"
			class="wp-story-video intrinsic-ignore wp-video-%3$s"
			data-id="%3$s"
			src="%4$s">
		</video>',
		esc_attr( $media['alt'] ),
		esc_attr( $media['mime'] ),
		$media['id'],
		esc_attr( $media['url'] )
	);
}

/**
 * Render a slide
 *
 * @param array $media  Media information.
 * @param array $index  Index of the slide, first slide will be displayed by default, others hidden.
 *
 * @return string
 */
function render_slide( $media, $index ) {
	return sprintf(
		'<li class="wp-story-slide" style="display: %s;">
			<figure>
				%s
			</figure>
		</li>',
		0 === $index ? 'block' : 'none',
		'image' === $media['type']
			? render_image( $media, $index )
			: render_video( $media, $index )
	);
}

/**
 * Render story block
 *
 * @param array $attributes  Block attributes.
 *
 * @return string
 */
function render_block( $attributes ) {
	Jetpack_Gutenberg::load_assets_as_required( FEATURE_NAME );

	$settings    = array();
	$media_files = isset( $attributes['mediaFiles'] ) ? $attributes['mediaFiles'] : array();

	return sprintf(
		'<div class="%1$s" aria-labelledby="%2$s" data-settings="%3$s">
			<div style="display: contents;">
				<div class="wp-story-container">
					<div class="wp-story-meta">
						<div class="wp-story-icon">
							<img alt="%4$s" src="%5$s" width="32" height=32>
						</div>
						<div>
							<div class="wp-story-title">
								%6$s
							</div>
						</div>
						<button class="wp-story-exit-fullscreen jetpack-mdc-icon-button">
							<i class="jetpack-material-icons close md-24"></i>
						</button>
					</div>
					<ul class="wp-story-wrapper">
						%7$s
					</ul>
				</div>
			</div>
		</div>',
		esc_attr( Jetpack_Gutenberg::block_classes( FEATURE_NAME, $attributes, array( 'wp-story', 'aligncenter' ) ) ),
		esc_attr( 'wp-story-' . get_the_ID() ),
		filter_var( wp_json_encode( $settings ), FILTER_SANITIZE_SPECIAL_CHARS ),
		__( 'Site icon', 'jetpack' ),
		esc_attr( get_site_icon_url( 32, includes_url( 'images/w-logo-blue.png' ) ) ),
		esc_html( get_the_title() ),
		join( "\n", array_map( __NAMESPACE__ . '\render_slide', $media_files, array_keys( $media_files ) ) ),
		__( 'Exit Fullscreen', 'jetpack' )
	);
}
