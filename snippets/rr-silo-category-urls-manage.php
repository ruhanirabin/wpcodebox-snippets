<?php
/**
 * Plugin Name: RR Silo Category URLs
 * Description: Selectively adds /{category}/{postname}/ URLs for posts in configured categories + Settings UI with category checklist.
 * Version: 1.2.0
 */

defined( 'ABSPATH' ) || exit;

final class RR_Silo_Category_URLs {

	private const OPTION_KEY = 'rr_silo_category_slugs';
	private const MENU_SLUG  = 'rr-silo-category-urls';

	public static function init(): void {
		add_filter( 'post_link', [ __CLASS__, 'filter_post_link' ], 10, 3 );
		add_action( 'init', [ __CLASS__, 'add_rewrite_rules' ] );
		add_action( 'template_redirect', [ __CLASS__, 'maybe_redirect_old_to_new' ] );

		add_action( 'admin_menu', [ __CLASS__, 'register_settings_page' ] );
		add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );

		add_action( 'init', [ __CLASS__, 'maybe_flush_rewrites_once' ], 20 );
	}

	/**
	 * @return string[]
	 */
	private static function get_silo_slugs(): array {
		$slugs = get_option( self::OPTION_KEY, [] );

		if ( ! is_array( $slugs ) ) {
			return [];
		}

		$slugs = array_map( static fn( $s ) => sanitize_title( (string) $s ), $slugs );
		$slugs = array_values( array_unique( array_filter( $slugs ) ) );

		return $slugs;
	}

	public static function filter_post_link( string $permalink, \WP_Post $post, bool $leavename ): string {
		if ( 'post' !== $post->post_type || 'publish' !== $post->post_status ) {
			return $permalink;
		}

		$silo = self::get_post_silo_slug( $post->ID );
		if ( ! $silo ) {
			return $permalink; // Keep default /%postname%/
		}

		$slug = $leavename ? '%postname%' : $post->post_name;

		return home_url( user_trailingslashit( $silo . '/' . $slug ) );
	}

	public static function add_rewrite_rules(): void {
		$slugs = self::get_silo_slugs();
		if ( empty( $slugs ) ) {
			return;
		}

		$pattern = implode( '|', array_map( 'preg_quote', $slugs ) );

		add_rewrite_rule(
			'^(' . $pattern . ')/([^/]+)/?$',
			'index.php?name=$matches[2]&post_type=post',
			'top'
		);
	}

	public static function maybe_redirect_old_to_new(): void {
		if ( ! is_singular( 'post' ) ) {
			return;
		}

		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			return;
		}

		$silo = self::get_post_silo_slug( $post_id );
		if ( ! $silo ) {
			return;
		}

		$request_path = trim( (string) wp_parse_url( (string) $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );
		$parts        = $request_path === '' ? [] : explode( '/', $request_path );

		// Old URL format: /postname/
		if ( 1 === count( $parts ) ) {
			wp_safe_redirect( get_permalink( $post_id ), 301 );
			exit;
		}
	}

	private static function get_post_silo_slug( int $post_id ): string {
		$slugs = self::get_silo_slugs();
		if ( empty( $slugs ) ) {
			return '';
		}

		$terms = get_the_terms( $post_id, 'category' );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return '';
		}

		foreach ( $terms as $term ) {
			if ( in_array( $term->slug, $slugs, true ) ) {
				return $term->slug;
			}
		}

		return '';
	}

	/* -------------------------
	 * Settings UI (category checklist)
	 * -------------------------- */

	public static function register_settings_page(): void {
		add_options_page(
			__( 'RR Silo Category URLs', 'rr-silo-urls' ),
			__( 'RR Silo Category URLs', 'rr-silo-urls' ),
			'manage_options',
			self::MENU_SLUG,
			[ __CLASS__, 'render_settings_page' ]
		);
	}

	public static function register_settings(): void {

	if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
		return;
	}

		register_setting(
			'rr_silo_urls',
			self::OPTION_KEY,
			[
				'type'              => 'array',
				'sanitize_callback' => [ __CLASS__, 'sanitize_slugs_option' ],
				'default'           => [],
			]
		);

		add_settings_section(
			'rr_silo_urls_main',
			__( 'Select silo categories', 'rr-silo-urls' ),
			static function (): void {
				echo '<p>Checked categories will use <code>/{category}/{postname}/</code> for posts assigned to them. Other posts remain <code>/{postname}/</code>.</p>';
			},
			self::MENU_SLUG
		);

		add_settings_field(
			'rr_silo_urls_slugs',
			__( 'Categories', 'rr-silo-urls' ),
			[ __CLASS__, 'render_category_checklist_field' ],
			self::MENU_SLUG,
			'rr_silo_urls_main'
		);

		// Flush rewrites when option changes (covers slug pattern changes).
		add_action(
			'update_option_' . self::OPTION_KEY,
			static function (): void {
				flush_rewrite_rules( false );
			},
			10,
			0
		);
	}

public static function sanitize_slugs_option( mixed $value ): array {
	if ( ! current_user_can( 'manage_options' ) ) {
		return self::get_silo_slugs();
	}

	$allowed = get_categories(
		[
			'taxonomy'   => 'category',
			'hide_empty' => false,
			'fields'     => 'slugs',
		]
	);

	$allowed_map = [];
	foreach ( (array) $allowed as $slug ) {
		$slug = sanitize_title( (string) $slug );
		if ( '' !== $slug ) {
			$allowed_map[ $slug ] = true;
		}
	}

	$slugs = [];

	if ( is_array( $value ) ) {
		foreach ( $value as $item ) {
			$slug = sanitize_title( (string) $item );
			if ( '' !== $slug && isset( $allowed_map[ $slug ] ) ) {
				$slugs[] = $slug;
			}
		}
	}

	$slugs = array_values( array_unique( $slugs ) );

	update_option( 'rr_silo_urls_rewrite_flushed_v3', 0, true );

	return $slugs;
}


	public static function render_category_checklist_field(): void {
		$selected = self::get_silo_slugs();
		$selected = array_flip( $selected );

		// Ensure a value is sent even when nothing is checked.
		printf(
			'<input type="hidden" name="%s[]" value="" />',
			esc_attr( self::OPTION_KEY )
		);

		$cats = get_categories(
			[
				'taxonomy'   => 'category',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			]
		);

		if ( empty( $cats ) ) {
			echo '<p class="description">No categories found.</p>';
			return;
		}

		echo '<div style="max-width: 900px; columns: 2; column-gap: 32px;">';

		foreach ( $cats as $cat ) {
			$slug    = (string) $cat->slug;
			$checked = isset( $selected[ $slug ] ) ? 'checked' : '';

			printf(
				'<label style="display:block; break-inside:avoid; padding: 4px 0;">
					<input type="checkbox" name="%1$s[]" value="%2$s" %3$s />
					<strong>%4$s</strong> <span style="opacity:.75;">(%2$s)</span>
				</label>',
				esc_attr( self::OPTION_KEY ),
				esc_attr( $slug ),
				$checked,
				esc_html( $cat->name )
			);
		}

		echo '</div>';
		echo '<p class="description">Tip: for your 4 silos, only check those 4 categories. Keep posts assigned to exactly one silo category to avoid ambiguity.</p>';
	}

	public static function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo '<div class="wrap">';
		echo '<h1>RR Silo Category URLs</h1>';
		echo '<form method="post" action="options.php">';
		settings_fields( 'rr_silo_urls' );
		do_settings_sections( self::MENU_SLUG );
		submit_button();
		echo '</form>';
		echo '</div>';
	}

	/* -------------------------
	 * Rewrite flush helper
	 * -------------------------- */

	public static function maybe_flush_rewrites_once(): void {
		$key = 'rr_silo_urls_rewrite_flushed_v3';
		$val = (int) get_option( $key, 0 );

		if ( 1 === $val ) {
			return;
		}

		flush_rewrite_rules( false );
		update_option( $key, 1, true );
	}
}

RR_Silo_Category_URLs::init();
