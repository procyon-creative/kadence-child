<?php

final class TokenSettingsTest extends WP_UnitTestCase {
    private $original_environment;

    public function set_up() {
        parent::set_up();
        $this->original_environment = getenv( 'KADENCE_CHILD_GITHUB_TOKEN' );
        putenv( 'KADENCE_CHILD_GITHUB_TOKEN' );
        delete_option( KADENCE_CHILD_OPTION_NAME );
    }

    public function tear_down() {
        if ( false === $this->original_environment ) {
            putenv( 'KADENCE_CHILD_GITHUB_TOKEN' );
        } else {
            putenv( 'KADENCE_CHILD_GITHUB_TOKEN=' . $this->original_environment );
        }
        parent::tear_down();
    }

    public function test_environment_token_takes_precedence_over_saved_token(): void {
        update_option( KADENCE_CHILD_OPTION_NAME, kadence_child_encrypt_token( 'saved-token' ) );
        putenv( 'KADENCE_CHILD_GITHUB_TOKEN= environment-token ' );

        self::assertSame( 'environment-token', kadence_child_get_github_token() );
    }

    public function test_unset_environment_uses_saved_encrypted_token(): void {
        $stored = kadence_child_encrypt_token( 'saved-token' );
        update_option( KADENCE_CHILD_OPTION_NAME, $stored );

        self::assertSame( 'saved-token', kadence_child_get_github_token() );
    }

    public function test_blank_environment_uses_saved_token(): void {
        putenv( 'KADENCE_CHILD_GITHUB_TOKEN=   ' );
        update_option( KADENCE_CHILD_OPTION_NAME, kadence_child_encrypt_token( 'saved-token' ) );

        self::assertSame( 'saved-token', kadence_child_get_github_token() );
    }

    public function test_encryption_round_trip_uses_random_nonce(): void {
        $first = kadence_child_encrypt_token( 'secret-token' );
        $second = kadence_child_encrypt_token( 'secret-token' );

        self::assertNotSame( $first, $second );
        self::assertStringNotContainsString( 'secret-token', $first );
        self::assertSame( 'secret-token', kadence_child_decrypt_token( $first ) );
        self::assertSame( 'secret-token', kadence_child_decrypt_token( $second ) );
    }

    public function test_tampered_ciphertext_is_rejected(): void {
        $stored = kadence_child_encrypt_token( 'secret-token' );
        $prefix = 'kadence_child_v1:';
        $bytes = base64_decode( substr( $stored, strlen( $prefix ) ), true );
        $bytes[ strlen( $bytes ) - 1 ] = chr( ord( $bytes[ strlen( $bytes ) - 1 ] ) ^ 1 );

        self::assertSame( '', kadence_child_decrypt_token( $prefix . base64_encode( $bytes ) ) );
    }

    /** @dataProvider invalidCiphertexts */
    public function test_malformed_encrypted_tokens_are_rejected( string $stored ): void {
        self::assertSame( '', kadence_child_decrypt_token( $stored ) );
    }

    public static function invalidCiphertexts(): array {
        return array(
            'invalid base64' => array( 'kadence_child_v1:!!!' ),
            'missing nonce' => array( 'kadence_child_v1:' . base64_encode( 'short' ) ),
        );
    }

    public function test_environment_token_prevents_saved_token_replacement(): void {
        putenv( 'KADENCE_CHILD_GITHUB_TOKEN=environment-token' );
        update_option( KADENCE_CHILD_OPTION_NAME, 'existing-value' );

        self::assertSame( 'existing-value', kadence_child_sanitize_token( 'replacement' ) );
    }

    public function test_blank_submission_preserves_saved_token(): void {
        update_option( KADENCE_CHILD_OPTION_NAME, 'existing-value' );

        self::assertSame( 'existing-value', kadence_child_sanitize_token( '  ' ) );
    }

    public function test_environment_override_preserves_database_token_when_settings_are_saved(): void {
        kadence_child_register_settings();
        update_option( KADENCE_CHILD_OPTION_NAME, 'saved-token' );
        $stored = get_option( KADENCE_CHILD_OPTION_NAME );
        putenv( 'KADENCE_CHILD_GITHUB_TOKEN=environment-token' );

        update_option( KADENCE_CHILD_OPTION_NAME, 'replacement' );

        self::assertSame( $stored, get_option( KADENCE_CHILD_OPTION_NAME ) );
        self::assertSame( 'environment-token', kadence_child_get_github_token() );

        putenv( 'KADENCE_CHILD_GITHUB_TOKEN' );
        self::assertSame( 'saved-token', kadence_child_get_github_token() );
    }

    public function test_new_token_is_trimmed_and_encrypted(): void {
        $stored = kadence_child_sanitize_token( ' new-token ' );

        self::assertNotSame( 'new-token', $stored );
        self::assertSame( 'new-token', kadence_child_decrypt_token( $stored ) );
    }

    public function test_encrypted_token_is_not_encrypted_twice(): void {
        $stored = kadence_child_encrypt_token( 'secret-token' );

        self::assertSame( $stored, kadence_child_sanitize_token( $stored ) );
    }

    public function test_plaintext_migration_encrypts_existing_token(): void {
        update_option( KADENCE_CHILD_OPTION_NAME, 'old-token' );
        kadence_child_register_settings();
        kadence_child_migrate_plaintext_token();
        $stored = get_option( KADENCE_CHILD_OPTION_NAME );

        self::assertNotSame( 'old-token', $stored );
        self::assertSame( 'old-token', kadence_child_decrypt_token( $stored ) );
    }

    public function test_migration_leaves_encrypted_token_unchanged(): void {
        $stored = kadence_child_encrypt_token( 'saved-token' );
        update_option( KADENCE_CHILD_OPTION_NAME, $stored );
        kadence_child_register_settings();
        kadence_child_migrate_plaintext_token();

        self::assertSame( $stored, get_option( KADENCE_CHILD_OPTION_NAME ) );
    }

    public function test_environment_field_is_disabled_and_never_displays_secret(): void {
        putenv( 'KADENCE_CHILD_GITHUB_TOKEN=environment-secret' );
        $html = $this->renderTokenField();

        self::assertStringContainsString( 'disabled', $html );
        self::assertStringContainsString( 'aria-describedby="kadence-child-token-source"', $html );
        self::assertStringContainsString( 'id="kadence-child-token-source"', $html );
        self::assertStringContainsString( 'KADENCE_CHILD_GITHUB_TOKEN', $html );
        self::assertStringNotContainsString( 'environment-secret', $html );
        self::assertStringContainsString( 'value=""', $html );
    }

    public function test_saved_token_field_is_editable_and_never_displays_secret(): void {
        update_option( KADENCE_CHILD_OPTION_NAME, kadence_child_encrypt_token( 'saved-secret' ) );
        $html = $this->renderTokenField();

        self::assertStringNotContainsString( 'disabled', $html );
        self::assertStringContainsString( 'id="' . KADENCE_CHILD_OPTION_NAME . '"', $html );
        self::assertStringContainsString( 'name="' . KADENCE_CHILD_OPTION_NAME . '"', $html );
        self::assertStringNotContainsString( 'saved-secret', $html );
        self::assertStringContainsString( 'value=""', $html );
    }

    private function renderTokenField(): string {
        ob_start();
        try {
            kadence_child_render_token_field();
            return ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
}
