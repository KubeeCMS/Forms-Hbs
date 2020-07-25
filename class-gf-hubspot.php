<?php

// don't load directly.
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

GFForms::include_feed_addon_framework();

/**
 * Gravity Forms HubSpot Add-On.
 *
 * @since     1.0
 * @package   GravityForms
 * @author    Rocketgenius
 * @copyright Copyright (c) 2019, Rocketgenius
 */
class GF_HubSpot extends GFFeedAddOn {

	/**
	 * Contains an instance of this class, if available.
	 *
	 * @since 1.0
	 * @var GF_HubSpot $_instance If available, contains an instance of this class
	 */
	private static $_instance = null;

	/**
	 * Defines the version of the Gravity Forms HubSpot Add-On.
	 *
	 * @since 1.0
	 * @var string $_version Contains the version.
	 */
	protected $_version = GF_HSPOT_VERSION;

	/**
	 * Defines the minimum Gravity Forms version required.
	 *
	 * @since 1.0
	 * @var string $_min_gravityforms_version The minimum version required.
	 */
	protected $_min_gravityforms_version = GF_HSPOT_MIN_GF_VERSION;

	/**
	 * Defines the plugin slug.
	 *
	 * @since 1.0
	 * @var string $_slug The slug used for this plugin.
	 */
	protected $_slug = 'gravityformshubspot';

	/**
	 * Defines the main plugin file.
	 *
	 * @since 1.0
	 * @var string $_path The path to the main plugin file, relative to the plugins folder.
	 */
	protected $_path = 'gravityformshubspot/hubspot.php';

	/**
	 * Defines the full path to this class file.
	 *
	 * @since 1.0
	 * @var string $_full_path The full path.
	 */
	protected $_full_path = __FILE__;

	/**
	 * Defines the URL where this add-on can be found.
	 *
	 * @since 1.0
	 * @var string
	 */
	protected $_url = 'http://gravityforms.com';

	/**
	 * Defines the title of this add-on.
	 *
	 * @since 1.0
	 * @var string $_title The title of the add-on.
	 */
	protected $_title = 'Gravity Forms HubSpot Add-On';

	/**
	 * Defines the short title of the add-on.
	 *
	 * @since 1.0
	 * @var string $_short_title The short title.
	 */
	protected $_short_title = 'HubSpot';

	/**
	 * Defines if Add-On should use Gravity Forms servers for update data.
	 *
	 * @since  1.0
	 * @var    bool
	 */
	protected $_enable_rg_autoupgrade = true;

	/**
	 * Defines the capability needed to access the Add-On settings page.
	 *
	 * @since  1.0
	 * @var    string $_capabilities_settings_page The capability needed to access the Add-On settings page.
	 */
	protected $_capabilities_settings_page = 'gravityforms_hubspot';

	/**
	 * Defines the capability needed to access the Add-On form settings page.
	 *
	 * @since  1.0
	 * @var    string $_capabilities_form_settings The capability needed to access the Add-On form settings page.
	 */
	protected $_capabilities_form_settings = 'gravityforms_hubspot';

	/**
	 * Defines the capability needed to uninstall the Add-On.
	 *
	 * @since  1.0
	 * @var    string $_capabilities_uninstall The capability needed to uninstall the Add-On.
	 */
	protected $_capabilities_uninstall = 'gravityforms_hubspot_uninstall';

	/**
	 * Defines the capabilities needed for the HubSpot Add-On
	 *
	 * @since  1.0
	 * @var    array $_capabilities The capabilities needed for the Add-On
	 */
	protected $_capabilities = array( 'gravityforms_hubspot', 'gravityforms_hubspot_uninstall' );

	/**
	 * Contains an instance of the HubSpot API library, if available.
	 *
	 * @since  1.0
	 * @var    GF_HubSpot_API $api If available, contains an instance of the HubSpot API library.
	 */
	protected $api = null;

	/**
	 * Returns an instance of this class, and stores it in the $_instance property.
	 *
	 * @since 1.0
	 *
	 * @return GF_HubSpot $_instance An instance of the GF_HubSpot class
	 */
	public static function get_instance() {
		if ( self::$_instance == null ) {
			self::$_instance = new GF_HubSpot();
		}

		return self::$_instance;
	}

	/**
	 * Prevent the class from being cloned
	 *
	 * @since 1.0
	 */
	private function __clone() {
	} /* do nothing */

	/**
	 * Set feed creation control.
	 *
	 * @since  1.0
	 *
	 * @return bool
	 */
	public function can_create_feed() {

		if ( $this->initialize_api() ) {

			// Validating API.
			$this->log_debug( __METHOD__ . '(): Validating API credentials.' );
			$contacts = $this->api->get_contacts();

			if ( is_wp_error( $contacts ) ) {
				return false;
			}

			return true;
		}

		return false;

	}

	/**
	 * Indicates if the feed can be duplicated.
	 *
	 * @since 1.0
	 * @since 1.3 Enabled feed duplication.
	 *
	 * @param int $id Feed ID requesting duplication.
	 *
	 * @return bool
	 */
	public function can_duplicate_feed( $id ) {

		return true;
	}

	/**
	 * Duplicates the feed and triggers creation of a corresponding form in HubSpot.
	 *
	 * @since 1.3
	 *
	 * @param array|int $id          The ID of the feed to be duplicated or the feed object when duplicating a form.
	 * @param bool|int  $new_form_id False when using feed actions or the ID of the new form when duplicating a form.
	 *
	 * @return int
	 */
	public function duplicate_feed( $id, $new_form_id = false ) {
		$new_feed_id = parent::duplicate_feed( $id, $new_form_id );

		if ( $new_feed_id && $feed = $this->get_feed( $new_feed_id ) ) {
			$delimiter                = '. FID: ';
			$items                    = explode( $delimiter, $feed['meta']['_hs_form'] );
			$feed['meta']['_hs_form'] = $items[0] . $delimiter . $feed['id'];
			$this->recreate_hubspot_form( $feed, false );
		}

		return $new_feed_id;
	}

	/**
	 * Setup columns for feed list table.
	 *
	 * @since  1.0
	 *
	 * @return array
	 */
	public function feed_list_columns() {

		return array(
			'feed_name' => esc_html__( 'Name', 'gravityformshubspot' ),
		);

	}

	/**
	 * Plugin starting point. Adds PayPal delayed payment support.
	 *
	 * @since  1.0
	 */
	public function init() {

		parent::init();

		$this->add_delayed_payment_support(
			array(
				'option_label' => esc_html__( 'Create record in HubSpot only when payment is received.', 'gravityformshubspot' ),
			)
		);

		add_action( 'wp_footer', array( $this, 'action_wp_footer' ) );

	}

	/**
	 * Add AJAX callbacks.
	 *
	 * @since  1.0
	 */
	public function init_ajax() {
		parent::init_ajax();

		// Add AJAX callback for de-authorizing with HubSpot.
		add_action( 'wp_ajax_gfhubspot_deauthorize', array( $this, 'ajax_deauthorize' ) );
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @since 1.0
	 *
	 * @return array
	 */
	public function scripts() {

		$min     = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG || isset( $_GET['gform_debug'] ) ? '' : '.min';
		$form_id = absint( rgget( 'id' ) );
		$form    = GFAPI::get_form( $form_id );

		$routing_fields = ! empty( $form ) ? GFCommon::get_field_filter_settings( $form ) : array();
		$hubspot_owners = $this->get_hubspot_owners();

		$scripts = array(
			array(
				'handle'  => 'gform_hubspot_pluginsettings',
				'deps'    => array( 'jquery' ),
				'src'     => $this->get_base_url() . "/js/plugin_settings{$min}.js",
				'version' => $this->_version,
				'enqueue' => array(
					array(
						'admin_page' => array( 'plugin_settings' ),
						'tab'        => $this->_slug,
					),
				),
				'strings' => array(
					'disconnect'   => array(
						'site'    => wp_strip_all_tags( __( 'Are you sure you want to disconnect from HubSpot for this website?', 'gravityformshubspot' ) ),
						'account' => wp_strip_all_tags( __( 'Are you sure you want to disconnect all Gravity Forms sites connected to this HubSpot account?', 'gravityformshubspot' ) ),
					),
					'settings_url' => admin_url( 'admin.php?page=gf_settings&subview=' . $this->get_slug() ),
					'deauth_nonce' => wp_create_nonce( 'gf_hubspot_deauth' ),
				),
			),
			array(
				'handle'  => 'gform_hubspot_owner_settings',
				'deps'    => array( 'jquery' ),
				'src'     => $this->get_base_url() . "/js/contact_owner_setting{$min}.js",
				'version' => $this->_version,
				'enqueue' => array(
					array( 'query' => "page=gf_edit_forms&view=settings&subview={$this->_slug}&fid=_notempty_" ),
					array( 'query' => "page=gf_edit_forms&view=settings&subview={$this->_slug}&fid=0" ),
				),
				'strings' => array(
					'fields'    => $routing_fields,
					'owners'    => $hubspot_owners,
					'assign_to' => wp_strip_all_tags( __( 'Assign To', 'gravityfromshubspot' ) ),
					'condition' => wp_strip_all_tags( __( 'Condition', 'gravityfromshubspot' ) ),
				),
			),
		);

		return array_merge( parent::scripts(), $scripts );

	}

	/**
	 * Register needed styles.
	 *
	 * @since  1.0
	 *
	 * @return array $styles
	 */
	public function styles() {

		$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG || isset( $_GET['gform_debug'] ) ? '' : '.min';

		$styles = array(
			array(
				'handle'  => 'gform_hubspot_pluginsettings',
				'src'     => $this->get_base_url() . "/css/plugin_settings{$min}.css",
				'version' => $this->_version,
				'enqueue' => array(
					array(
						'admin_page' => array( 'plugin_settings' ),
						'tab'        => $this->_slug,
					),
				),
			),
			array(
				'handle'  => 'gform_hubspot_formsettings',
				'src'     => $this->get_base_url() . "/css/form_settings{$min}.css",
				'version' => $this->_version,
				'enqueue' => array(
					array(
						'admin_page' => array( 'form_settings' ),
						'tab'        => $this->_slug,
					),
				),
			),
		);

		return array_merge( parent::styles(), $styles );

	}

	/**
	 * Update auth tokens.
	 *
	 * @since  1.0
	 */
	public function plugin_settings_page() {

		$this->maybe_update_auth_tokens();

		parent::plugin_settings_page();

	}

	/**
	 * Store auth tokens when we get auth payload from HubSpot.
	 *
	 * @since 1.0
	 */
	public function maybe_update_auth_tokens() {
		// If access token is provided, save it.
		if ( rgget( 'auth_payload' ) ) {
			// Get the authentication token.
			$auth_token   = $this->get_plugin_setting( 'auth_token' );
			$settings     = array();
			$auth_payload = unserialize( base64_decode( rgget( 'auth_payload' ) ) );

			if ( empty( $auth_token ) || $auth_token['access_token'] !== $auth_payload['access_token'] ) {
				// Add token info to plugin settings.
				$settings['auth_token'] = array(
					'access_token'  => $auth_payload['access_token'],
					'refresh_token' => $auth_payload['refresh_token'],
					'date_created'  => time(),
				);

				// Save plugin settings.
				$this->update_plugin_settings( $settings );

				GFCommon::add_message( esc_html__( 'HubSpot settings have been updated.', 'gravityformshubspot' ) );
			}

			//Recreate HubSpot Forms if there are any existing HubSpot feeds
			$this->maybe_recreate_hubspot_forms();
		}

		// If error is provided, display message.
		if ( rgget( 'auth_error' ) ) {
			// Add error message.
			GFCommon::add_error_message( esc_html__( 'Unable to connect your HubSpot account.', 'gravityformshubspot' ) );
		}
	}

	/**
	 * Recreate HubSpot forms for any existing HubSpot feed. It will go through all the feeds and
	 * recreates the associated HubSpot Form for each.
	 *
	 * @since 1.0
	 * @since 1.3 Updated to use recreate_hubspot_form().
	 */
	public function maybe_recreate_hubspot_forms() {

		$feeds = $this->get_feeds_by_slug( $this->_slug );
		foreach ( $feeds as $feed ) {
			$this->recreate_hubspot_form( $feed );
		}
	}

	/**
	 * Recreates the HubSpot form for the given feed.
	 *
	 * @since 1.3
	 *
	 * @param array $feed        The feed the HubSpot form is to be created for.
	 * @param bool  $reset_owner Indicates if the contact owner should be set to none.
	 */
	public function recreate_hubspot_form( $feed, $reset_owner = true ) {
		$result = $this->create_hubspot_form( $feed['meta'], $feed['form_id'] );
		if ( ! $result ) {
			// If form could not be created, try again with a unique name.
			$feed['meta']['_hs_form'] .= '.' . uniqid();

			$result = $this->create_hubspot_form( $feed['meta'], $feed['form_id'] );
		}

		if ( $result ) {
			$feed['meta']['_hs_form']      = $this->get_hubspot_formname_without_warning( $result['name'] );
			$feed['meta']['_hs_form_guid'] = $result['guid'];
			$feed['meta']['_hs_portal_id'] = $result['portal_id'];

			if ( $reset_owner ) {
				$feed['meta']['contact_owner'] = 'none';
			}

			$this->update_feed_meta( $feed['id'], $feed['meta'] );
		}
	}

	/**
	 * Setup plugin settings fields.
	 *
	 * @since  1.0
	 *
	 * @return array
	 */
	public function plugin_settings_fields() {
		// Prepare plugin description.
		$description  = '<p>';
		$description .= esc_html__( 'HubSpot is an all-in-one CRM, Sales, Marketing, and Customer Service platform.', 'gravityformshubspot' );
		$description .= '</p>';
		$description .= '<p>';
		$description .= esc_html__( 'The Gravity Forms HubSpot Add-On connects the power of the world’s leading growth platform - HubSpot - with Gravity Forms so your business can grow better.', 'gravityformshubspot' );
		$description .= '</p>';
		$description .= '<p>';
		$description .= sprintf(
			/* translators: 1: Open link tag 2: Close link tag */
			esc_html__( 'If you don\'t have a HubSpot account, you can %1$ssign up for your free HubSpot account here%2$s.', 'gravityformshubspot' ),
			'<a href="https://app.hubspot.com/signup-v2/marketing/?utm_source=Gravity-Forms-wordpress&utm_medium=referral" target="_blank">', '</a>'
		);
		$description .= '</p>';

		return array(
			array(
				'title'       => '',
				'description' => $description,
				'fields'      => array(
					array(
						'name'              => 'auth_token',
						'type'              => 'auth_token_button',
						'feedback_callback' => array( $this, 'initialize_api' ),
					),
				),
			),
		);

	}

	/**
	 * Create Generate Auth Token settings field.
	 *
	 * @since  1.0
	 *
	 * @param  array $field Field properties.
	 * @param  bool  $echo  Display field contents. Defaults to true.
	 *
	 * @return string
	 */
	public function settings_auth_token_button( $field, $echo = true ) {
		$html = '';

		if ( $this->initialize_api() ) {
			// Validating API.
			$this->log_debug( __METHOD__ . '(): Validating API credentials.' );
			$contacts = $this->api->get_contacts();

			if ( is_wp_error( $contacts ) ) {
				$this->api = null;
			} else {
				$html  = '<p>' . esc_html__( 'Signed into HubSpot.', 'gravityformshubspot' );
				$html .= '</p>';
				$html .= sprintf(
					' <a href="#" class="button deauth_button">%1$s</a>',
					esc_html__( 'Disconnect your HubSpot account', 'gravityformshubspot' )
				);

				$html .= '<div id="deauth_scope" class="alert_red" style="margin-top: 20px;padding:20px; padding-top:5px;">';
				$html .= '<p><label for="deauth_scope0"><input type="radio" name="deauth_scope" value="site" id="deauth_scope0" checked="checked">' . esc_html__( 'De-authorize this site only', 'gravityformshubspot' ) . '</label></p>';
				$html .= '<p><label for="deauth_scope1"><input type="radio" name="deauth_scope" value="account" id="deauth_scope1">' . esc_html__( 'Disconnect all Gravity Forms sites connected to this HubSpot account', 'gravityformshubspot' ) . '</label></p>';
				$html .= '<p>' . sprintf( ' <a href="#" class="button deauth_button" id="gform_hubspot_deauth_button">%1$s</a>', esc_html__( 'Disconnect your HubSpot account', 'gravityformshubspot' ) ) . '</p>';
				$html .= '</div>';
			}
		}

		if ( $this->api === null ) {
			// If SSL is available, display custom app settings.
			if ( is_ssl() ) {
				$license_key  = GFCommon::get_key();
				$settings_url = urlencode( admin_url( 'admin.php?page=gf_settings&subview=' . $this->_slug ) );
				$auth_url     = add_query_arg( array( 'redirect_to' => $settings_url, 'license' => $license_key ), $this->get_gravity_api_url( '/auth/hubspot' ) );

				$html = sprintf(
					'<a href="%2$s" class="button" id="gform_hubspot_auth_button">%s</a>',
					esc_html__( 'Click here to connect your HubSpot account', 'gravityformshubspot' ),
					$auth_url
				);
			} else {
				$html  = '<div class="alert_red" style="padding:20px; padding-top:5px;">';
				$html .= '<h4>' . esc_html__( 'SSL Certificate Required', 'gravityformshubspot' ) . '</h4>';
				/* translators: 1: Open link tag 2: Close link tag */
				$html .= sprintf( esc_html__( 'Make sure you have an SSL certificate installed and enabled, then %1$sclick here to continue%2$s.', 'gravityformshubspot' ), '<a href="' . admin_url( 'admin.php?page=gf_settings&subview=gravityformshubspot', 'https' ) . '">', '</a>' );
				$html .= '</div>';
			}
		}

		if ( $echo ) {
			echo $html;
		}

		return $html;

	}

	/**
	 * Get Gravity API URL.
	 *
	 * @since 1.0
	 *
	 * @param string $path Path.
	 *
	 * @return string
	 */
	public function get_gravity_api_url( $path = '' ) {
		return ( defined( 'GRAVITY_API_URL' ) ? GRAVITY_API_URL : 'https://gravityapi.com/wp-json/gravityapi/v1' ) . $path;
	}

	/**
	 * Initializes the HubSpot API if credentials are valid.
	 *
	 * @since  1.0
	 *
	 * @return bool|null API initialization state. Returns null if no authentication token is provided.
	 */
	public function initialize_api() {

		// If the API is already initializes, return true.
		if ( ! is_null( $this->api ) ) {
			return true;
		}

		// Initialize HubSpot API library.
		if ( ! class_exists( 'GF_HubSpot_API' ) ) {
			require_once 'includes/class-gf-hubspot-api.php';
		}

		// Get the authentication token.
		$auth_token = $this->get_plugin_setting( 'auth_token' );

		// If the authentication token is not set, return null.
		if ( rgblank( $auth_token ) ) {
			return null;
		}

		// Initialize a new HubSpot API instance.
		$hubspot = new GF_HubSpot_API( $auth_token );

		if ( time() > $auth_token['date_created'] + 21600 ) { // Access token expires in 6 hours = 21,600 seconds.
			// Log that authentication test failed.
			$this->log_debug( __METHOD__ . '(): API tokens expired, start refreshing.' );

			// refresh token.
			$auth_token = $hubspot->refresh_token();
			if ( ! is_wp_error( $auth_token ) ) {
				$settings['auth_token'] = array(
					'access_token'  => $auth_token['access_token'],
					'refresh_token' => $auth_token['refresh_token'],
					'date_created'  => time(),
				);

				// Save plugin settings.
				$this->update_plugin_settings( $settings );
				$this->log_debug( __METHOD__ . '(): API access token has been refreshed.' );

			} else {
				$this->log_debug( __METHOD__ . '(): API access token failed to be refreshed; ' . $auth_token->get_error_message() );

				return false;
			}
		}

		// Assign HubSpot API instance to the Add-On instance.
		$this->api = $hubspot;

		return true;

	}

	/**
	 * Revoke token and remove them from Settings.
	 *
	 * Note we cannot revoke refresh token ($this->api->revoke_token()) because the refresh token is shared across
	 * all sites authenticated under the same accounts.
	 *
	 * @since 1.0
	 */
	public function ajax_deauthorize() {
		check_ajax_referer( 'gf_hubspot_deauth', 'nonce' );
		$scope = sanitize_text_field( $_POST['scope'] );

		// If user is not authorized, exit.
		if ( ! GFCommon::current_user_can_any( $this->_capabilities_settings_page ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Access denied.', 'gravityformshubspot' ) ) );
		}

		// If API instance is not initialized, return error.
		if ( ! $this->initialize_api() ) {
			$this->log_error( __METHOD__ . '(): Unable to de-authorize because API is not initialized.' );

			wp_send_json_error();
		}

		// Delete all HubSpot forms associated with existing HubSpot feeds.
		$this->delete_hubspot_forms();

		if ( $scope === 'account' ) {
			$result = $this->api->revoke_token();

			if ( is_wp_error( $result ) ) {
				$this->log_error( __METHOD__ . '(): Unable to revoke token; ' . $result->get_error_message() );

				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}

			$this->log_error( __METHOD__ . '(): All Gravity Forms sites connected to this HubSpot account have been disconnected.' );
		}

		// Remove access token from settings.
		delete_option( 'gravityformsaddon_' . $this->_slug . '_settings' );

		// Log that we revoked the access token.
		$this->log_debug( __METHOD__ . '(): This website has been disconnected from HubSpot.' );

		// Return success response.
		wp_send_json_success();
	}

	/**
	 * Deletes all HubSpot forms associated with feeds. This function is called during the process of de-authorizing a HubSpot account
	 * and serves as a clean up routine so that Gravity Forms created forms aren't lingering around on a disconnected HubSpot account.
	 *
	 * @since 1.0
	 */
	public function delete_hubspot_forms() {

		//Getting all HubSpot feeds across all forms
		$feeds = $this->get_feeds_by_slug( $this->_slug );

		//Deleting all associated HubSpot forms
		foreach ( $feeds as $feed ) {
			$this->delete_hubspot_form( $feed );
		}
	}


	/**
	 * Deletes the HubSpot form associated with the specified feed
	 * @since 1.0
	 *
	 * @param array $feed Feed object that is associated with HubSpot Form
	 */
	public function delete_hubspot_form( $feed ) {

		if ( $this->initialize_api() ) {

			$guid = $feed['meta']['_hs_form_guid'];
			$this->api->delete_form( $guid );
		}
	}

	/**
	 * Setup fields for feed settings.
	 *
	 * @since 1.0
	 *
	 * @return array
	 */
	public function feed_settings_fields() {

		$contact_properties = $this->get_hubspot_contact_properties();

		$form = $this->get_current_form();

		// Prepare base feed settings section.
		$basic_section =
			array(
				'title'  => '',
				'fields' => array(
					array(
						'name'          => 'feed_name',
						'label'         => esc_html__( 'Name', 'gravityformshubspot' ),
						'type'          => 'text',
						'class'         => 'medium',
						'required'      => true,
						'default_value' => $this->get_default_feed_name(),
						'tooltip'       => '<h6>' . esc_html__( 'Name', 'gravityformshubspot' ) . '</h6>' . esc_html__( 'Enter a feed name to uniquely identify this feed.', 'gravityformshubspot' ),
					),
					array(
						'name'          => 'feed_type',
						'label'         => esc_html__( 'Feed Type', 'gravityformshubspot' ),
						'type'          => 'select',
						'choices'    => array(
							array( 'label' => __( 'Create Contact', 'gravityformshubspot' ), 'value' => 'create_contact' ),
						),
						'default_value' => 'create_contact',
						'hidden'		=> true,
					),
					array(
						'name'    	=> '_hs_form',
						'label'   	=> esc_html__( 'HubSpot Form Name', 'gravityformshubspot' ),
						'type'    	=> 'hubspotform',
						'class'   	=> 'medium',
						'required' 	=> true,
						'tooltip' 	=> esc_html__( 'Enter the name for the form that will be automatically created in your HubSpot account to work in conjunction with this feed. This HubSpot form will be configured to match your mapped fields below and is required. Once created, please don\'t remove or edit it.', 'gravityformshubspot' ),
						'default_value' => 'Gravity Forms - ' . $form['title'],
						'validation_callback' => array( $this, 'validate_hubspot_form' ),

					),
				),
			);

		foreach ( $contact_properties['selection'] as $property ) {
			$basic_section['fields'][] = array(
				'name'       => $property['name'],
				'label'      => $property['label'],
				'type'       => 'select',
				'default_value' => rgar( $property, 'default_value' ),
				'tooltip' => rgar( $property, 'tooltip' ),
				'choices' => $property['choices'],
			);
		}

		$basic_section['fields'][] = array(

			'name'       => 'contact_owner',
			'label'      => esc_html__( 'Contact Owner', 'gravityformshubspot' ),
			'type'       => 'radio',
			'horizontal' => true,
			'default_value' => 'none',
			'choices'    => array(
				array( 'label' => __( 'None&nbsp;&nbsp;', 'gravityformshubspot' ), 'value' => 'none' ),
				array( 'label' => __( 'Select Owner&nbsp;&nbsp;', 'gravityformshubspot' ), 'value' => 'select' ),
				array( 'label' => __( 'Assign Conditionally', 'gravityformshubspot' ), 'value' => 'conditional' ),
			),
			'tooltip'    => '<h6>' . esc_html__( 'Contact Owner', 'gravityforms' ) . '</h6>' . esc_html__( 'Select a HubSpot user that will be assigned as the owner of the newly created Contact.', 'gravityformshubspot' ),
		);

		$contact_owner_section =
			array(
				'title'  => esc_html__( 'Contact Owner', 'gravityformshubspot' ),
				'class'  => 'contact_owner_section',
				'fields' => array(
					array(
						'name'    => 'contact_owner_select',
						'label'   => esc_html__( 'Select Owner', 'gravityformshubspot' ),
						'type'    => 'select',
						'choices' => $this->get_hubspot_owners(),
					),
					array(
						'name'  => 'contact_owner_conditional',
						'label' => '',
						'class' => 'large',
						'type'  => 'conditions',
					),
				),
			);

		$field_map_section =
			array(
				'title'  => 'Map Contact Fields',
				'fields' => $contact_properties['basic'],
			);

		$additional_fields_section =
			array(
				'title'  => esc_html__( 'Add Additional Contact Fields', 'gravityformshubspot' ),
				'fields' => array(
					array(
						'name'      => 'additional_fields',
						'label'     => '',
						'type'      => 'dynamic_field_map',
						'key_field_title' => 'HubSpot',
						'value_field_title' => 'Gravity Forms',
						'enable_custom_key' => false,
						'field_map' => $contact_properties['grouped'],
					),
				),

			);

		$other_fields_section =
			array(
				'title'  => esc_html__( 'Additional Options', 'gravityformshubspot' ),
				'fields' => array(
					array(
						'name'    => 'conditionalLogic',
						'label'   => esc_html__( 'Conditional Logic', 'gravityforms' ),
						'type'    => 'feed_condition',
						'tooltip' => '<h6>' . esc_html__( 'Conditional Logic', 'gravityforms' ) . '</h6>' . esc_html__( 'When conditions are enabled, HubSpot contacts will only be created when the conditions are met. When disabled, a HubSpot contact will be created for every form submission.', 'gravityforms' ),
					),
				),

			);


		$settings_fields = array( $basic_section, $contact_owner_section, $field_map_section, $additional_fields_section, $other_fields_section );


		return $settings_fields;
	}

	/***
	 * Overrides the parent field to remove the Street Address (Line 2) field from the field map options
	 *
	 * @since 1.0
	 *
	 * @param int               $form_id             Current Form Id
	 * @param array|string|null $field_type          Current field type
	 * @param array|string|null $exclude_field_types Field types to be excluded from drop down
	 *
	 * @return array
	 */
	public static function get_field_map_choices( $form_id, $field_type = null, $exclude_field_types = null ) {

		$choices = parent::get_field_map_choices( $form_id, $field_type, $exclude_field_types );
		$form = GFAPI::get_form( $form_id );
		$address_fields = GFAPI::get_fields_by_type( $form, array( 'address' ) );
		if ( ! is_array( $address_fields ) ) {
			return $choices;
		}

		$address_line2_ids = array();
		foreach ( $address_fields as $address_field ) {
			$address_line2_ids[] = $address_field->id . '.2';
		}

		$new_choices = array();
		foreach ( $choices as $choice ) {
			if ( ! in_array( $choice['value'], $address_line2_ids ) ) {
				$new_choices[] = $choice;
			}
		}

		return $new_choices;
	}

	/**
	 * Displays the currently configured HubSpot Form.
	 *
	 * @since 1.0
	 *
	 * @param array $field Field object.
	 * @param bool  $echo True if HTML should be printed on screen.
	 *
	 * @return string
	 */
	public function settings_hubspotform( $field, $echo = true ) {

		$field['type'] = 'text';
		$html = $this->settings_text( $field, false );

		$guid  = $this->get_setting( $field['name'] . '_guid' );
		$html .= '<input
                    type="hidden"
                    name="_gaddon_setting_' . esc_attr( $field['name'] ) . '_guid"
                    value="' . esc_attr( htmlspecialchars( $guid, ENT_QUOTES ) ) . '" ' .
		         ' />';

		if ( $echo ) {
			echo $html;
		}

		return $html;
	}

	/**
	 * Validates that the HubSpot form name is unique
	 *
	 * @param array  $field       Field array containing the configuration options of this field.
	 * @param string $field_value Submitted value.
	 *
	 */
	public function validate_hubspot_form( $field, $field_value = '' ) {

		// Get settings.
		$settings = $this->get_current_settings();

		$forms = $this->api->get_forms();

		if ( is_wp_error( $forms ) ) {
			$this->set_field_error( $field, esc_html__( 'There was an error validating the form name. Please try saving again', 'gravityformshubspot' ) );
		}

		$form_name = $field_value . $this->get_hubspot_formname_warning();
		foreach ( $forms as $form ) {
			if ( $form['name'] == $form_name && $settings['_hs_form_guid'] != $form['guid'] ) {
				$this->set_field_error( $field, esc_html__( 'This form name is already in use in HubSpot. Please enter a unique form name.', 'gravityformshubspot' ) );
			}
		}
	}

	/***
	 * Renders the HTML for the Contact Owner conditions setting.
	 *
	 * @since 1.0
	 *
	 * @param array $field Field object.
	 * @param bool  $echo True if HTML should be printed on screen.
	 */
	public function settings_conditions( $field, $echo = true ) {

		echo '<div id="gform_conditions_setting" class="gform_hubspot_conditions" ></div>';
		$field['name'] = 'conditions';

		$this->settings_hidden( $field );

	}

	/**
	 * Overrides parent class to create/update HubSpot form when feed is saved.
	 *
	 * @since 1.0
	 *
	 * @param int   $feed_id Feed ID.
	 * @param int   $form_id Form ID.
	 * @param array $settings Feed settings.
	 *
	 * @return array|bool
	 */
	public function save_feed_settings( $feed_id, $form_id, $settings ) {
		global $_gaddon_posted_settings;

		if ( $feed_id ) {
			$result = $this->update_hubspot_form( rgar( $settings, '_hs_form_guid' ), $settings, $form_id );
		} else {
			$result = $this->create_hubspot_form( $settings, $form_id );
		}

		if ( ! $result ) {
			return false;
		}

		$_gaddon_posted_settings['_hs_form_guid'] = $settings['_hs_form_guid'] = $result['guid'];
		$_gaddon_posted_settings['_hs_portal_id'] = $settings['_hs_portal_id'] = $result['portal_id'];

		// Saving feed.
		return parent::save_feed_settings( $feed_id, $form_id, $settings );
	}

	/**
	 * Delete associated HubSpot form and then deletes feed.
	 *
	 * @since 1.0
	 *
	 * @param int $id Id of feed to be deleted.
	 */
	public function delete_feed( $id ) {

		$feed = $this->get_feed( $id );
		$this->delete_hubspot_form( $feed );

		parent::delete_feed( $id );
	}


	/**
	 * Creates a HubSpot form based on the provided feed settings
	 *
	 * @since 1.0
	 *
	 * @param array $feed_meta Current feed meta.
	 * @param int   $form_id   The ID of the form the feed belongs to.
	 *
	 * @return array|bool Returns the auto-generated form data from HubSpot if successful. Otherwise returns false.
	 */
	public function create_hubspot_form( $feed_meta, $form_id ) {

		$this->initialize_api();

		$hs_form = $this->generate_hubspot_form_object( $feed_meta, $form_id );

		$api_result = $this->api->create_form( $hs_form );

		// Display error message if HubSpot form could not be updated/created.
		if ( is_wp_error( $api_result ) ) {
			$this->set_field_error( array( 'name' => '_hs_form' ), esc_html__( 'Could not save HubSpot form. Please try again later', 'gravityformshubspot' ) );

			return false;
		}

		return array(
			'name'      => $api_result['name'],
			'guid'      => $api_result['guid'],
			'portal_id' => $api_result['portalId'],
		);
	}

	/**
	 * Updates an existing HubSpot form to match the provided feed $settings, or creates a new one if GUID doesn't match any form in HubSpot.
	 *
	 * @since 1.0
	 *
	 * @param string $guid     GUID of HubSpot form to be updated.
	 * @param array  $settings Current feed settings.
	 * @param int    $form_id  The ID of the form the feed belongs to.
	 *
	 * @return array|bool Returns an array with the newly updated form name and form GUID if updated successfully. Otherwise return false.
	 */
	public function update_hubspot_form( $guid, $settings, $form_id ) {

		// 1- Get HubSpot form.
		$existing_form = $this->api->get_form( $guid );
		if ( is_wp_error( $existing_form ) ) {

			$error_data = $existing_form->get_error_data();
			if ( $error_data['status'] == 404 ) {

				// Form doesn't exist. Create a new one.
				return $this->create_hubspot_form( $settings, $form_id );
			} else {
				// Error when getting existing form. Abort to throw validation error.
				return false;
			}
		} else {

			// Form exists. Update it.
			$form       = $this->generate_hubspot_form_object( $settings, $form_id );
			$api_result = $this->api->update_form( $guid, $form );

			// Display error message if HubSpot form could not be updated/created.
			if ( is_wp_error( $api_result ) ) {
				$this->set_field_error( array( 'name' => '_hs_form' ), esc_html__( 'Could not save HubSpot form', 'gravityformshubspot' ) . '. Error: ' . $api_result->get_error_message( 'hubspot_api_error' ) );

				return false;
			}

			return array(
				'name'      => $api_result['name'],
				'guid'      => $api_result['guid'],
				'portal_id' => $api_result['portalId'],
			);
		}
	}

	/**
	 * Based on the fields mapped in the feed settings ( i.e. $settings variable ), creates a HubSpot form object to create or update a HubSpot form.
	 *
	 * @since 1.0
	 *
	 * @param array $feed_meta Current feed settings.
	 * @param int   $form_id   The ID of the form the feed belongs to.
	 *
	 * @return array Returns a HubSpot form object based on specified settings.
	 */
	public function generate_hubspot_form_object( $feed_meta, $form_id ) {

		$fields          = array();
		$properties      = $this->get_hubspot_contact_properties();
		$settings_fields = array_merge( $properties['basic'], $properties['additional'], $properties['selection'] );

		// Build basic fields.
		foreach ( $feed_meta as $setting_name => $setting_value ) {

			$field_name = $this->get_hubspot_contact_property_name( $setting_name );
			if ( empty( $setting_value ) || ! $field_name ) {
				continue;
			}

			$setting_field = $this->find_setting_field( $setting_name, $settings_fields );
			if ( ! $setting_field ) {
				continue;
			}

			$fields[] = array(
				'name'      => $field_name,
				'label'     => $setting_field['label'],
				'type'      => $setting_field['_hs_type'],
				'fieldType' => 'text',
			);
		}


		// Adding Contact Owner field.
		$fields[] = array(
			'name'      => 'hubspot_owner_id',
			'label'     => 'Contact Owner',
			'type'      => 'enumeration',
			'fieldType' => 'hidden',
		);

		// Build additional fields.
		if ( is_array( $feed_meta['additional_fields'] ) ) {
			foreach ( $feed_meta['additional_fields'] as $setting ) {
				if ( rgar( $setting, 'custom_key' ) !== '' ) {
					$setting['key'] = $setting['custom_key'];
				}

				$setting_field = $this->find_setting_field( $setting['key'], $settings_fields );
				if ( ! $setting_field ) {
					continue;
				}

				$field_name = $this->get_hubspot_contact_property_name( $setting_field['name'] );
				if ( ! $field_name ) {
					continue;
				}

				// Ensures File upload fields aren't named the same as the contact property.
				// Gets around strange HubSpot behavior that causes file URL to be wiped out when form field and contact property have the same label.
				$field_label = $setting_field['_hs_field_type'] == 'file' ? $setting_field['label'] . ' - ' . uniqid() : $setting_field['label'];

				$fields[] = array(
					'name'      => $field_name,
					'label'     => $field_label,
					'type'      => $setting_field['_hs_type'],
					'fieldType' => $setting_field['_hs_field_type'],
				);
			}
		}

		$form_name = $feed_meta['_hs_form'] . $this->get_hubspot_formname_warning();
		$hs_form = array(
			'name'            => $form_name,
			'formFieldGroups' => array(
				array(
					'fields' => $fields,
				),
			),
		);

		// Only available when run from the form settings area.
		$form = $this->get_current_form();

		if ( empty( $form ) ) {
			$form = GFAPI::get_form( $form_id );
		}

		/**
		 * Allows the HubSpot form object to be filtered before saving the feed.
		 *
		 * @since 1.0
		 *
		 * @param array $hs_form   The HubSpot form object to be filtered.
		 * @param array $feed_meta The current feed settings object.
		 * @param array $form      The current Gravity Form Object.
		 */
		return gf_apply_filters( array( 'gform_hubspot_form_object_pre_save_feed', $form_id ), $hs_form, $feed_meta, $form );

	}

	/**
	 * Generates the form submission object to be sent to HubSpot when the form is submitted.
	 *
	 * @since 1.0
	 *
	 * @param array $feed Current Feed Object.
	 * @param array $entry Current Entry Object.
	 * @param array $form Current Form Object.
	 *
	 * @return array Returns a submission object in the format accepted by HubSpot's Submit Form endpoint.
	 */
	public function generate_form_submission_object( $feed, $entry, $form ) {

		$fields          = array();
		$properties      = $this->get_hubspot_contact_properties();
		$settings_fields = array_merge( $properties['basic'], $properties['additional'] );

		$enum_properties = $this->get_enumeration_properties( $form );

		// Build basic fields.
		foreach ( $feed['meta'] as $key => $field_id ) {

			$property_name   = $this->get_hubspot_contact_property_name( $key );
			$is_field_mapped = ! empty( $field_id ) && $property_name;

			if ( ! $is_field_mapped ) {
				continue;
			}

			$fields[] = array(
				'name'  => $property_name,
				'value' => isset( $enum_properties[ $property_name ] ) ? trim( $field_id ) : $this->get_field_value( $form, $entry, $field_id ),
			);
		}

		$owner_id = $this->get_contact_owner( $feed, $entry, $form );
		if ( $owner_id ) {
			$fields[] = array(
				'name'  => 'hubspot_owner_id',
				'value' => $owner_id,
			);
		}

		// Build additional fields.
		if ( is_array( $feed['meta']['additional_fields'] ) ) {
			foreach ( $feed['meta']['additional_fields'] as $setting ) {
				if ( rgar( $setting, 'custom_key' ) !== '' ) {
					$setting['key'] = $setting['custom_key'];
				}

				$setting_field = $this->find_setting_field( $setting['key'], $settings_fields );
				if ( ! $setting_field ) {
					continue;
				}

				$property_name   = $this->get_hubspot_contact_property_name( $setting_field['name'] );
				$field_id        = $setting['value'];
				$is_field_mapped = ! empty( $field_id ) && $property_name;
				if ( ! $is_field_mapped ) {
					continue;
				}

				$fields[] = array(
					'name'  => $property_name,
					'value' => $this->get_field_value( $form, $entry, $field_id ),
				);
			}
		}

		$context = array(
			'pageUri'  => GFFormsModel::get_current_page_url(),
			'pageName' => $form['title'],
		);

		$hutk = rgar( $_COOKIE, 'hubspotutk' );
		if ( ! empty( $hutk ) ) {
			$context['hutk'] = $hutk;
		}

		// Pass entry IP to HubSpot unless personal data settings for a form are set to not save the submitter's IP address.
		if ( rgars( $form, 'personalData/preventIP' ) !== true ) {
			$context['ipAddress'] = $entry['ip'];
		}

		$submission_data = array(
			'fields'  => $fields,
			'context' => $context,
		);

		/**
		 * Allows the HubSpot submission data to be filtered before being sent to HubSpot
		 *
		 * @since 1.0
		 *
		 * @param array $submission_data The HubSpot submission data to be filtered.
		 * @param array $feed The current feed settings object.
		 * @param array $entry The current Entry Object.
		 * @param array $form The current Form Object.
		 */
		return gf_apply_filters( array( 'gform_hubspot_submission_data', $form['id'] ), $submission_data, $feed, $entry, $form );

	}

	/**
	 * Returns the value of the selected field. Overrides the parent function to include Address Line 2 with Street Address
	 *
	 * @param array $form Current Form Object
	 * @param array $entry Current Entry Object
	 * @param string $field_id Current Field ID
	 *
	 * @return string The value of the current field specified in $field_id
	 */
	public function get_field_value( $form, $entry, $field_id ) {

		$field_value = parent::get_field_value( $form, $entry, $field_id );
		$field = GFFormsModel::get_field( $form, $field_id );

		//Appending Line 2 to Street Address
		if ( rgobj( $field, 'type' ) == 'address' && (string) $field_id == (string) $field->id . '.1' ) {
			$field_value .= ' ' . parent::get_field_value( $form, $entry, $field['id'] . '.2' );
		}
		return $field_value;
	}

	/***
	 * Searches for a field named or labeled $name in the list of settings fields specified by the $settings_fields array. Returns the field if it finds it, or false if not.
	 *
	 * @since 1.0
	 *
	 * @param string $name Name of the field to look for.
	 * @param array  $settings_fields Array of all settings fields.
	 *
	 * @return array|bool Returns the field whose name matches the specified $name variable
	 */
	public function find_setting_field( $name, $settings_fields ) {

		foreach ( $settings_fields as $field ) {
			if ( $field['name'] === $name || $field['label'] === $name ) {
				return $field;
			}
		}
		return false;
	}

	/**
	 * Gets a list of HubSpot owners
	 *
	 * @since 1.0
	 *
	 * @return array|null Return a list of available Contact Owners configured in HubSpot
	 */
	public function get_hubspot_owners() {
		if ( ! $this->initialize_api() || rgget( 'subview' ) !== $this->_slug || rgget( 'fid' ) === '' ) {
			return null;
		}

		global $_owner_choices;

		if ( ! $_owner_choices ) {

			$owners = $this->api->get_owners();

			if ( is_wp_error( $owners ) ) {
				return array(
					array(
						'label' => esc_html__( 'Error retrieving HubSpot owners', 'gravityformshubspot' ),
						'value' => '',
					),
				);
			}

			$_owner_choices = array();
			foreach ( $owners as $owner ) {
				if ( ! empty( $owner['firstName'] ) && ! empty( $owner['lastName'] ) ) {
					$owner_label = "{$owner['firstName']} {$owner['lastName']}";
				} else {
					$owner_label = $owner['email'];
				}

				$_owner_choices[] = array(
					'label' => $owner_label,
					'value' => $owner['ownerId'],
				);
			}
		}

		return $_owner_choices;
	}

	/**
	 * Gets a list of contact properties, split into two arrays. "basic" contains basic contact properties and "additional" contains all others.
	 *
	 * @since 1.0
	 *
	 * @return array Returns an associative array with two keys. The "basic" key contains an array with basic contact properties. The "additional" key contains an array with all other contact properties.
	 */
	public function get_hubspot_contact_properties() {
		global $_contact_properties;

		// Cached version.
		if ( $_contact_properties ) {
			return $_contact_properties;
		}

		$property_groups = $this->api->get_contact_properties();

		$basic_field_names = array( 'firstname', 'lastname', 'email' );

		//Only the following supported property types will be supported for mapping.
		$supported_property_types = array( 'string', 'number', 'date' );

		$enum_properties = $this->get_enumeration_properties();

		//Property names that are not supported for mapping to be ignored.
		$ignore_property_names = array( 'hubspot_owner_id' );

		$basic_fields      = array();
		$additional_fields = array();
		$selection_fields = array();

		$empty_choice = array(
			'label' => esc_html__( 'Select a Contact Property', 'gravityformshubspot' ),
			'value' => '',
		);
		$groups = array( $empty_choice );

		$labels = array();

		foreach ( $property_groups as $property_group ) {
			$group = array( 'label' => $property_group['displayName'], 'choices' => array() );

			foreach ( $property_group['properties'] as $property ) {

				$field = array(
					'type'     => 'field_select',
					'class'    => 'medium',
					'label'    => $property['label'],
					'name'     => '_hs_customer_' . $property['name'],
					'value'    => '_hs_customer_' . $property['name'],
					'_hs_type' => $property['type'],
					'_hs_field_type' => $property['fieldType'],
					'required' => $property['name'] == 'email',
				);
				$labels[ $property['label'] ][] = $property;

				$supported_in_additional_fields = ! in_array( $property['name'], $ignore_property_names ) && in_array( $property['type'], $supported_property_types );

				if ( in_array( $property['name'], $basic_field_names, true ) ) {

					$basic_fields[] = $field;

				} elseif ( $supported_in_additional_fields && $property['readOnlyValue'] === false ) {

					$additional_fields[] = $field;
					$group['choices'][] = $field;

				} elseif ( isset( $enum_properties[ $property['name'] ] ) ) {
					$prop = $enum_properties[ $property['name'] ];
					$field['default_value'] = rgar( $prop, 'default_value' );
					$field['tooltip'] = rgar( $prop, 'tooltip' );
					$field['choices'] = $prop['allows_blank'] ? array( array( 'value' => '', 'label' => esc_html__( 'Select an Option', 'gravityformshubspot' ) ), array( 'value' => ' ', 'label' => '' ) ) : array();
					$field['choices'] = array_merge( $field['choices'] , $property['options'] );

					$selection_fields[] = $field;
				}
			}

			if ( ! empty( $group['choices'] ) ) {
				usort( $group['choices'], array( $this, 'sort_properties' ) );
				$groups[] = $group;
			}
		}

		ksort( $labels );
		usort( $basic_fields, array( $this, 'sort_properties' ) );
		usort( $additional_fields, array( $this, 'sort_properties' ) );

		$_contact_properties = array( 'basic' => $basic_fields, 'additional' => $additional_fields, 'selection' => $selection_fields, 'grouped' => $groups );

		return $_contact_properties;
	}

	/**
	 * Returns an array of enumeration field names to be displayed for mapping
	 *
	 * @since 1.0
	 *
	 * @param array $form The current Form Object
	 *
	 * @return array Returns an array of enumeration fields to be mapped
	 */
	public function get_enumeration_properties( $form = null ) {

		if ( ! $form ) {
			$form = $this->get_current_form();
		}

		/**
		 * Allows the list of selection properties settings to be changed dynamically. Useful when drop down or radio button custom fields are added in HubSpot and there is a need specify one
		 * of the options when creating the contact
		 */
		return apply_filters( 'gform_hubspot_custom_settings',
			array(
				'hs_lead_status' => array( 'allows_blank' => true, 'tooltip' => esc_html__( '<h6>Lead Status</h6>Select the lead status value the newly added contact should be set to.', 'gravityformshubspot' ) ),
				'lifecyclestage' => array( 'allows_blank' => false, 'default_value' => 'lead', 'tooltip' => esc_html__( '<h6>Lifecycle Stage</h6>Select the lifecycle stage value the newly added contact should be set to.', 'gravityformshubspot' ) ),
			),
			$form
		);
	}
	/**
	 * Process the HubSpot feed.
	 *
	 * @since  1.0
	 *
	 * @param  array $feed  Feed object.
	 * @param  array $entry Entry object.
	 * @param  array $form  Form object.
	 */
	public function process_feed( $feed, $entry, $form ) {
		// If API instance is not initialized, exit.
		if ( ! $this->initialize_api() ) {

			// Log that we cannot process the feed.
			$this->add_feed_error( esc_html__( 'Feed was not processed because API was not initialized.', 'gravityformshubspot' ), $feed, $entry, $form );

			return;
		}

		// Create HubSpot submission object.
		$submission_data = $this->generate_form_submission_object( $feed, $entry, $form );

		$response = $this->api->submit_form( $feed['meta']['_hs_portal_id'], $feed['meta']['_hs_form_guid'], $submission_data );

		if ( is_wp_error( $response ) ) {
			$this->add_feed_error( sprintf( esc_html__( 'There was an error when creating the contact in HubSpot. %s', 'gravityformshubspot' ), $response->get_error_message() ), $feed, $entry, $form );
			$this->log_error( __METHOD__ . '(): Unable to create the contact; error data: ' . print_r( $response->get_error_data(), true ) );
		}
	}

	/**
	 * Given a settings key, converts it into a Contact Property Name (if applicable). If specified settings key is not a Contact Property, returns false
	 *
	 * @since 1.0
	 *
	 * @param string $settings_key Settings key to be transformed into a Contact Property Name.
	 *
	 * @return bool|mixed Returns the proper HubSpot Contact Property name based on the specified settings key. If the specified settings key is not a Contact Property, return false.
	 */
	public function get_hubspot_contact_property_name( $settings_key ) {
		if ( strpos( $settings_key, '_hs_customer_' ) === 0 ) {
			return str_replace( '_hs_customer_', '', $settings_key );
		}
		return false;
	}

	/**
	 * Used for usort() function to sort customer properties.
	 *
	 * @since 1.0
	 *
	 * @param array $a Array.
	 * @param array $b Array.
	 *
	 * @return int
	 */
	public function sort_properties( $a, $b ) {
		return strcmp( $a['label'], $b['label'] );
	}

	/**
	 * Evaluates who the Contact Owner is supposed to be (based on feed settings), and return the owner id.
	 *
	 * @since 1.0
	 *
	 * @param array $feed  Current Feed Object
	 * @param array $entry Current Entry Object
	 * @param array $form  Current Form Object
	 *
	 * @return false|int Returns the Contact Owner's ID if one is supposed to be assigned to the contact. Otherwise returns false.
	 */
	public function get_contact_owner( $feed, $entry, $form ) {

		$owner_id = false;

		// Set contact owner.
		if ( rgar( $feed['meta'], 'contact_owner' ) === 'select' && rgar( $feed['meta'], 'contact_owner_select' ) !== '' ) {

			$owner_id = rgar( $feed['meta'], 'contact_owner_select' );

		} elseif ( rgar( $feed['meta'], 'contact_owner' ) === 'conditional' && ! rgar( $feed['meta'], 'conditions' ) !== '' ) {

			$conditions      = rgar( $feed['meta'], 'conditions' );
			$entry_meta_keys = array_keys( GFFormsModel::get_entry_meta( $form['id'] ) );
			foreach ( $conditions as $rule ) {
				if ( in_array( $rule['fieldId'], $entry_meta_keys ) ) {

					$is_value_match = GFFormsModel::is_value_match( rgar( $entry, $rule['fieldId'] ), $rule['value'], $rule['operator'], null, $rule, $form );

				} else {

					$source_field   = GFFormsModel::get_field( $form, $rule['fieldId'] );
					$field_value    = empty( $entry ) ? GFFormsModel::get_field_value( $source_field, array() ) : GFFormsModel::get_lead_field_value( $entry, $source_field );
					$is_value_match = GFFormsModel::is_value_match( $field_value, $rule['value'], $rule['operator'], $source_field, $rule, $form );
				}

				if ( isset( $is_value_match ) && $is_value_match ) {
					$owner_id = rgar( $rule, 'owner' );

					break;
				}
			}
		}
		return $owner_id;
	}

	/**
	 * Returns the warning string to be added to the HubSpot form names.
	 *
	 * @since 1.0
	 *
	 * @return string
	 */
	public function get_hubspot_formname_warning() {
		return ' ( ' . esc_html__( 'Do not delete or edit', 'gravityformshubspot' ) . ' )';
	}

	/**
	 * Returns the HubSpot Form name without the warning appended to it.
	 *
	 * @since 1.0
	 *
	 * @param string $form_name The form name to be cleaned
	 *
	 * @return string
	 */
	public function get_hubspot_formname_without_warning( $form_name ) {

		return str_replace( $this->get_hubspot_formname_warning(), '', $form_name );

	}


	/**
	 * Add tracking JS snippet to footer if there are any Hubspot feeds.
	 *
	 * @since 1.0
	 */
	public function action_wp_footer() {

		$add_tracking = true;

		/**
		 * Allows the tracking script to be removed.
		 *
		 * @since 1.0
		 *
		 * @param true $add_tracking Whether to output the tracking script.
		 */
		$add_tracking = apply_filters( 'gform_hubspot_output_tracking_script', $add_tracking );

		if ( ! $add_tracking ) {
			return;
		}

		$feeds = $this->get_feeds();
		if ( empty( $feeds ) ) {
			return;
		}

		$portal_id = rgars( $feeds, '0/meta/_hs_portal_id' );

		if ( $portal_id && strlen( $portal_id ) > 0 ) {
			if ( ! is_admin() ) {
				?>
<!-- Start of Async HubSpot Analytics Code -->
<script type="text/javascript">
(function(d,s,i,r) {
if (d.getElementById(i)){return;}
var n=d.createElement(s),e=d.getElementsByTagName(s)[0];
n.id=i;n.src='//js.hs-analytics.net/analytics/'+(Math.ceil(new Date()/r)*r)+'/<?php echo $portal_id; ?>.js';
e.parentNode.insertBefore(n, e);
})(document,"script","hs-analytics",300000);
</script>
<!-- End of Async HubSpot Analytics Code -->
<?php
			}
		}
	}
}
