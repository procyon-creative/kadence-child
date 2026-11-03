<?php

final class ThemeIntegrationTest extends WP_UnitTestCase {
    public function test_child_theme_and_parent_are_loaded(): void {
        $theme = wp_get_theme();

        self::assertSame( 'kadence-child', $theme->get_stylesheet() );
        self::assertSame( 'kadence', $theme->get_template() );
        self::assertFalse( $theme->errors() );
        self::assertSame( 'Kadence', $theme->parent()->get( 'Name' ) );
        self::assertTrue( class_exists( 'Kadence\Theme' ) );
    }

    public function test_theme_registers_settings_hooks(): void {
        self::assertSame( 10, has_action( 'admin_init', 'kadence_child_register_settings' ) );
        self::assertSame( 10, has_action( 'admin_init', 'kadence_child_migrate_plaintext_token' ) );
        self::assertSame( 10, has_action( 'admin_menu', 'kadence_child_add_settings_page' ) );
    }

    public function test_child_styles_depend_on_parent_styles(): void {
        do_action( 'wp_enqueue_scripts' );
        $styles = wp_styles();

        self::assertTrue( wp_style_is( 'kadence-child-style', 'enqueued' ) );
        self::assertContains( 'kadence-global', $styles->registered['kadence-child-style']->deps );
        self::assertTrue( wp_style_is( 'kadence-global', 'enqueued' ) );
        self::assertFalse( wp_style_is( 'kadence-style', 'registered' ) );
        self::assertSame( wp_get_theme()->get( 'Version' ), $styles->registered['kadence-child-style']->ver );
        self::assertSame(
            get_stylesheet_directory_uri() . '/style.css',
            $styles->registered['kadence-child-style']->src
        );
    }

    public function test_registered_setting_encrypts_values_saved_through_wordpress(): void {
        $original_environment = getenv( 'KADENCE_CHILD_GITHUB_TOKEN' );
        putenv( 'KADENCE_CHILD_GITHUB_TOKEN' );
        try {
            kadence_child_register_settings();
            update_option( KADENCE_CHILD_OPTION_NAME, 'integration-token' );
            $stored = get_option( KADENCE_CHILD_OPTION_NAME );

            self::assertNotSame( 'integration-token', $stored );
            self::assertSame( 'integration-token', kadence_child_get_github_token() );
            self::assertSame(
                KADENCE_CHILD_OPTION_NAME,
                $GLOBALS['wp_settings_fields']['kadence-child-updates']['kadence_child_github_section'][KADENCE_CHILD_OPTION_NAME]['args']['label_for']
            );
        } finally {
            putenv( false === $original_environment ? 'KADENCE_CHILD_GITHUB_TOKEN' : 'KADENCE_CHILD_GITHUB_TOKEN=' . $original_environment );
        }
    }

    public function test_subscriber_cannot_render_settings_page(): void {
        wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
        ob_start();
        try {
            kadence_child_render_settings_page();
            self::assertSame( '', ob_get_contents() );
        } finally {
            ob_end_clean();
        }
    }

    public function test_menu_toggle_label_escapes_the_menu_title(): void {
        $post_id = self::factory()->post->create( array( 'post_title' => 'News " onmouseover="alert(1)' ) );
        $item = wp_setup_nav_menu_item( get_post( $post_id ) );
        $item->classes = array( 'menu-item-has-children' );
        $html = apply_filters(
            'walker_nav_menu_start_el',
            '<button class="drawer-sub-toggle" aria-expanded="false"></button>',
            $item,
            0,
            (object) array()
        );

        self::assertStringNotContainsString( ' onmouseover="', $html );
        self::assertStringContainsString( 'aria-label="Child menu of ' . esc_attr( $item->title ) . '"', $html );
    }

    public function test_menu_item_without_classes_is_unchanged(): void {
        $item = (object) array( 'title' => 'Home' );
        $html = '<a href="/">Home</a>';

        self::assertSame( $html, kadence_child_nav_menu_start_el( $html, $item, 0, (object) array() ) );
    }

    public function test_breadcrumb_url_is_escaped_for_html(): void {
        $this->go_to( '/example/' );
        $filter = function () {
            return 'https://example.org/?first=1&second=2';
        };
        add_filter( 'home_url', $filter );
        try {
            $args = kadence_child_breadcrumb_args( array() );
            self::assertStringContainsString( 'href="https://example.org/?first=1&#038;second=2"', $args['before'] );
        } finally {
            remove_filter( 'home_url', $filter );
        }
    }
}
