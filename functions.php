<?php
/**
 * Kadence Child theme functions and definitions.
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package kadence_child
 */

require_once get_stylesheet_directory() . '/inc/admin.php';

$kadence_child_autoload = get_stylesheet_directory() . '/vendor/autoload.php';
if ( file_exists( $kadence_child_autoload ) ) {
	require_once $kadence_child_autoload;
	$kadence_child_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/procyon-creative/kadence-child/',
		__FILE__,
		'kadence-child'
	);
	$kadence_child_checker->getVcsApi()->enableReleaseAssets( '/kadence-child.*\.zip$/i' );
	$kadence_child_token = kadence_child_get_github_token();
	if ( '' !== $kadence_child_token ) {
		$kadence_child_checker->setAuthentication( $kadence_child_token );
	}
	unset( $kadence_child_token );
}
unset( $kadence_child_autoload );

add_action( 'wp_enqueue_scripts', 'kadence_child_parent_theme_enqueue_styles', 20 );

/**
 * Enqueue scripts and styles.
 */
function kadence_child_parent_theme_enqueue_styles() {
	wp_enqueue_style(
		'kadence-child-style',
		get_stylesheet_uri(),
		array( 'kadence-global' ),
		wp_get_theme()->get( 'Version' )
	);
}

/**
 * Add aria-label to the mobile menu items with children.
 */
add_filter( 'walker_nav_menu_start_el', 'kadence_child_nav_menu_start_el', 99, 4 );
function kadence_child_nav_menu_start_el( $item_output, $item, $depth, $args ) {
	if ( in_array( 'menu-item-has-children', (array) ( $item->classes ?? array() ), true ) ) {
		/* translators: %s: parent menu item title. */
		$label = sprintf( __( 'Child menu of %s', 'kadence-child' ), $item->title );
		$item_output = str_replace( 'drawer-sub-toggle"', 'drawer-sub-toggle" aria-label="' . esc_attr( $label ) . '"', $item_output );
	}
	return $item_output;
}

// Filter kadence_breadcrumb_args
add_filter( 'kadence_breadcrumb_args', 'kadence_child_breadcrumb_args' );
function kadence_child_breadcrumb_args( $args ) {
    global $wp;
    $current_url = home_url( $wp->request );
    // get current page url
    $args += [
      'before' => '<a class="kadence-bread-current" aria-current="page" href="' . esc_url( $current_url ) . '" >',
      'after'  => '</a>',
      'delimiter_before' => '<span class="bc-delimiter" aria-hidden="true">'
    ];
    return $args;
}

// Filter navigation_markup_template
add_filter( 'navigation_markup_template', 'kadence_child_navigation_markup_template', 10, 2 );
function kadence_child_navigation_markup_template( $template, $class ) {
    $template = '<nav class="navigation %1$s" aria-label="%4$s">
			<div class="nav-links">%3$s</div>
		</nav>';

    return $template;
}
