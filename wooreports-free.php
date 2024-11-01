<?php

/*
Plugin Name: WooReports API
Plugin URI: https://woo.report/
Description: Enhance WooCommerce reporting and analytical capabilities with WooReports!
Version: 2.0.2
Author: Lucian Capdefier (luciancapdefier)
Author URI: https://woo.report/about-us/#lucian.capdefier
Text Domain: wooreports
Domain Path: /languages
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

add_action( 'plugins_loaded', 'wcwr_init' );

function wcwr_init() {
	if ( class_exists( 'WC_Integration' ) ) {
		include_once( plugin_dir_path( __FILE__ ) . 'includes/class-wcwr-wc-integration.php' );

		add_filter( 'woocommerce_integrations', 'wcwr_add_integration' );
		add_action( 'rest_api_init', 'wcwr_register_api_hooks' );

		add_action( 'woocommerce_product_options_general_product_data', 'wcwr_add_product_general_fields' );
		add_action( 'woocommerce_process_product_meta', 'wcwr_add_product_fields_save' );
		add_action( 'woocommerce_product_options_inventory_product_data', 'wcwr_add_product_inventory_fields' );
		add_action( 'woocommerce_product_after_variable_attributes', 'wcwr_add_variable_product_general_fields', 10, 3 );
		add_action( 'woocommerce_save_product_variation', 'wcwr_add_variable_product_general_fields_save', 10, 2 );

		add_filter( 'woocommerce_admin_reports', 'wcwr_filter_woocommerce_admin_reports', 10, 1 );
	} 
}

function wcwr_register_api_hooks() {
	$plugin_dir_path = plugin_dir_path( __FILE__ );
	define('WOOREPORTS_API_VERSION', 2.02);

	include_once( $plugin_dir_path . 'includes/reports/class-wcwr-rest-meta.php' );
	include_once( $plugin_dir_path . 'includes/reports/class-wcwr-rest-reports-controller.php' );
	include_once( $plugin_dir_path . 'includes/reports/class-wcwr-rest-sales-by-each-product.php' );
	include_once( $plugin_dir_path . 'includes/reports/class-wcwr-rest-sales-by-each-order.php' );
	include_once( $plugin_dir_path . 'includes/reports/class-wcwr-rest-stock-at-sales-value.php' );
	include_once( $plugin_dir_path . 'includes/reports/class-wcwr-rest-customers-behavior.php' );
	include_once( $plugin_dir_path . 'includes/reports/class-wcwr-rest-products-affinity.php' );

	$controllers = array(
		'WCWR_REST_Meta',
		'WCWR_REST_Reports_Controller',
		'WCWR_REST_Sales_By_Each_Product',
		'WCWR_REST_Sales_By_Each_Order',
		'WCWR_REST_Stock_At_Sales_Value',
		'WCWR_REST_Customers_Behavior',
		'WCWR_REST_Products_Affinity'
	);

	foreach ( $controllers as $controller ) {
		$controller = new $controller();
		$controller->register_routes();
	}
}

function wcwr_add_integration( $integrations ) {
	$integrations[] = 'WCWR_WC_Integration';
	return $integrations;
}

// begin adding product item, cost
function wcwr_add_product_general_fields() {	
	if ( !is_plugin_active( 'woocommerce-cost-of-goods/woocommerce-cost-of-goods.php' ) ) {
		global $woocommerce, $post;
		echo '<div class="options_group">';
		woocommerce_wp_text_input( 
			array( 
				'id'                => '_wc_cog_cost', 
				'label'             => __( 'Cost' . ' (' . get_woocommerce_currency_symbol() . ')', 'wooreports' ), 
				'placeholder'       => '', 
				'description'       => __( 'Enter the cost of the product (typically, the average weighted cost).', 'wooreports' ),
				'type'              => 'price', 
				'desc_tip'			=> 'true',
				'custom_attributes' => array(
						'step' 	=> 'any',
						'min'	=> '0'
					) 
			)
		);

		echo '</div>';
	}
}

function wcwr_add_product_inventory_fields() {
	global $woocommerce, $post;

	echo '<div class="options_group">';
	woocommerce_wp_text_input( 
		array( 
			'id'                => '_wcwr_stock_move_descr', 
			'label'             => __( 'Movement description', 'wooreports' ), 
			'placeholder'       => __( 'New shipment or damaged products or...', 'wooreports' ),
			'description'       => __( 'Enter a description in case you modify stock quantity.', 'wooreports' ),
			'desc_tip'			=> 'true',
			'custom_attributes' => array(
					'step' 	=> 'any',
					'min'	=> '0'
				) 
		)
	);

	echo '</div>';
}

function wcwr_add_product_fields_save( $post_id ){
	global $wpdb;
	$product = wc_get_product( $post_id );

	if ( !is_plugin_active( 'woocommerce-cost-of-goods/woocommerce-cost-of-goods.php' ) ) {
		$cost = $_POST['_wc_cog_cost'];
		if( !empty( $cost ) ) {
			update_post_meta( $post_id, '_wc_cog_cost', esc_attr( $cost ) );
		}
	}

	$stock = $_POST['_stock'];
	$stock_move_descr = $_POST['_wcwr_stock_move_descr'];
	if( !empty( $stock ) && $stock != $product->get_stock_quantity() ) {
		$wpdb->insert( 
			$wpdb->prefix . 'wooreports_stock_movement', 
			array( 
				'product_id' 	=> $post_id, 
				'descr' 		=> esc_attr( $stock_move_descr ), 
				'stock' 		=> $stock,
				'regular_price' => $_POST['_regular_price'],
			    'sale_price' 				=> $_POST['_sale_price'],
			    'sale_price_dates_from' 	=> $_POST['_sale_price_dates_from'],
			    'sale_price_dates_to' 		=> $_POST['_sale_price_dates_to'],
			    'cog_cost' 					=> $_POST['_wc_cog_cost'],
			) 
		);
	}
}

function wcwr_add_variation_product_general_fields( $loop, $variation_data, $variation ) {
	if ( !is_plugin_active( 'woocommerce-cost-of-goods/woocommerce-cost-of-goods.php' ) ) {
		woocommerce_wp_text_input( 
			array( 
				'id'          => '_wc_cog_cost[' . $variation->ID . ']', 
				'label'       => __( 'Cost' . ' (' . get_woocommerce_currency_symbol() . ')', 'wooreports' ), 
				'desc_tip'    => 'true',
				'description' => __( 'Enter the cost of the product (typically, the average weighted cost).', 'wooreports' ),
				'value'       => get_post_meta( $variation->ID, '_wc_cog_cost', true ),
				'custom_attributes' => array(
						'step' 	=> 'any',
						'min'	=> '0'
					) 
			)
		);
	}
}

function wcwr_add_variation_product_general_fields_save( $post_id ) {
	if ( !is_plugin_active( 'woocommerce-cost-of-goods/woocommerce-cost-of-goods.php' ) ) {
		$woocommerce_number_field = $_POST['_wc_cog_cost'][ $post_id ];
		if( !empty( $woocommerce_number_field ) ) {
			update_post_meta( $post_id, '_wc_cog_cost', esc_attr( $woocommerce_number_field ) );
		}
	}
}
// end adding product item, cost

function wcwr_filter_woocommerce_admin_reports( $reports ) {
	$reports['wooreports'] = array(
		'title' => __( 'WooReports', 'wooreports' ),
		'reports' => array(
			'' => array(
				'title'       => __( '', 'wooreports' ),
				'description' => '',
				'hide_title'  => true,
				'callback'    => 'wcwr_get_report_descr',
			),
			// in case we want to list down all available reports... for some reason
			// 'y' => array(
			// 	'title'       => __( 'y', 'wooreports' ),
			// 	'description' => '',
			// 	'hide_title'  => true,
			// 	'callback'    => 'wcwr_get_report_descr',
			// ),
		),
	);

	return $reports;
}

function wcwr_get_report_descr() {
	echo 'For accessing the reports go to WooReports Dashboard, at <a target="_blank" href="https://woo.report/dashboard/">https://woo.report/dashboard/</a>.';
}