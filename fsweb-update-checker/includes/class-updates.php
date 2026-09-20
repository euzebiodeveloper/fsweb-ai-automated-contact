<?php
/**
 * FSWEB Update Checker — Update Manager
 *
 * Responsible for version comparison, building update data objects,
 * and providing plugin information for the WordPress update system.
 *
 * @package   FSWEB_Update_Checker
 * @version   1.0.0
 * @link      https://flowsyncweb.com
 * @license   GPL-2.0+
 */

namespace FSWEB\UpdateChecker\AiAutomatedContact\V1;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Update Manager — builds update transient and plugins_api response objects.
 */
class Updates {

	/**
	 * Plugin basename (e.g. 'my-plugin/my-plugin.php').
	 *
	 * @var string
	 */
	private $plugin_basename;

	/**
	 * Plugin slug.
	 *
	 * @var string
	 */
	private $slug;

	/**
	 * Full path to the main plugin file.
	 *
	 * @var string
	 */
	private $plugin_file;

	/**
	 * Metadata repository.
	 *
	 * @var Metadata
	 */
	private $metadata;

	/**
	 * Constructor.
	 *
	 * @param string   $plugin_basename Plugin basename.
	 * @param string   $slug            Plugin slug.
	 * @param string   $plugin_file     Full path to main plugin file.
	 * @param Metadata $metadata        Metadata repository instance.
	 */
	public function __construct( $plugin_basename, $slug, $plugin_file, Metadata $metadata ) {
		$this->plugin_basename = $plugin_basename;
		$this->slug            = $slug;
		$this->plugin_file     = $plugin_file;
		$this->metadata        = $metadata;
	}

	/**
	 * Inject update data into the site_transient_update_plugins.
	 *
	 * @param \stdClass $transient The update_plugins transient value.
	 * @return \stdClass
	 */
	public function inject_into_transient( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		$meta = $this->metadata->get_cached();
		if ( ! $meta || empty( $meta->version ) ) {
			return $transient;
		}

		$current_version = $this->get_installed_version();
		if ( empty( $current_version ) ) {
			return $transient;
		}

		if ( version_compare( $meta->version, $current_version, '>' ) ) {
			$transient->response[ $this->plugin_basename ] = $this->build_update_object( $meta );
			if ( isset( $transient->no_update[ $this->plugin_basename ] ) ) {
				unset( $transient->no_update[ $this->plugin_basename ] );
			}
		} else {
			$transient->no_update[ $this->plugin_basename ] = $this->build_no_update_object( $meta );
			if ( isset( $transient->response[ $this->plugin_basename ] ) ) {
				unset( $transient->response[ $this->plugin_basename ] );
			}
		}

		return $transient;
	}

	/**
	 * Build the update object for the transient `response` array.
	 *
	 * @param object $meta Decoded JSON metadata.
	 * @return \stdClass
	 */
	public function build_update_object( $meta ) {
		$update                  = new \stdClass();
		$update->id              = $this->plugin_basename;
		$update->slug            = $this->slug;
		$update->plugin          = $this->plugin_basename;
		$update->new_version     = $meta->version;
		$update->url             = ! empty( $meta->homepage ) ? $meta->homepage : '';
		$update->package         = $this->build_package_url( $meta );
		$update->requires        = ! empty( $meta->requires ) ? $meta->requires : '';
		$update->tested          = ! empty( $meta->tested ) ? $meta->tested : '';
		$update->requires_php    = ! empty( $meta->requires_php ) ? $meta->requires_php : '';
		$update->compatibility   = new \stdClass();
		$update->icons           = ! empty( $meta->icons ) ? (array) $meta->icons : array();
		$update->banners         = ! empty( $meta->banners ) ? (array) $meta->banners : array();
		$update->banners_rtl     = ! empty( $meta->banners_rtl ) ? (array) $meta->banners_rtl : array();

		if ( ! empty( $meta->translations ) ) {
			$update->translations = (array) $meta->translations;
		}

		return $update;
	}

	/**
	 * Build the package download URL, appending the license key
	 * as a query param for API authentication.
	 *
	 * @since 1.1.0
	 * @param object $meta Decoded JSON metadata.
	 * @return string
	 */
	private function build_package_url( $meta ) {
		$url = ! empty( $meta->download_url ) ? $meta->download_url : '';

		if ( empty( $url ) ) {
			return '';
		}

		$license = $this->metadata->get_license();
		if ( empty( $license ) ) {
			return $url;
		}

		// Only append to our own API URLs — never leak credentials elsewhere.
		if ( false === strpos( $url, 'api.flowsyncweb.com' ) ) {
			return $url;
		}

		return add_query_arg(
			array(
				'license_key' => rawurlencode( $license ),
				'domain'      => rawurlencode( wp_parse_url( home_url(), PHP_URL_HOST ) ),
			),
			$url
		);
	}

	/**
	 * Build a "no_update" entry for the transient.
	 *
	 * @param object $meta Decoded JSON metadata.
	 * @return \stdClass
	 */
	public function build_no_update_object( $meta ) {
		$item                  = new \stdClass();
		$item->id              = $this->plugin_basename;
		$item->slug            = $this->slug;
		$item->plugin          = $this->plugin_basename;
		$item->new_version     = $meta->version;
		$item->url             = ! empty( $meta->homepage ) ? $meta->homepage : '';
		$item->package         = '';
		$item->requires        = ! empty( $meta->requires ) ? $meta->requires : '';
		$item->tested          = ! empty( $meta->tested ) ? $meta->tested : '';
		$item->requires_php    = ! empty( $meta->requires_php ) ? $meta->requires_php : '';
		$item->compatibility   = new \stdClass();
		$item->icons           = array();
		$item->banners         = array();
		$item->banners_rtl     = array();

		return $item;
	}

	/**
	 * Build the plugin_info object for the plugins_api response from LOCAL data.
	 *
	 * Uses the installed plugin's PHP headers, README.txt, and local assets
	 * (icons, banners, screenshots) — no API call needed.
	 * This ensures "View Details" always reflects the *installed* version,
	 * never whatever the remote server happens to have.
	 *
	 * @return \stdClass
	 */
	public function build_local_plugin_info() {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_data = get_plugin_data( $this->plugin_file, false, false );

		$info                 = new \stdClass();
		$info->name           = ! empty( $plugin_data['Name'] ) ? $plugin_data['Name'] : $this->slug;
		$info->slug           = $this->slug;
		$info->version        = ! empty( $plugin_data['Version'] ) ? $plugin_data['Version'] : '';
		$info->author         = ! empty( $plugin_data['Author'] ) ? $plugin_data['Author'] : '';
		$info->author_profile = ! empty( $plugin_data['AuthorURI'] ) ? $plugin_data['AuthorURI'] : '';
		$info->requires       = ! empty( $plugin_data['RequiresWP'] ) ? $plugin_data['RequiresWP'] : '';
		$info->tested         = ''; // Not a standard plugin header — omit for local.
		$info->requires_php   = ! empty( $plugin_data['RequiresPHP'] ) ? $plugin_data['RequiresPHP'] : '';
		$info->homepage       = ! empty( $plugin_data['PluginURI'] ) ? $plugin_data['PluginURI'] : '';
		$info->download_link  = ''; // Viewing installed version — no download here.
		$info->last_updated   = gmdate( 'Y-m-d H:i:s', filemtime( $this->plugin_file ) );

		// Icons / banners from local plugin assets (no API fallback needed).
		$info->icons   = $this->build_local_icons();
		$info->banners = $this->build_local_banners();

		// Sections from README.txt.
		$plugin_dir = plugin_dir_path( $this->plugin_file );
		$readme     = new ReadmeParser( $plugin_dir );
		$sections   = $readme->get_sections();

		// Re-key sections to all-lowercase for WordPress compatibility.
		$normalised = array();
		foreach ( $sections as $key => $html ) {
			$normalised[ strtolower( $key ) ] = $html;
		}

		// Override screenshots with local images (if available).
		$local_screenshots = $this->build_local_screenshots();
		if ( '' !== $local_screenshots ) {
			$normalised['screenshots'] = $local_screenshots;
		}

		$info->sections = $normalised;

		return $info;
	}

	/**
	 * Build the plugin_info object for the plugins_api response from REMOTE data.
	 *
	 * Uses remote README.txt (fetched from /api/releases/{slug}/readme) for
	 * sections, remote metadata for version/author/etc., and local assets
	 * for icons/banners/screenshots. Falls back gracefully when metadata
	 * is unavailable.
	 *
	 * @since 1.1.0
	 * @param string $readme_content Raw README.txt content from the API.
	 * @return \stdClass
	 */
	public function build_remote_plugin_info( $readme_content ) {
		$meta = $this->metadata->get_cached();

		$info                 = new \stdClass();
		$info->name           = ( $meta && ! empty( $meta->name ) ) ? $meta->name : $this->slug;
		$info->slug           = $this->slug;
		$info->version        = ( $meta && ! empty( $meta->version ) ) ? $meta->version : '';
		$info->author         = ( $meta && ! empty( $meta->author ) ) ? $meta->author : '';
		$info->author_profile = ( $meta && ! empty( $meta->author_profile ) ) ? $meta->author_profile : '';
		$info->requires       = ( $meta && ! empty( $meta->requires ) ) ? $meta->requires : '';
		$info->tested         = ( $meta && ! empty( $meta->tested ) ) ? $meta->tested : '';
		$info->requires_php   = ( $meta && ! empty( $meta->requires_php ) ) ? $meta->requires_php : '';
		$info->homepage       = ( $meta && ! empty( $meta->homepage ) ) ? $meta->homepage : '';
		$info->download_link  = ( $meta && ! empty( $meta->download_url ) ) ? $meta->download_url : '';
		$info->last_updated   = ( $meta && ! empty( $meta->last_updated ) ) ? $meta->last_updated : '';

		// Icons / banners from local plugin assets.
		$info->icons   = $this->build_local_icons();
		$info->banners = $this->build_local_banners();

		// Sections from remote README.txt (latest version from GitHub main).
		$sections = ReadmeParser::parse_from_string( $readme_content );

		// Normalise keys to lowercase for WordPress compatibility.
		$normalised = array();
		foreach ( $sections as $key => $html ) {
			$normalised[ strtolower( $key ) ] = $html;
		}

		// Override screenshots with local images (if available).
		$local_screenshots = $this->build_local_screenshots();
		if ( '' !== $local_screenshots ) {
			$normalised['screenshots'] = $local_screenshots;
		}

		$info->sections = $normalised;

		return $info;
	}

	/**
	 * Build the plugin_info object for the plugins_api response from REMOTE data.
	 *
	 * @deprecated 1.1.0 Use build_local_plugin_info() instead. Kept for backward
	 *             compatibility with any external code that might call it directly.
	 *
	 * @param object      $meta     Decoded JSON metadata.
	 * @param array|null  $sections Optional sections array.
	 * @param object|null $banners  Optional banners object.
	 * @param object|null $icons    Optional icons object.
	 * @return \stdClass
	 */
	public function build_plugin_info( $meta, $sections = null, $banners = null, $icons = null ) {
		$info                   = new \stdClass();
		$info->name             = ! empty( $meta->name ) ? $meta->name : $this->slug;
		$info->slug             = $this->slug;
		$info->version          = $meta->version;
		$info->author           = ! empty( $meta->author ) ? $meta->author : '';
		$info->author_profile   = ! empty( $meta->author_profile ) ? $meta->author_profile : '';
		$info->requires         = ! empty( $meta->requires ) ? $meta->requires : '';
		$info->tested           = ! empty( $meta->tested ) ? $meta->tested : '';
		$info->requires_php     = ! empty( $meta->requires_php ) ? $meta->requires_php : '';
		$info->homepage         = ! empty( $meta->homepage ) ? $meta->homepage : '';
		$info->download_link    = ! empty( $meta->download_url ) ? $meta->download_url : '';
		$info->last_updated     = ! empty( $meta->last_updated ) ? $meta->last_updated : '';

		if ( null !== $banners ) {
			$info->banners = (array) $banners;
		} elseif ( ! empty( $meta->banners ) ) {
			$info->banners = (array) $meta->banners;
		} else {
			$info->banners = array();
		}

		if ( null !== $icons ) {
			$info->icons = (array) $icons;
		} elseif ( ! empty( $meta->icons ) ) {
			$info->icons = (array) $meta->icons;
		} else {
			$info->icons = array();
		}

		$info->sections = ( null !== $sections && is_array( $sections ) )
			? $sections
			: array();

		return $info;
	}

	/**
	 * Check whether our slug matches the request.
	 *
	 * @param string $requested_slug The slug being requested.
	 * @return bool
	 */
	public function is_our_slug( $requested_slug ) {
		return $requested_slug === $this->slug
			|| $requested_slug === dirname( $this->plugin_basename );
	}

	/**
	 * Get the currently installed plugin version from plugin headers.
	 *
	 * @return string|null
	 */
	public function get_installed_version() {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_data = get_plugin_data( $this->plugin_file, false, false );

		return ! empty( $plugin_data['Version'] ) ? $plugin_data['Version'] : null;
	}

	/**
	 * Get the plugin display name from plugin headers.
	 *
	 * @return string
	 */
	public function get_plugin_name() {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$data = get_plugin_data( $this->plugin_file, false, false );

		return ! empty( $data['Name'] ) ? $data['Name'] : $this->slug;
	}

	/**
	 * Get the plugin basename.
	 *
	 * @return string
	 */
	public function get_plugin_basename() {
		return $this->plugin_basename;
	}

	/**
	 * Get the plugin slug.
	 *
	 * @return string
	 */
	public function get_slug() {
		return $this->slug;
	}

	/**
	 * Build screenshots HTML from local plugin images.
	 *
	 * Scans {plugin_dir}/includes/assets/images/ for .webp, .png, .jpg, .jpeg
	 * files and returns the WordPress-expected <ol> markup.
	 *
	 * @return string HTML or empty string if no images found.
	 */
	public function build_local_screenshots() {
		$plugin_dir = plugin_dir_path( $this->plugin_file );
		$images_dir = $plugin_dir . 'includes/assets/images/';

		if ( ! is_dir( $images_dir ) ) {
			return '';
		}

		$plugin_url = plugin_dir_url( $this->plugin_file );
		$images_url = $plugin_url . 'includes/assets/images/';

		$extensions = array( 'webp', 'png', 'jpg', 'jpeg' );
		$files      = array();

		foreach ( $extensions as $ext ) {
			$found = glob( $images_dir . 'screenshot-*.' . $ext );
			if ( is_array( $found ) ) {
				$files = array_merge( $files, $found );
			}
		}

		if ( empty( $files ) ) {
			return '';
		}

		sort( $files );

		$html = '<ol>';
		foreach ( $files as $file ) {
			$filename = basename( $file );
			$alt      = pathinfo( $filename, PATHINFO_FILENAME );
			$url      = $images_url . $filename;

			$html .= sprintf(
				'<li><a href="%1$s" target="_blank"><img src="%1$s" alt="%2$s"></a></li>',
				esc_url( $url ),
				esc_attr( $alt )
			);
		}
		$html .= '</ol>';

		return $html;
	}

	/**
	 * Build icons object from local plugin images.
	 *
	 * Scans for icon-128x128.*, icon-256x256.*, and icon.svg in:
	 *   1. {plugin_dir}/includes/assets/images/
	 *   2. {plugin_dir}/assets/
	 *   3. {plugin_dir}/ (root)
	 *
	 * Returns a WordPress-expected icons object with '1x', '2x', 'svg',
	 * and 'default' keys.
	 *
	 * @return array Associative array of icon URLs (empty array if none found).
	 */
	public function build_local_icons() {
		$plugin_dir  = plugin_dir_path( $this->plugin_file );
		$plugin_url  = plugin_dir_url( $this->plugin_file );
		$extensions  = array( 'webp', 'png', 'jpg', 'jpeg', 'svg' );

		$search_dirs = array(
			'includes/assets/images/',
			'assets/',
			'',
		);

		$icons = array();

		foreach ( $search_dirs as $subdir ) {
			$dir_path = $plugin_dir . $subdir;
			if ( '' !== $subdir && ! is_dir( $dir_path ) ) {
				continue;
			}

			$dir_url = $plugin_url . $subdir;

			// 1x (128x128)
			if ( ! isset( $icons['1x'] ) ) {
				foreach ( $extensions as $ext ) {
					$file = 'icon-128x128.' . $ext;
					if ( file_exists( $dir_path . $file ) ) {
						$icons['1x'] = $dir_url . $file;
						break;
					}
				}
			}

			// 2x (256x256)
			if ( ! isset( $icons['2x'] ) ) {
				foreach ( $extensions as $ext ) {
					if ( 'svg' === $ext ) {
						continue;
					}
					$file = 'icon-256x256.' . $ext;
					if ( file_exists( $dir_path . $file ) ) {
						$icons['2x'] = $dir_url . $file;
						break;
					}
				}
			}

			// SVG
			if ( ! isset( $icons['svg'] ) && file_exists( $dir_path . 'icon.svg' ) ) {
				$icons['svg'] = $dir_url . 'icon.svg';
			}
		}

		// Default falls back to 1x, then whatever we have.
		if ( ! empty( $icons ) ) {
			$icons['default'] = isset( $icons['1x'] ) ? $icons['1x'] : reset( $icons );
		}

		return $icons;
	}

	/**
	 * Build banners object from local plugin images.
	 *
	 * Scans for banner-772x250.* and banner-1544x500.* in:
	 *   1. {plugin_dir}/includes/assets/images/
	 *   2. {plugin_dir}/assets/
	 *   3. {plugin_dir}/ (root)
	 *
	 * Returns a WordPress-expected banners object with 'low' and 'high' keys.
	 *
	 * @return array Associative array of banner URLs (empty array if none found).
	 */
	public function build_local_banners() {
		$plugin_dir  = plugin_dir_path( $this->plugin_file );
		$plugin_url  = plugin_dir_url( $this->plugin_file );
		$extensions  = array( 'webp', 'png', 'jpg', 'jpeg' );

		$search_dirs = array(
			'includes/assets/images/',
			'assets/',
			'',
		);

		$banners = array();

		foreach ( $search_dirs as $subdir ) {
			$dir_path = $plugin_dir . $subdir;
			if ( '' !== $subdir && ! is_dir( $dir_path ) ) {
				continue;
			}

			$dir_url = $plugin_url . $subdir;

			// low (772x250)
			if ( ! isset( $banners['low'] ) ) {
				foreach ( $extensions as $ext ) {
					$file = 'banner-772x250.' . $ext;
					if ( file_exists( $dir_path . $file ) ) {
						$banners['low'] = $dir_url . $file;
						break;
					}
				}
			}

			// high (1544x500 = retina)
			if ( ! isset( $banners['high'] ) ) {
				foreach ( $extensions as $ext ) {
					$file = 'banner-1544x500.' . $ext;
					if ( file_exists( $dir_path . $file ) ) {
						$banners['high'] = $dir_url . $file;
						break;
					}
				}
			}
		}

		return $banners;
	}
}
