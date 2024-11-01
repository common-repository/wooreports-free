<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCWR_REST_Products_Affinity extends WC_REST_Controller {

	protected $namespace = 'wc/wooreports1';

	protected $rest_base = 'reports/products-affinity';

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

		$inner_query = "
SELECT orders.ID                   AS order_id,
       order_item_meta.product_id,
       order_items.order_item_name AS transaction_product_name
  FROM wp_posts AS orders
       JOIN wp_woocommerce_order_items AS order_items
          ON orders.ID = order_items.order_id
       JOIN
       (  SELECT order_item_id,
                 SUM(
                    if(meta_key = '_product_id',
                       CAST(meta_value AS UNSIGNED),
                       0))
                    AS product_id
            FROM wp_woocommerce_order_itemmeta
           WHERE meta_key IN ('_product_id')
        GROUP BY order_item_id) AS order_item_meta
          ON order_item_meta.order_item_id = order_items.order_item_id
 WHERE CAST(orders.post_date AS DATE) " . ( empty( $filter['date_min'] ) ? "is TRUE" : ">= '". $filter['date_min'] . "'" ) . "
	   AND CAST(orders.post_date AS DATE) " . ( empty( $filter['date_max'] ) ? "is TRUE" : "<= '". $filter['date_max'] . "'" ) . "
       AND order_items.order_item_type = 'line_item'
		";

		$query = "
  SELECT x.product_id             AS x_product_id,
         x.transaction_product_name AS x_transaction_product_name,
         y.product_id             AS y_product_id,
         y.transaction_product_name AS y_transaction_product_name,
         count(DISTINCT x.order_id) AS cnt_dst_order_id
    FROM (" . $inner_query . ") x,
         (" . $inner_query . ") y
   WHERE x.order_id = y.order_id
GROUP BY x.product_id, y.product_id" . $limit;

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

		return apply_filters( 'wooreports_rest_prepare_report_products_affinity', $response, (object) $sales_data, $request );
	}
}