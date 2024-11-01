<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCWR_REST_Meta extends WC_REST_Controller {

	protected $namespace = 'wc/wooreports1';

	protected $rest_base = 'meta';

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
		if ( ! wc_rest_check_manager_permissions( 'reports', 'read' ) ) {
			// return new WP_Error( 'woocommerce_rest_cannot_view', __( 'Sorry, you cannot list resources.', 'wooreports' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	public function get_items( $request ) {
		$data    = array();
		$reports = array(
			array(
				'description' => __( 'WooReports - Enhanced reporting & analytics capabilities for WooCommerce.', 'wooreports' ),
				'api_version' => WOOREPORTS_API_VERSION,
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
			'description' => $report->description,
			'version'        => $report->version,
		);

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data = $this->add_additional_fields_to_object( $data, $request );
		$data = $this->filter_response_by_context( $data, $context );

		// Wrap the data in a response object.
		$response = rest_ensure_response( $data );
		// $response->add_links( array(
		// 	'self' => array(
		// 		'href' => rest_url( sprintf( '/%s/%s/%s', $this->namespace, $this->rest_base, $report->version ) ),
		// 	),
		// 	'collection' => array(
		// 		'href' => rest_url( sprintf( '%s/%s', $this->namespace, $this->rest_base ) ),
		// 	),
		// ) );

		return apply_filters( 'wooreports_rest_prepare_meta', $response, $report, $request );
	}

	// public function get_item_schema() {
	// 	$schema = array(
	// 		'$schema'    => 'http://json-schema.org/draft-04/schema#',
	// 		'title'      => 'report',
	// 		'type'       => 'object',
	// 		'properties' => array(
	// 			'description' => array(
	// 				'description' => __( 'A human-readable description of the resource.', 'wooreports' ),
	// 				'type'        => 'string',
	// 				'context'     => array( 'view' ),
	// 				'readonly'    => true,
	// 			),
	// 			'version' => array(
	// 				'description' => __( 'An numeric identifier for the resource.', 'wooreports' ),
	// 				'type'        => 'string',
	// 				'context'     => array( 'view' ),
	// 				'readonly'    => true,
	// 			),
	// 		),
	// 	);

	// 	return $this->add_additional_fields_schema( $schema );
	// }

	public function get_collection_params() {
		return array(
			'context' => $this->get_context_param( array( 'default' => 'view' ) ),
		);
	}
}