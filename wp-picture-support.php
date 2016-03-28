<?php
/**
 * @link              https://twitter.com/ptbello
 * @since             1.0.0
 * @package           Wp_Picture_Support
 *
 * @wordpress-plugin
 * Plugin Name:       WP &lt;picture&gt; support
 * Plugin URI:        https://github.com/ptbello/wp-picture-support
 * Description:       Add support for &lt;picture&gt; thus enabling Art Direction on images
 * Version:           0.1.0
 * Author:            Piero Bellomo
 * Author URI:        https://twitter.com/ptbello
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wp-picture-support
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * 1. Include a polyfill (pick one of the following or any other of your choosing)
 *    Important for older browser and for SEO (since googlebot doesn't understand picture but does parse js)
 */
function picture_scripts() {
	wp_enqueue_script( 'respimages', '//cdn.jsdelivr.net/respimage/1.4.2/respimage.min.js');
	// wp_enqueue_script( 'picturefill', 'https://cdn.jsdelivr.net/picturefill/3.0.2/picturefill.min.js');
}
add_action( 'wp_enqueue_scripts', 'picture_scripts' );

/**
 * 2. Register additional image sizes
 *    If you are adding image sizes to an existing project, check https://wordpress.org/plugins/regenerate-thumbnails/
 */
add_action( 'init', 'register_picture_sizes' );
function register_picture_sizes()
{
	add_image_size( '16-9_1600', 1600, 900, true );
	add_image_size(  '16-9_768',  768, 432, true );
	add_image_size(   '1-1_768',  768, 768, true );
	add_image_size(  '4-3_1200', 1200, 900, true );
	add_image_size(   '3-4_300',  300, 400, true );
}

/**
 * 3. Register your picture formats and save them as an option
 */
add_action( 'init', 'register_picture_formats' );
function register_picture_formats()
{
	$picture_formats['product-header'] = array(
		'(orientation: portrait) and (max-width: 450px)'  => array( '3-4_300' ),
		'(orientation: landscape) and (max-width: 768px)' => array( '16-9_768' ),
		'(max-width: 768px)'                              => array( '1-1_768' ),
		'(max-width: 1279px)'                             => array( '4-3_1200' ),
		'(min-width: 1280px)'                             => array( '16-9_1600' ),
	);
	//$picture_formats['some-other-format'] = array(
	//
	//);
	update_option('picture_formats', $picture_formats, true);
}

/**
 * 4. Generate the picture markup according to format
 *    e.g. echo wp_get_attachment_picture(123, 'product-header');
 */
if ( ! function_exists( 'wp_get_attachment_picture' ) ) {

	/**
	 * Get an HTML picture element following one of the previously registered formats
	 *
	 * While $size will accept an array, it is better to register a size with
	 * add_image_size() so that a cropped version is generated. It's much more
	 * efficient than having to find the closest-sized image and then having the
	 * browser scale down the image.
	 *
	 * @see add_image_size()
	 *
	 * @param int                   $attachment_id Image attachment ID.
	 * @param string $format        one of the previously registered formats.
	 * @param mixed  $args          Optional, misc. arguments (default image, alt text, lazy loading)
	 * @param mixed  $attr          Optional, attributes for the picture markup and default image.
	 *
	 * @return string HTML img element or empty string on failure.
	 */
	function wp_get_attachment_picture( $attachment_id, $format, $args = '', $attr = '' ) {

		if( $attachment   = get_post( $attachment_id ) ) {
			$default_args = array(
				'alt'   => trim( strip_tags( get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ) ),
				// Use Alt field first
				'lazy'  => false,
				'default'  => 'thumbnail',
			);
			if ( empty( $default_args['alt'] ) ) {
				$default_args['alt'] = trim( strip_tags( $attachment->post_excerpt ) );
			} // If not, Use the Caption
			if ( empty( $default_args['alt'] ) ) {
				$default_args['alt'] = trim( strip_tags( $attachment->post_title ) );
			} // Finally, use the title
			$args = wp_parse_args( $args, $default_args );
		} else {
			return "<!-- wp_get_attachment_picture: attachment $attachment_id not found  -->";
		}

		if( $picture_formats = get_option('picture_formats') ) {
			if( array_key_exists($format, $picture_formats) ) {
				$sources = array();
				foreach( (array) $picture_formats[$format] as $query => $set ) {
					$scrset  = array();
					foreach ( $set as $descriptor ) {
						$tmp = explode( ' ', $descriptor );
						$size = $tmp[0];
						$density = isset($tmp[1]) ? $tmp[1] : null;
						$src_data = wp_get_attachment_image_src( $attachment_id, $size );
						$src      = $src_data[0];
						if ( $density ) {
							$src .= ' ' . $density;
						}
						$scrset[] = $src;
					}
					$sources[ $query ] = $scrset;
				}

				if( in_array($args['default'], get_intermediate_image_sizes()) ) {
					$src_data = wp_get_attachment_image_src( $attachment_id, $args['default'] );
					$args['default'] = $src_data[0];
				}   // else, just keep: this must have been a direct image URL

				if( is_array( $attr ) && count( $attr ) ) {
					$attrarr = $attr;
					$attr = '';
					foreach ( $attrarr as $name => $value ) $attr.= " $name=" . '"' . $value . '"';
				}
				$srckey = $args['lazy'] ? 'data-srcset' : 'srcset';
				ob_start(); ?>
					<picture<?php echo $attr; ?>>
						<!--[if IE 9]><audio><![endif]-->
						<?php foreach( $sources as $query => $srcset) : $srcset = implode(', ', $srcset); ?>
							<source
							<?= $srckey ?>="<?= $srcset ?>"
							media="<?= $query ?>" />
						<?php endforeach; ?>
						<!--[if IE 9]></audio><![endif]-->
						<img alt="<?= $args['alt'] ?>" src="<?= $args['default'] ?>" />
					</picture>
				<?php return ob_get_clean();
			} else {
				return "<!-- wp_get_attachment_picture: format $format is not registered in \$picture_formats -->";
			}
		} else {
			return "<!-- no format defined: \$picture_formats is empty -->";
		}
	}
}

