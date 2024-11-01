<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCWR_REST_Sales_By_Each_Product extends WC_REST_Controller {

	protected $namespace = 'wc/wooreports1';

	protected $rest_base = 'reports/sales-by-each-product';

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
				  SELECT orders.ID                                  AS order_id,
				         DATE_FORMAT(orders.post_date, '%Y-%m-%d %T') AS order_datetime,
				         order_item_meta.product_id,
				         order_item_meta.variation_id,
				         products.post_title                        AS curr_product_name,
				         order_items.order_item_name
				            AS transaction_product_name,
				         SUM(if(orders.post_status IN ('wc-completed', 'wc-processing') AND orders.post_type IN ('". implode( "','", wc_get_order_types( 'reports' ) ) . "'),
				                order_item_meta.line_total,
				                0))
				            AS amount_value,
				         SUM(if(orders.post_type     IN ('" . implode( "','", wc_get_order_types( 'order-count' ) ) . "')
				                AND orders.post_status   IN ('" . implode( "','", array_keys( wc_get_order_statuses() ) )  . "'),
				                order_item_meta.qty, 0))      AS count_value,
				         GROUP_CONCAT(
				            if(tt.taxonomy = 'product_cat', t.name, NULL) SEPARATOR ', ')
				            AS product_cat,
				         GROUP_CONCAT(
				            if(tt.taxonomy = 'product_tag', t.name, NULL) SEPARATOR ', ')
				            AS product_tag
				    FROM $wpdb->posts AS orders
				         JOIN " . $wpdb->prefix . "woocommerce_order_items AS order_items
				            ON orders.ID = order_items.order_id
				         JOIN
				         (  SELECT order_item_id,
				                   SUM(if(meta_key = '_qty', CAST(meta_value AS UNSIGNED), 0))
				                      AS qty,
				                   SUM(if(meta_key = '_line_total', meta_value, 0)) AS line_total,
				                   SUM(
				                      if(meta_key = '_product_id',
				                         CAST(meta_value AS UNSIGNED),
				                         0))
				                      AS product_id,
				                   SUM(
				                      if(meta_key = '_variation_id',
				                         CAST(meta_value AS UNSIGNED),
				                         0))
				                      AS variation_id
				              FROM " . $wpdb->prefix . "woocommerce_order_itemmeta
				             WHERE meta_key IN ('_qty',
				                                '_line_total',
				                                '_product_id',
				                                '_variation_id')
				          GROUP BY order_item_id) AS order_item_meta
				            ON order_item_meta.order_item_id = order_items.order_item_id
				         JOIN $wpdb->posts AS products ON order_item_meta.product_id = products.ID
				         JOIN $wpdb->term_relationships tr ON products.ID = tr.object_id
				         JOIN $wpdb->term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
				         JOIN $wpdb->terms t ON tt.term_id = t.term_id
				   WHERE     CAST(orders.post_date AS DATE) " . ( empty( $filter['date_min'] ) ? "is TRUE" : ">= '". $filter['date_min'] . "'" ) . "
				         AND CAST(orders.post_date AS DATE) " . ( empty( $filter['date_max'] ) ? "is TRUE" : "<= '". $filter['date_max'] . "'" ) . "
				         AND order_items.order_item_type = 'line_item'
				         AND products.post_type = 'product'
				         # AND CAST(products.post_date AS DATE) " . ( empty( $filter['date_min'] ) ? "is TRUE" : ">= '". $filter['date_min'] . "'" ) . "
				         # AND CAST(products.post_date AS DATE) " . ( empty( $filter['date_max'] ) ? "is TRUE" : "<= '". $filter['date_max'] . "'" ) . "
				GROUP BY orders.ID,
				         orders.post_date,
				         order_item_meta.product_id,
				         order_item_meta.variation_id,
				         products.post_title,
         				 products.ID,
				         order_items.order_item_name
				ORDER BY order_item_meta.product_id" . $limit;

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

		return apply_filters( 'wooreports_rest_prepare_report_sales_by_each_product', $response, (object) $sales_data, $request );
	}
}