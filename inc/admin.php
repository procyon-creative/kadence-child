<?php
/**
 * Theme settings page: stores the GitHub PAT used by the update checker.
 *
 * @package kadence_child
 */

defined( 'ABSPATH' ) || exit;

const KADENCE_CHILD_OPTION_GROUP = 'kadence_child_settings';
const KADENCE_CHILD_OPTION_NAME  = 'kadence_child_github_token';

add_action( 'admin_menu', 'kadence_child_add_settings_page' );
function kadence_child_add_settings_page() {
	add_options_page(
		__( 'Theme Updates', 'kadence-child' ),
		__( 'Theme Updates', 'kadence-child' ),
		'manage_options',
		'kadence-child-updates',
		'kadence_child_render_settings_page'
	);
}

add_action( 'admin_init', 'kadence_child_register_settings' );
add_action( 'admin_init', 'kadence_child_migrate_plaintext_token' );

/**
 * Upgrade any plaintext token stored before encryption was added.
 * Runs once per admin request — cheap, since most calls find nothing to do.
 */
function kadence_child_migrate_plaintext_token() {
	$stored = (string) get_option( KADENCE_CHILD_OPTION_NAME, '' );
	if ( '' === $stored || 0 === strpos( $stored, 'kadence_child_v1:' ) ) {
		return;
	}
	update_option( KADENCE_CHILD_OPTION_NAME, kadence_child_encrypt_token( $stored ) );
}
function kadence_child_register_settings() {
	register_setting(
		KADENCE_CHILD_OPTION_GROUP,
		KADENCE_CHILD_OPTION_NAME,
		array(
			'type'              => 'string',
			'sanitize_callback' => 'kadence_child_sanitize_token',
			'default'           => '',
		)
	);

	add_settings_section(
		'kadence_child_github_section',
		__( 'GitHub Updates', 'kadence-child' ),
		'kadence_child_render_section_intro',
		'kadence-child-updates'
	);

	add_settings_field(
		KADENCE_CHILD_OPTION_NAME,
		__( 'Personal Access Token', 'kadence-child' ),
		'kadence_child_render_token_field',
		'kadence-child-updates',
		'kadence_child_github_section',
		array( 'label_for' => KADENCE_CHILD_OPTION_NAME )
	);
}

function kadence_child_sanitize_token( $value ) {
	if ( '' !== kadence_child_get_environment_github_token() ) {
		return (string) get_option( KADENCE_CHILD_OPTION_NAME, '' );
	}

	$value = is_string( $value ) ? trim( $value ) : '';

	// Empty submission keeps the existing token (so re-saving the page doesn't wipe it).
	if ( '' === $value ) {
		return (string) get_option( KADENCE_CHILD_OPTION_NAME, '' );
	}

	// Already encrypted (e.g. internal callers like the plaintext-migration). Pass through.
	if ( 0 === strpos( $value, 'kadence_child_v1:' ) ) {
		return $value;
	}

	return kadence_child_encrypt_token( $value );
}

/**
 * Derive a 32-byte symmetric key from WordPress secrets defined in wp-config.php.
 * If the LOGGED_IN_KEY is rotated, previously-stored tokens become unreadable
 * and the admin will need to re-enter the PAT.
 */
function kadence_child_token_key() {
	$material = ( defined( 'LOGGED_IN_KEY' ) ? LOGGED_IN_KEY : '' )
		. ( defined( 'LOGGED_IN_SALT' ) ? LOGGED_IN_SALT : '' )
		. 'kadence_child_token';
	return substr( hash( 'sha256', $material, true ), 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
}

function kadence_child_encrypt_token( $plaintext ) {
	if ( ! function_exists( 'sodium_crypto_secretbox' ) ) {
		return $plaintext;
	}
	$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
	$cipher = sodium_crypto_secretbox( $plaintext, $nonce, kadence_child_token_key() );
	return 'kadence_child_v1:' . base64_encode( $nonce . $cipher );
}

function kadence_child_decrypt_token( $stored ) {
	$prefix = 'kadence_child_v1:';
	if ( '' === $stored || 0 !== strpos( $stored, $prefix ) ) {
		return $stored; // Empty, or legacy plaintext from before encryption was added.
	}
	if ( ! function_exists( 'sodium_crypto_secretbox_open' ) ) {
		return '';
	}
	$decoded = base64_decode( substr( $stored, strlen( $prefix ) ), true );
	if ( false === $decoded || strlen( $decoded ) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
		return '';
	}
	$nonce  = substr( $decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
	$cipher = substr( $decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
	$plain  = sodium_crypto_secretbox_open( $cipher, $nonce, kadence_child_token_key() );
	return false === $plain ? '' : $plain;
}

function kadence_child_render_section_intro() {
	$new_token_url = 'https://github.com/settings/personal-access-tokens/new';
	?>
	<p><?php esc_html_e( 'This theme pulls updates from GitHub. Public repositories do not require a token. For a private repository, provide a Personal Access Token with read-only Contents access.', 'kadence-child' ); ?></p>
	<ol style="margin-left:1.5em;">
		<li>
			<?php
			printf(
				/* translators: %s: link to GitHub new fine-grained PAT page */
				wp_kses(
					__( 'Open <a href="%s" target="_blank" rel="noopener">GitHub → New fine-grained personal access token</a>. You must have access to the theme repository.', 'kadence-child' ),
					array( 'a' => array( 'href' => true, 'target' => true, 'rel' => true ) )
				),
				esc_url( $new_token_url )
			);
			?>
		</li>
		<li><strong><?php esc_html_e( 'Token name:', 'kadence-child' ); ?></strong> <?php esc_html_e( 'something descriptive, e.g. the site domain.', 'kadence-child' ); ?></li>
		<li><strong><?php esc_html_e( 'Resource owner:', 'kadence-child' ); ?></strong> <?php esc_html_e( 'the account or organization that owns the theme repository.', 'kadence-child' ); ?></li>
		<li><strong><?php esc_html_e( 'Expiration:', 'kadence-child' ); ?></strong> <?php esc_html_e( '1 year (or your preferred rotation cadence).', 'kadence-child' ); ?></li>
		<li><strong><?php esc_html_e( 'Repository access:', 'kadence-child' ); ?></strong> <?php esc_html_e( 'Only select repositories → the theme repository.', 'kadence-child' ); ?></li>
		<li><strong><?php esc_html_e( 'Repository permissions:', 'kadence-child' ); ?></strong> <?php esc_html_e( 'find Contents in the list, then set its Access dropdown to', 'kadence-child' ); ?> <code>Read-only</code>. <?php esc_html_e( '(Metadata: Read-only is selected automatically.)', 'kadence-child' ); ?></li>
		<li><?php esc_html_e( 'Click Generate token, copy it, and paste it below. Save Changes.', 'kadence-child' ); ?></li>
	</ol>
	<p class="description"><?php esc_html_e( 'The token is encrypted at rest using a key derived from this site\'s LOGGED_IN_KEY / LOGGED_IN_SALT in wp-config.php. If those secrets are ever rotated, the stored token becomes unreadable and you will need to re-enter it.', 'kadence-child' ); ?></p>
	<?php
}

function kadence_child_render_token_field() {
	if ( '' !== kadence_child_get_environment_github_token() ) {
		printf(
			'<input type="password" id="%s" value="" disabled aria-describedby="kadence-child-token-source" class="regular-text" />',
			esc_attr( KADENCE_CHILD_OPTION_NAME )
		);
		echo '<p id="kadence-child-token-source" class="description">';
		esc_html_e( 'A GitHub token is provided by the KADENCE_CHILD_GITHUB_TOKEN environment variable. Remove it to edit the saved token here.', 'kadence-child' );
		echo '</p>';
		return;
	}

	$has_token = '' !== (string) get_option( KADENCE_CHILD_OPTION_NAME, '' );
	printf(
		'<input type="password" id="%s" name="%s" value="" autocomplete="off" class="regular-text" placeholder="%s" />',
		esc_attr( KADENCE_CHILD_OPTION_NAME ),
		esc_attr( KADENCE_CHILD_OPTION_NAME ),
		esc_attr( $has_token ? __( 'Token saved — leave blank to keep, or paste a new one to replace.', 'kadence-child' ) : __( 'github_pat_…', 'kadence-child' ) )
	);
}

function kadence_child_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<form action="options.php" method="post">
			<?php
			settings_fields( KADENCE_CHILD_OPTION_GROUP );
			do_settings_sections( 'kadence-child-updates' );
			submit_button();
			?>
		</form>
	</div>
	<?php
}

/**
 * Return the environment token without storing it in the database.
 */
function kadence_child_get_environment_github_token() {
	$token = getenv( 'KADENCE_CHILD_GITHUB_TOKEN' );
	return false === $token ? '' : trim( $token );
}

/**
 * Prefer the environment token, falling back to the saved token.
 */
function kadence_child_get_github_token() {
	$token = kadence_child_get_environment_github_token();
	if ( '' !== $token ) {
		return $token;
	}

	return kadence_child_decrypt_token( (string) get_option( KADENCE_CHILD_OPTION_NAME, '' ) );
}
