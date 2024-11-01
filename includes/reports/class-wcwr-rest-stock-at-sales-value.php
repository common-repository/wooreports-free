<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCWR_REST_Stock_At_Sales_Value extends WC_REST_Controller {

	protected $namespace = 'wc/wooreports1';

	protected $rest_base = 'reports/stock-at-sales-value';

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
			'page' => $request['page'],
			'per_page' => $request['per_page'],
		);

		$limit = (!empty($filter['page']) && !empty($filter['per_page'])) ? " LIMIT " . ( $filter['page'] - 1 ) * $filter['per_page'] . ", " . $filter['per_page'] : "";

		$query = "
	SELECT 
		 curr_date,
		 meta.product_id,
		 meta.sku,
		 products.post_title AS curr_product_name,
         meta.stock_status,
         meta.stock,
--		 meta.manage_stock,
         meta.regular_price,
         meta.sale_price,
         meta.sale_price_dates_from,
         meta.sale_price_dates_to,
         meta.wc_cog_cost,
		 COALESCE(regular_price * stock, 0) AS regular_stock_value,
         COALESCE(if(sale_price IS NOT NULL, sale_price, regular_price) * stock, 0) AS sale_stock_value,
		 COALESCE(
		      if(
			     (sale_price IS NOT NULL AND curr_date BETWEEN sale_price_dates_from AND sale_price_dates_to)
		         OR
			     (sale_price IS NOT NULL AND (sale_price_dates_from IS NULL AND sale_price_dates_to IS NULL))
				 OR
			     (sale_price IS NOT NULL AND (sale_price_dates_from IS NULL AND curr_date <= sale_price_dates_to))
				 OR
				 (sale_price IS NOT NULL AND (sale_price_dates_from <= curr_date AND sale_price_dates_to IS NULL)),
			  sale_price,
			  regular_price) * stock, 0) AS todays_stock_value,
		 COALESCE(wc_cog_cost * stock, 0) AS stock_cost
    FROM $wpdb->posts AS products
         JOIN
         (  SELECT post_id AS product_id,
                   SUM(if(meta_key = '_stock' AND meta_value <> '', meta_value, NULL)) AS stock,
                   MAX(if(meta_key = '_manage_stock' AND meta_value <> '', meta_value, NULL)) AS manage_stock,
                   MAX(if(meta_key = '_stock_status' AND meta_value <> '', meta_value, NULL)) AS stock_status,
                   SUM(if(meta_key = '_regular_price' AND meta_value <> '', meta_value, NULL)) AS regular_price,
                   SUM(if(meta_key = '_sale_price' AND meta_value <> '', meta_value, NULL)) AS sale_price,
                   MAX(if(meta_key = '_sale_price_dates_from' AND meta_value <> '', DATE(FROM_UNIXTIME(meta_value)), NULL)) AS sale_price_dates_from,
                   MAX(if(meta_key = '_sale_price_dates_to' AND meta_value <> '', DATE(FROM_UNIXTIME(meta_value)), NULL)) AS sale_price_dates_to,
                   MAX(if(meta_key = '_sku' AND meta_value <> '', meta_value, NULL)) AS sku,
                   SUM(if(meta_key = '_wc_cog_cost' AND meta_value <> '', meta_value, NULL)) AS wc_cog_cost
              FROM $wpdb->postmeta
             WHERE meta_key IN ('_stock',
                                '_manage_stock',
                                '_stock_status',
                                '_regular_price',
                                '_sale_price',
                                '_sku',
                                '_sale_price_dates_from',
                                '_sale_price_dates_to',
                                '_wc_cog_cost')
          GROUP BY post_id) AS meta
            ON products.ID = meta.product_id
		 JOIN (SELECT curdate() AS curr_date) AS param ON TRUE
   WHERE     CAST(products.post_date AS DATE) <= param.curr_date
         AND (products.post_type = 'product' OR products.post_type = 'product_variation')
		 AND manage_stock='yes'
GROUP BY param.curr_date, meta.product_id, products.post_title" . $limit;

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

		return apply_filters( 'wooreports_rest_prepare_report_stock_at_sales_value', $response, (object) $sales_data, $request );
	}
}