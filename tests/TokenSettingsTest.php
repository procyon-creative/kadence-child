<?php

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

final class TokenSettingsTest extends TestCase {
    use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

    private $original_environment;

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        $this->original_environment = getenv( 'KADENCE_CHILD_GITHUB_TOKEN' );
        putenv( 'KADENCE_CHILD_GITHUB_TOKEN' );
    }

    protected function tearDown(): void {
        if ( false === $this->original_environment ) {
            putenv( 'KADENCE_CHILD_GITHUB_TOKEN' );
        } else {
            putenv( 'KADENCE_CHILD_GITHUB_TOKEN=' . $this->original_environment );
        }
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_environment_token_takes_precedence_without_reading_database(): void {
        putenv( 'KADENCE_CHILD_GITHUB_TOKEN= environment-token ' );
        Functions\expect( 'get_option' )->never();

        self::assertSame( 'environment-token', kadence_child_get_github_token() );
    }

    public function test_unset_environment_uses_saved_encrypted_token(): void {
        $stored = kadence_child_encrypt_token( 'saved-token' );
        Functions\expect( 'get_option' )->once()
            ->with( KADENCE_CHILD_OPTION_NAME, '' )->andReturn( $stored );

        self::assertSame( 'saved-token', kadence_child_get_github_token() );
    }

    public function test_blank_environment_uses_saved_token(): void {
        putenv( 'KADENCE_CHILD_GITHUB_TOKEN=   ' );
        Functions\when( 'get_option' )->justReturn( '' );

        self::assertSame( '', kadence_child_get_github_token() );
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
        Functions\when( 'get_option' )->justReturn( 'existing-value' );

        self::assertSame( 'existing-value', kadence_child_sanitize_token( 'replacement' ) );
    }

    public function test_blank_submission_preserves_saved_token(): void {
        Functions\when( 'get_option' )->justReturn( 'existing-value' );

        self::assertSame( 'existing-value', kadence_child_sanitize_token( '  ' ) );
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
        Functions\when( 'get_option' )->justReturn( 'old-token' );
        Functions\expect( 'update_option' )->once()->with(
            KADENCE_CHILD_OPTION_NAME,
            Mockery::on( function ( $value ) {
                return 'old-token' !== $value && 'old-token' === kadence_child_decrypt_token( $value );
            } )
        );

        kadence_child_migrate_plaintext_token();
    }

    public function test_migration_leaves_encrypted_token_unchanged(): void {
        Functions\when( 'get_option' )->justReturn( kadence_child_encrypt_token( 'saved-token' ) );
        Functions\expect( 'update_option' )->never();

        kadence_child_migrate_plaintext_token();
    }

    public function test_environment_field_is_disabled_and_never_displays_secret(): void {
        putenv( 'KADENCE_CHILD_GITHUB_TOKEN=environment-secret' );
        Functions\expect( 'get_option' )->never();
        $html = $this->renderTokenField();

        self::assertStringContainsString( 'disabled', $html );
        self::assertStringContainsString( 'aria-describedby="kadence-child-token-source"', $html );
        self::assertStringContainsString( 'id="kadence-child-token-source"', $html );
        self::assertStringContainsString( 'KADENCE_CHILD_GITHUB_TOKEN', $html );
        self::assertStringNotContainsString( 'environment-secret', $html );
        self::assertStringContainsString( 'value=""', $html );
    }

    public function test_saved_token_field_is_editable_and_never_displays_secret(): void {
        Functions\when( 'get_option' )->justReturn( 'saved-secret' );
        $html = $this->renderTokenField();

        self::assertStringNotContainsString( 'disabled', $html );
        self::assertStringContainsString( 'id="' . KADENCE_CHILD_OPTION_NAME . '"', $html );
        self::assertStringContainsString( 'name="' . KADENCE_CHILD_OPTION_NAME . '"', $html );
        self::assertStringNotContainsString( 'saved-secret', $html );
        self::assertStringContainsString( 'value=""', $html );
    }

    private function renderTokenField(): string {
        Functions\when( 'esc_attr' )->alias( function ( $value ) {
            return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
        } );
        Functions\when( '__' )->returnArg();
        Functions\when( 'esc_html_e' )->alias( function ( $value ) {
            echo htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
        } );

        ob_start();
        try {
            kadence_child_render_token_field();
            return ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
}
