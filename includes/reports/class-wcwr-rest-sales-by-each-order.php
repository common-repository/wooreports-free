<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCWR_REST_Sales_By_Each_Order extends WC_REST_Controller {

	protected $namespace = 'wc/wooreports1';

	protected $rest_base = 'reports/sales-by-each-order';

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
			return new WP_Error( 'woocommerce_rest_cannot_view', __( 'Sorry, you cannot list resources.', 'woocommerce' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	public function get_items( $request ) {
		$data   = array();
		$item   = $this->prepare_item_for_response( null, $request );
		$data[] = $this->prepare_response_for_collection( $item );

		return rest_ensure_response( $data );
	}

	public function prepare_item_for_response( $_, $request ) {
		global $wpdb;
		
		$filter = array(
			'date_min' => $request['date_min'],
			'date_max' => $request['date_max'],
			'page' => $request['page'],
			'per_page' => $request['per_page'],
		);

		$limit = (!empty($filter['page']) && !empty($filter['per_page'])) ? " LIMIT " . ( $filter['page'] - 1 ) * $filter['per_page'] . ", " . $filter['per_page'] : "";

		$query = "
				  SELECT orders.ID                                          AS order_id,
				         orders.post_type                                   AS order_type,
				         orders.post_status                                 AS order_status,
				         DATE_FORMAT(orders.post_date, '%Y-%m-%d %T')       AS order_datetime,
				         SUM(if(meta_key = '_order_shipping', meta_value, 0)) AS order_shipping,
				         SUM(if(meta_key = '_order_shipping_tax', meta_value, 0))
				            AS order_shipping_tax,
				         SUM(if(meta_key = '_order_tax', meta_value, 0))    AS order_tax,
				         SUM(if(meta_key = '_cart_discount', meta_value, 0)) AS cart_discount,
				         SUM(if(meta_key = '_cart_discount_tax', meta_value, 0))
				            AS cart_discount_tax,
				         SUM(if(meta_key = '_order_total', meta_value, 0))  AS order_total,
				         GROUP_CONCAT(
				            if(meta_key = '_order_currency', if(meta_value = '', null, meta_value), null) SEPARATOR '')
				            AS order_currency,
				         COALESCE(GROUP_CONCAT(
				            if(meta_key = '_payment_method', if(meta_value = '', null, meta_value), null) SEPARATOR ''), 'n/a')
				            AS payment_method,
				         COALESCE(GROUP_CONCAT(
				            if(meta_key = '_payment_method_title', if(meta_value = '', null, meta_value), null) SEPARATOR ''), 'Not available')
				            AS payment_method_title
				    FROM $wpdb->posts AS orders
				         JOIN $wpdb->postmeta AS order_meta ON orders.ID = order_meta.post_id
				   WHERE     CAST(orders.post_date AS DATE) " . ( empty( $filter['date_min'] ) ? "is TRUE" : ">= '". $filter['date_min'] . "'" ) . "
				         AND CAST(orders.post_date AS DATE) " . ( empty( $filter['date_max'] ) ? "is TRUE" : "<= '". $filter['date_max'] . "'" ) . "
				         AND meta_key IN ('_order_shipping',
				                          '_order_shipping_tax',
				                          '_order_tax',
				                          '_cart_discount',
				                          '_cart_discount_tax',
				                          '_order_total',
				                          '_order_currency',
				                          '_payment_method',
				                          '_payment_method_title')
				         AND orders.post_type IN ('" . implode( "','", wc_get_order_types( 'reports' ) ) . "')
						 AND orders.post_status IN ('" . implode( "','", array_keys( wc_get_order_statuses() ) ) . "')
				GROUP BY orders.ID, orders.post_date
				ORDER BY orders.ID, orders.post_date" . $limit;
				
		$query_data = $wpdb->get_results( $query );

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $query_data, $request );
		$data    = $this->filter_response_by_context( $data, $context );
		$data    = array(
			'data'	=> $data
			);
		// Wrap the data in a response object.
		$response = rest_ensure_response( $data );
		$response->add_links( array(
			'about' => array(
				'api_version' => WOOREPORTS_API_VERSION,
				'href' => rest_url( sprintf( '%s/reports', $this->namespace ) ),
				'query' => $query,
			),
		) );

		return apply_filters( 'wooreports_rest_prepare_report_sales_by_each_order', $response, (object) $sales_data, $request );
	}

	// public function get_item_schema() {
	// 	$schema = array(
	// 		'$schema'    => 'http://json-schema.org/draft-04/schema#',
	// 		'title'      => 'sales_by_each_order',
	// 		'type'       => 'object',
	// 		'properties' => array(
	// 			'order_id' => array(
	// 				'description' => __( 'Order Id.', 'wooreports' ),
	// 				'type'        => 'string',
	// 				'context'     => array( 'view' ),
	// 				'readonly'    => true,
	// 			),
	// 			'order_datetime' => array(
	// 				'description' => __( 'Date and time of order.', 'wooreports' ),
	// 				'type'        => 'string',
	// 				'context'     => array( 'view' ),
	// 				'readonly'    => true,
	// 			),
	// 			'order_shipping' => array(
	// 				'description' => __( 'Order shipping cost.', 'wooreports' ),
	// 				'type'        => 'decimal',
	// 				'context'     => array( 'view' ),
	// 				'readonly'    => true,
	// 			),
	// 			'order_shipping_tax' => array(
	// 				'description' => __( 'Order shipping tax.', 'wooreports' ),
	// 				'type'        => 'decimal',
	// 				'context'     => array( 'view' ),
	// 				'readonly'    => true,
	// 			),
	// 			'order_tax' => array(
	// 				'description' => __( 'Order tax.', 'wooreports' ),
	// 				'type'        => 'decimal',
	// 				'context'     => array( 'view' ),
	// 				'readonly'    => true,
	// 			),
	// 			'cart_discount' => array(
	// 				'description' => __( 'Cart discount.', 'wooreports' ),
	// 				'type'        => 'decimal',
	// 				'context'     => array( 'view' ),
	// 				'readonly'    => true,
	// 			),
	// 			'cart_discount_tax' => array(
	// 				'description' => __( 'Cart discount tax.', 'wooreports' ),
	// 				'type'        => 'decimal',
	// 				'context'     => array( 'view' ),
	// 				'readonly'    => true,
	// 			),
	// 			'order_total' => array(
	// 				'description' => __( 'Order total.', 'wooreports' ),
	// 				'type'        => 'decimal',
	// 				'context'     => array( 'view' ),
	// 				'readonly'    => true,
	// 			),
	// 			'order_currency' => array(
	// 				'description' => __( 'Order currency.', 'wooreports' ),
	// 				'type'        => 'string',
	// 				'context'     => array( 'view' ),
	// 				'readonly'    => true,
	// 			),
	// 		),
	// 	);

	// 	return $this->add_additional_fields_schema( $schema );
	// }

	// public function get_collection_params() {
	// 	return array(
	// 		'context' => $this->get_context_param( array( 'default' => 'view' ) ),
	// 		'date_min' => array(
	// 			'description'       => sprintf( __( 'Return sales for a specific start date, the date need to be in the %s format.', 'wooreports' ), 'YYYY-MM-DD' ),
	// 			'type'              => 'string',
	// 			'format'            => 'date',
	// 			'validate_callback' => 'wc_rest_validate_reports_request_arg',
	// 			'sanitize_callback' => 'sanitize_text_field',
	// 		),
	// 		'date_max' => array(
	// 			'description'       => sprintf( __( 'Return sales for a specific end date, the date need to be in the %s format.', 'wooreports' ), 'YYYY-MM-DD' ),
	// 			'type'              => 'string',
	// 			'format'            => 'date',
	// 			'validate_callback' => 'wc_rest_validate_reports_request_arg',
	// 			'sanitize_callback' => 'sanitize_text_field',
	// 		),
	// 		'page' => array(
	// 			'description' 		=> __( '.', 'wooreports' ),
	// 			'type'        		=> 'integer',
	// 		),
	// 		'per_page' => array(
	// 			'description' 		=> __( '.', 'wooreports' ),
	// 			'type'        		=> 'integer',
	// 		),
	// 	);
	// }
}