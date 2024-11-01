<?php

class WCWR_WC_Integration extends WC_Integration {

	public function __construct() {
		global $woocommerce;

		$this->id                 = 'wooreports';
		$this->method_title       = __( 'WooReports', 'wooreports' );
		$this->method_description = __( 'Enhanced reporting & analytics capabilities for WooCommerce.', 'wooreports' );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables.
		$this->user_email = $this->get_option( 'user_email' );
		$this->site_url = $this->get_option( 'site_url' );
		$this->security_enabled = $this->get_option( 'security_enabled' );
		
		// Actions.
		add_action( 'woocommerce_update_options_integration_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_update_options_integration_' . $this->id, array( $this, 'process_integration_wcwrdb' ) );
		
		// Filters.
		add_filter( 'woocommerce_settings_api_sanitized_fields_' . $this->id, array( $this, 'sanitize_settings' ) );
	}

	public function init_form_fields() {
		global $wpdb;

		$this->form_fields = array(
			'title'                => array(
				'title'       => __( 'Setup', 'wooreports' ),
				'type'        => 'title',
				'description' => __( 'WooReports requires a one-time setup between your store and WooReports Dashboard. Continuing this registration process implies you are agreeing with the terms and conditions specified at the following link, <a target="_blank" href="https://woo.report/terms-and-conditions/">https://woo.report/terms-and-conditions/</a>.', 'wooreports' ),
			),
			'user_email'        => array(
				'title'       => __( 'Email address', 'wooreports' ),
				'type'        => 'text',
				'description' => __( 'Email address used to create username for WooReports Dashboard. If user already exists, your store will be added as another store.', 'wooreports' ),
				'desc_tip'    => true,
				'default'     => wp_get_current_user()->user_email,
			),
			'site_url'        => array(
				'title'       => __( 'Store URL', 'wooreports' ),
				'type'        => 'text',
				'description' => __( 'WooCommerce store URL.', 'wooreports' ),
				'desc_tip'    => true,
				'default'     => site_url(),
			),
			'security_enabled'     => array(
				'title'       => __( 'Enable API security', 'wooreports' ),
				'type'        => 'checkbox',
				'label'       => __( '', 'wooreports' ),
				'description' => __( 'Must be set to enabled, except for the cases when you want to access the WooReports API endpoints without security.', 'wooreports' ),
				'desc_tip'    => true,
				'default'     => get_option( 'security_enabled' ) ? get_option( 'security_enabled' ) : 'yes'
			),
		);
	}

	public function sanitize_settings( $settings ) {
		return $settings;
	}

	public function process_integration_wcwrdb() {
		update_option ( 'woocommerce_api_enabled', 'yes' );
		wp_redirect( 'https://woo.report/account/?wcwr_user_email=' . $this->user_email . '&wcwr_site_url=' . $this->site_url );
		exit;
	}

}
