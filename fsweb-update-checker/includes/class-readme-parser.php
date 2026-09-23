<?php
/**
 * FSWEB Update Checker — Readme Parser
 *
 * Parses a WordPress-style README.txt file into sections suitable for
 * the plugins_api() response. The "View Details" popup always reflects
 * the *installed* version, never the remote latest.
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
 * Readme Parser — converts README.txt into section_name => HTML pairs.
 */
class ReadmeParser {

	/**
	 * Full path to the README.txt file.
	 *
	 * @var string
	 */
	private $file_path;

	/**
	 * Cached parsed sections.
	 *
	 * @var array|null
	 */
	private $sections = null;

	/**
	 * Short description (the text before the first == section ==).
	 *
	 * @var string
	 */
	private $short_description = '';

	/**
	 * Constructor.
	 *
	 * @param string $plugin_dir Absolute path to the plugin directory.
	 */
	public function __construct( $plugin_dir ) {
		$this->file_path = $this->resolve_file_path( $plugin_dir );
	}

	/**
	 * Resolve the readme file path.
	 *
	 * WordPress ships the readme as `readme.txt` (lowercase), while some
	 * FSWEB plugins use the uppercase variant. Looking for the lowercase
	 * name first keeps the WordPress convention working and still reads the
	 * uppercase file when that is the one present — otherwise the "View
	 * details" popup came up without any section.
	 *
	 * @since  1.0.0
	 * @param  string $plugin_dir Absolute path to the plugin directory.
	 * @return string
	 */
	private function resolve_file_path( $plugin_dir ) {
		$dir = trailingslashit( $plugin_dir );

		foreach ( array( 'readme.txt', 'README.txt', 'Readme.txt' ) as $candidate ) {
			if ( file_exists( $dir . $candidate ) ) {
				return $dir . $candidate;
			}
		}

		return $dir . 'readme.txt';
	}

	/**
	 * Check whether the README.txt file exists.
	 *
	 * @return bool
	 */
	public function exists() {
		return file_exists( $this->file_path );
	}

	/**
	 * Parse the README and return sections as name => HTML pairs.
	 *
	 * Sections are the `== Section Name ==` blocks found in the file.
	 * Sub-sections (`= Subsection =`) are kept inside their parent.
	 *
	 * @return array Associative array of section_name => HTML content.
	 */
	public function get_sections() {
		if ( null !== $this->sections ) {
			return $this->sections;
		}

		if ( ! $this->exists() ) {
			$this->sections = array();
			return $this->sections;
		}

		$raw = file_get_contents( $this->file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $raw ) {
			$this->sections = array();
			return $this->sections;
		}

		$this->sections = $this->parse( $raw );
		return $this->sections;
	}

	/**
	 * Get the short description (text before the first == section ==).
	 *
	 * @return string HTML.
	 */
	public function get_short_description() {
		if ( null === $this->sections ) {
			$this->get_sections();
		}
		return $this->short_description;
	}

	/**
	 * Get a single section by name (case-insensitive, trimmed).
	 *
	 * @param string $name Section name (e.g. 'Description', 'Changelog').
	 * @return string HTML content, or '' if not found.
	 */
	public function get_section( $name ) {
		$sections = $this->get_sections();
		$key      = $this->normalise_key( $name );
		return isset( $sections[ $key ] ) ? $sections[ $key ] : '';
	}

	/**
	 * Parse raw README content from a string (no file needed).
	 *
	 * Useful for parsing README.txt content fetched from a remote API
	 * (e.g. GitHub main branch) without needing a local file on disk.
	 *
	 * @since 1.1.0
	 * @param string $content Raw README.txt content.
	 * @return array Associative array of section_name => HTML content.
	 */
	public static function parse_from_string( $content ) {
		$instance = new self( '' );
		$instance->file_path     = '';
		$instance->sections      = null;
		$instance->short_description = '';

		if ( empty( $content ) ) {
			$instance->sections = array();
			return $instance->sections;
		}

		$instance->sections = $instance->parse( $content );
		return $instance->sections;
	}

	// ------------------------------------------------------------------//
	//  Internal parsing
	// ------------------------------------------------------------------//

	/**
	 * Split raw README content into sections.
	 *
	 * @param string $content Raw file content.
	 * @return array
	 */
	private function parse( $content ) {
		$content = $this->strip_bom( $content );
		$lines   = explode( "\n", $content );
		$lines   = $this->strip_inline_comments( $lines );

		$sections = array();
		$current  = '_short_description'; // pseudo-section for the intro text.
		$buffer   = '';

		foreach ( $lines as $line ) {
			// Skip `=== Header ===` lines (plugin name header, not a section).
			if ( preg_match( '/^===\s*.+\s*===\s*$/', $line ) ) {
				continue;
			}

			// Detect `== Section Name ==` (requires space before/after name
			// to avoid matching `=== Header ===` style lines).
			if ( preg_match( '/^==\s+(.+?)\s+==\s*$/', $line, $m ) ) {
				// Flush the previous section buffer.
				if ( '' !== trim( $buffer ) || '_short_description' !== $current ) {
					$sections[ $current ] = $this->render_markdown( trim( $buffer ) );
				}
				$current = $this->normalise_key( $m[1] );
				$buffer  = '';
				continue;
			}

			$buffer .= $line . "\n";
		}

		// Flush the last section.
		if ( '' !== trim( $buffer ) || '_short_description' !== $current ) {
			$sections[ $current ] = $this->render_markdown( trim( $buffer ) );
		}

		// Separate the short description from real sections.
		if ( isset( $sections['_short_description'] ) ) {
			$this->short_description = $this->render_markdown( trim( $sections['_short_description'] ) );
			unset( $sections['_short_description'] );
		}

		// Filter out sections that shouldn't appear as tabs.
		$sections = $this->filter_sections( $sections );

		return $sections;
	}

	/**
	 * Remove sections that should not appear in the "View Details" popup.
	 *
	 * Follows WordPress.org convention: "Upgrade Notice" is only used
	 * in the updates page, not in the plugin info modal. Boilerplate
	 * sample sections from the WordPress plugin README template are
	 * also stripped.
	 *
	 * @param array $sections Parsed sections.
	 * @return array
	 */
	private function filter_sections( $sections ) {
		$skip = array(
			'upgrade-notice',
			'arbitrary-section',
			'a-brief-markdown-example',
		);

		foreach ( $skip as $key ) {
			unset( $sections[ $key ] );
		}

		return $sections;
	}

	/**
	 * Remove inline comments (lines starting with `#` after trimming).
	 *
	 * @param array $lines Raw lines.
	 * @return array
	 */
	private function strip_inline_comments( $lines ) {
		return array_filter( $lines, function ( $line ) {
			return ! preg_match( '/^\s*#/', $line );
		} );
	}

	/**
	 * Strip UTF-8 BOM if present.
	 *
	 * @param string $content Raw content.
	 * @return string
	 */
	private function strip_bom( $content ) {
		if ( "\xEF\xBB\xBF" === substr( $content, 0, 3 ) ) {
			return substr( $content, 3 );
		}
		return $content;
	}

	/**
	 * Normalise a section name to a slug-safe, jQuery-safe key.
	 *
	 * Lowercases and replaces whitespace with hyphens so the key works
	 * as an HTML id attribute and jQuery selector (spaces would be
	 * interpreted as descendant selectors).
	 *
	 * @param string $name Raw section name.
	 * @return string
	 */
	private function normalise_key( $name ) {
		return str_replace( ' ', '-', strtolower( trim( $name ) ) );
	}

	/**
	 * Convert basic Markdown to HTML.
	 *
	 * Handles: headings, bold, italic, links, code, lists, blockquotes,
	 * and horizontal rules. This is deliberately simple — WordPress-style
	 * README files use very limited Markdown.
	 *
	 * @param string $text Markdown text.
	 * @return string HTML.
	 */
	private function render_markdown( $text ) {
		// Phase 1: inline code (backticks) — must run before other formatting.
		$text = preg_replace( '/`([^`]+)`/', '<code>$1</code>', $text );

		// Phase 2: bold (**text**) and italic (*text*).
		$text = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text );
		$text = preg_replace( '/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/', '<em>$1</em>', $text );

		// Phase 3: inline links [text](url).
		$text = preg_replace(
			'/\[([^\]]+)\]\(([^)]+)\)/',
			'<a href="$2" target="_blank" rel="noopener">$1</a>',
			$text
		);

		// Phase 4: headings — `= heading =` or `== heading ==` etc.
		// Within a section body these become <h3>/<h4> respectively.
		$text = preg_replace( '/^====\s*(.+?)\s*====\s*$/m', '<h4>$1</h4>', $text );
		$text = preg_replace( '/^===\s*(.+?)\s*===\s*$/m', '<h3>$1</h3>', $text );
		$text = preg_replace( '/^==\s*(.+?)\s*==\s*$/m', '<h3>$1</h3>', $text );
		$text = preg_replace( '/^=\s*(.+?)\s*=\s*$/m', '<h4>$1</h4>', $text );

		// Phase 5: blockquotes — lines starting with >.
		$text = preg_replace( '/^&gt;\s*(.+)$/m', '<blockquote><p>$1</p></blockquote>', $text );

		// Phase 6: horizontal rules.
		$text = preg_replace( '/^-{3,}\s*$/m', '<hr>', $text );

		// Phase 7: unordered lists.
		$text = $this->process_lists( $text, 'ul', '/^[*+-]\s+(.+)$/m' );

		// Phase 8: ordered lists.
		$text = $this->process_lists( $text, 'ol', '/^\d+\.\s+(.+)$/m' );

		// Phase 9: wrap remaining paragraphs (skip HTML blocks and empty lines).
		$text = $this->wrap_paragraphs( $text );

		return $text;
	}

	/**
	 * Wrap list items in <ul> or <ol> blocks, preserving original line order.
	 *
	 * Consecutive list-item lines within a block are grouped into a single
	 * <ul>/<ol> element. Non-list lines (headings, paragraphs) keep their
	 * position relative to the list.
	 *
	 * @param string $text   Markdown text.
	 * @param string $tag    'ul' or 'ol'.
	 * @param string $regex  Regex to match list items.
	 * @return string HTML.
	 */
	private function process_lists( $text, $tag, $regex ) {
		$blocks = preg_split( '/\n\s*\n/', $text );
		$result = array();

		foreach ( $blocks as $block ) {
			$lines  = explode( "\n", $block );
			$output = array();  // ordered output for this block.
			$items  = array();  // current run of consecutive list items.
			$i      = 0;
			$count  = count( $lines );

			while ( $i < $count ) {
				$line = $lines[ $i ];

				if ( preg_match( $regex, $line, $m ) ) {
					// Start or continue a list run.
					$items[] = '<li>' . trim( $m[1] ) . '</li>';

					// Look ahead for continuation lines (text not matching
					// the regex but not blank and not a different structure).
					while ( $i + 1 < $count ) {
						$next = $lines[ $i + 1 ];
						if ( '' === trim( $next ) ) {
							break; // blank line ends the list run.
						}
						if ( preg_match( $regex, $next ) ) {
							break; // next item — handled in next iteration.
						}
						if ( preg_match( '/^[=<]/', ltrim( $next ) ) ) {
							break; // heading or other structure.
						}
						// Continuation line — append to last item.
						$idx              = count( $items ) - 1;
						$items[ $idx ]    = rtrim( $items[ $idx ], '</li>' ) . ' ' . trim( $next ) . '</li>';
						$i++;
					}
				} else {
					// Non-list line — flush any accumulated list first.
					if ( ! empty( $items ) ) {
						$output[] = '<' . $tag . '>' . implode( '', $items ) . '</' . $tag . '>';
						$items    = array();
					}
					if ( '' !== trim( $line ) ) {
						$output[] = $line;
					}
				}

				$i++;
			}

			// Flush any remaining list items at end of block.
			if ( ! empty( $items ) ) {
				$output[] = '<' . $tag . '>' . implode( '', $items ) . '</' . $tag . '>';
			}

			if ( ! empty( $output ) ) {
				$result[] = implode( "\n", $output );
			}
		}

		return implode( "\n\n", $result );
	}

	/**
	 * Wrap bare text lines in <p> tags, preserving existing HTML blocks.
	 *
	 * Works line-by-line: HTML block elements (headings, lists, etc.) pass
	 * through unchanged; consecutive text lines are grouped into <p> blocks;
	 * blank lines act as paragraph separators.
	 *
	 * @param string $text Text with potential bare lines.
	 * @return string HTML.
	 */
	private function wrap_paragraphs( $text ) {
		$lines  = explode( "\n", $text );
		$result = array();
		$buffer = '';

		foreach ( $lines as $line ) {
			$trimmed = trim( $line );

			// Blank line — flush buffered text as a paragraph.
			if ( '' === $trimmed ) {
				if ( '' !== $buffer ) {
					$result[] = '<p>' . trim( $buffer ) . '</p>';
					$buffer   = '';
				}
				continue;
			}

			// Self-contained HTML block element — output as-is.
			if ( preg_match( '/^<(\w+)[^>]*>.*<\/\1>\s*$/', $trimmed ) ) {
				if ( '' !== $buffer ) {
					$result[] = '<p>' . trim( $buffer ) . '</p>';
					$buffer   = '';
				}
				$result[] = $trimmed;
				continue;
			}

			// Self-closing tags (<hr>, <br>).
			if ( preg_match( '/^<(hr|br)[^>]*\/?>\s*$/', $trimmed ) ) {
				if ( '' !== $buffer ) {
					$result[] = '<p>' . trim( $buffer ) . '</p>';
					$buffer   = '';
				}
				$result[] = $trimmed;
				continue;
			}

			// Plain text — accumulate into buffer.
			$buffer .= ( '' !== $buffer ? ' ' : '' ) . $trimmed;
		}

		// Flush any remaining buffered text.
		if ( '' !== $buffer ) {
			$result[] = '<p>' . trim( $buffer ) . '</p>';
		}

		return implode( "\n", $result );
	}
}
