<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCWR_REST_Reports_Controller extends WC_REST_Controller {

	protected $namespace = 'wc/wooreports1';

	protected $rest_base = 'reports';

	public function register_routes() {
		register_rest_route( $this->namespace, '/' . $this->rest_base, array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				// 'args'                => $this->get_collection_params(),
			),
			// 'schema' => array( $this, 'get_public_item_schema' ),
		) );
	}

	public function get_items_permissions_check( $request ) {
		$params = get_option( 'woocommerce_wooreports_settings' );
		if ( ! wc_rest_check_manager_permissions( 'reports', 'read' ) && $params['security_enabled'] == 'yes' ) {
			return new WP_Error( 'woocommerce_rest_cannot_view', __( 'Sorry, you cannot list resources.', 'wooreports' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	public function get_items( $request ) {
		$data    = array();
		$reports = array(
			array(
				'slug'        => 'sales-by-each-product',
				'description' => __( 'List of sales by each product.', 'wooreports' ),
			),
			array(
				'slug'        => 'sales-by-each-order',
				'description' => __( 'List of sales and taxes by each order.', 'wooreports' ),
			),
			array(
				'slug'        => 'stock-at-sales-value',
				'description' => __( 'List of products in stock and stock value.', 'wooreports' ),
			),
		);

		foreach ( $reports as $report ) {
			$item   = $this->prepare_item_for_response( (object) $report, $request );
			$data[] = $this->prepare_response_for_collection( $item );
		}

		return rest_ensure_response( $data );
	}

	public function prepare_item_for_response( $report, $request ) {
		$data = array(
			'slug'        => $report->slug,
			'description' => $report->description,
		);

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data = $this->add_additional_fields_to_object( $data, $request );
		$data = $this->filter_response_by_context( $data, $context );

		// Wrap the data in a response object.
		$response = rest_ensure_response( $data );
		$response->add_links( array(
			'self' => array(
				'href' => rest_url( sprintf( '/%s/%s/%s', $this->namespace, $this->rest_base, $report->slug ) ),
			),
			'collection' => array(
				'href' => rest_url( sprintf( '%s/%s', $this->namespace, $this->rest_base ) ),
			),
		) );

		return apply_filters( 'wooreports_rest_prepare_reports', $response, $report, $request );
	}
}