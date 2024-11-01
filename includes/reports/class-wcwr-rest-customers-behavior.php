<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCWR_REST_Customers_Behavior extends WC_REST_Controller {

	protected $namespace = 'wc/wooreports1';

	protected $rest_base = 'reports/customers-behavior';

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
			'arc_run_report_date' => $request['arc_run_report_date'],
			'arc_interval_type' => $request['arc_interval_type'],
			'arc_int_cons_method' => $request['arc_int_cons_method'],
			'arc_curr_intervals_considered' => $request['arc_curr_intervals_considered'],
			'arc_intervals_displayed' => $request['arc_intervals_displayed'],
			'arc_intervals_considered' => $request['arc_intervals_considered'],
			'arc_th_orders' => $request['arc_th_orders'],
			'arc_th_spent' => $request['arc_th_spent'],
			'arc_measure_to_consider' => $request['arc_measure_to_consider'],
			'arc_logical_operator' => $request['arc_logical_operator'],
		);

		$intervals_displayed_plus_plus = $filter['arc_intervals_displayed'] + $filter['arc_curr_intervals_considered'];
		$run_date_sql = "STR_TO_DATE('" . $filter['arc_run_report_date'] . "', '%Y-%m-%d')";

		$query_limit = (!empty($filter['page']) && !empty($filter['per_page'])) ? " LIMIT " . ( $filter['page'] - 1 ) * $filter['per_page'] . ", " . $filter['per_page'] : "";

		$arc_columns = $wpdb->get_results( "
			SELECT CONCAT(d.ky,'_prev_',CAST(g.n AS CHAR)) AS ky,
						 -- REPLACE(
						 CASE
						 WHEN t.dscr = 'YEAR' THEN
							 CONCAT(d.dscr, ' ', t.cd, LPAD(CAST(YEAR(DATE_FORMAT(DATE_SUB(curr_date,INTERVAL g.n YEAR),'%Y-01-01')) AS CHAR), 4, '0'))
						 WHEN t.dscr = 'QUARTER' THEN
							 CONCAT(d.dscr, ' ', t.cd, LPAD(CAST(QUARTER(DATE_FORMAT(DATE_SUB(curr_date,INTERVAL g.n QUARTER),'%Y-%m-01')) AS CHAR), 2, '0'),' ', CAST(YEAR(DATE_FORMAT(DATE_SUB(curr_date,INTERVAL g.n QUARTER),'%Y-%m-01')) AS CHAR))
						 WHEN t.dscr = 'MONTH' THEN
							 CONCAT(d.dscr, ' ', t.cd, LPAD(CAST(MONTH(DATE_FORMAT(DATE_SUB(curr_date,INTERVAL g.n MONTH),'%Y-%m-01')) AS CHAR), 2, '0'),' ', CAST(YEAR(DATE_FORMAT(DATE_SUB(curr_date,INTERVAL g.n MONTH),'%Y-%m-01')) AS CHAR))
						 WHEN t.dscr = 'WEEK' THEN
							 CONCAT(d.dscr, ' ', t.cd, LPAD(CAST(WEEK(DATE_FORMAT(DATE_SUB(curr_date,INTERVAL g.n WEEK),'%Y-%m-%d')) AS CHAR), 2, '0'),' ', CAST(YEAR(DATE_FORMAT(DATE_SUB(curr_date,INTERVAL g.n WEEK),'%Y-%m-%d')) AS CHAR))
						 WHEN t.dscr = 'DAY' THEN
							 CONCAT(d.dscr, ' ', t.cd, LPAD(CAST(DAY(DATE_FORMAT(DATE_SUB(curr_date,INTERVAL g.n DAY),'%Y-%m-%d')) AS CHAR), 2, '0'),' ', LPAD(CAST(MONTH(DATE_FORMAT(DATE_SUB(curr_date,INTERVAL g.n DAY),'%Y-%m-%d')) AS CHAR), 2, '0'), ' ', CAST(YEAR(DATE_FORMAT(DATE_SUB(curr_date,INTERVAL g.n DAY),'%Y-%m-%d')) AS CHAR))
						 END
						 -- , ' ', '_') 
						 AS dscr,
						 CASE
						 WHEN g.n < p.curr_interval_considered THEN
							 'current'
						 WHEN g.n < p.prev_interval_considered + p.curr_interval_considered THEN
							 'considered'
						 ELSE 'displayed'
						 END AS ky_typ,
						@rn:=@rn+1 AS rw_nbr,
						g.n prt_nbr,
						d.ky smpl_ky
			FROM (
						 SELECT ( hi.n * 16 + lo.n ) AS n
						 FROM
							 (SELECT 0 n UNION ALL SELECT 1  UNION ALL SELECT 2  UNION ALL
								SELECT 3   UNION ALL SELECT 4  UNION ALL SELECT 5  UNION ALL
								SELECT 6   UNION ALL SELECT 7  UNION ALL SELECT 8  UNION ALL
								SELECT 9   UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL
								SELECT 12  UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL
								SELECT 15) lo,
							 (SELECT 0 n UNION ALL SELECT 1  UNION ALL SELECT 2  UNION ALL
								SELECT 3   UNION ALL SELECT 4  UNION ALL SELECT 5  UNION ALL
								SELECT 6   UNION ALL SELECT 7  UNION ALL SELECT 8  UNION ALL
								SELECT 9   UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL
								SELECT 12  UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL
								SELECT 15) hi
						 LIMIT " . $intervals_displayed_plus_plus . "
					 ) g,
				(
					SELECT 'Y' AS cd, 'YEAR' AS dscr UNION ALL
					SELECT 'Q', 'QUARTER' UNION ALL
					SELECT 'M', 'MONTH' UNION ALL
					SELECT 'W', 'WEEK' UNION ALL
					SELECT 'D', 'DAY'
				) t,
				(
					SELECT 1 AS ordr, 'cnt' AS ky, 'Orders' AS dscr UNION ALL
					SELECT 2, 'amt', 'Spent'
				) d,
				(SELECT " . $run_date_sql . " AS curr_date, @rn:=0) c,
				(SELECT " . $filter['arc_intervals_displayed'] . "
					AS prev_interval_displayed,
						" . $filter['arc_intervals_considered'] . "
					AS prev_interval_considered,
						" . $filter['arc_curr_intervals_considered'] . "
					AS curr_interval_considered) p
			WHERE t.dscr = '" . $filter['arc_interval_type'] . "'
			ORDER BY g.n, d.ordr;
		" );

		// $can_user_email_sha256 = ( $wpdb->get_var( "SELECT VERSION() >= '5.5.5'" ) == 1 ) ? true : false;
		// $query_select_user_email_sha256 = $can_user_email_sha256 ? " SHA2(user_email, 256) as user_email_sha256, " : "";
		// $query_groupby_user_email_sha256 = $can_user_email_sha256 ? " user_email_sha256, " : "";
		
		// query_from
		$query_from = "
			FROM (
			  SELECT user_id,
			         user_login,
				     user_email,
					 user_first_name,
					 user_last_name,
			         interval_type,
			         curr_interval_considered,
					 prev_interval_displayed,
					 prev_interval_considered,
					 curr_date,
					 th_cnt,
					 th_amt,
		 			 role_list,
		";

		// repeat intervals
		$to_be_added = array();
		for ( $i = 0; $i < $filter['arc_intervals_displayed'] + $filter['arc_curr_intervals_considered']; $i ++ ) {
			$to_be_added[] = "
				sum(if(interval_value =
	                            CASE
							        WHEN interval_type = 'YEAR' THEN
									     CONCAT('Y', LPAD(CAST(YEAR(DATE_FORMAT(DATE_SUB(curr_date,INTERVAL " . $i . " YEAR),'%Y-01-01')) AS CHAR), 4, '0'))
									WHEN interval_type = 'QUARTER' THEN
										 CONCAT('Q', LPAD(CAST(QUARTER(DATE_FORMAT(DATE_SUB(curr_date,INTERVAL " . $i . " QUARTER),'%Y-%m-01')) AS CHAR), 2, '0'),' ', CAST(YEAR(DATE_FORMAT(DATE_SUB(curr_date,INTERVAL " . $i . " QUARTER),'%Y-%m-01')) AS CHAR))
									WHEN interval_type = 'MONTH' THEN
										 CONCAT('M', LPAD(CAST(MONTH(DATE_FORMAT(DATE_SUB(curr_date,INTERVAL " . $i . " MONTH),'%Y-%m-01')) AS CHAR), 2, '0'),' ', CAST(YEAR(DATE_FORMAT(DATE_SUB(curr_date,INTERVAL " . $i . " MONTH),'%Y-%m-01')) AS CHAR))
									WHEN interval_type = 'WEEK' THEN
										 CONCAT('W', LPAD(CAST(WEEK(DATE_FORMAT(DATE_SUB(curr_date,INTERVAL " . $i . " WEEK),'%Y-%m-%d')) AS CHAR), 2, '0'),' ', CAST(YEAR(DATE_FORMAT(DATE_SUB(curr_date,INTERVAL " . $i . " WEEK),'%Y-%m-%d')) AS CHAR))
									WHEN interval_type = 'DAY' THEN
										 CONCAT('D', LPAD(CAST(DAY(DATE_FORMAT(DATE_SUB(curr_date,INTERVAL " . $i . " DAY),'%Y-%m-%d')) AS CHAR), 2, '0'),' ', LPAD(CAST(MONTH(DATE_FORMAT(DATE_SUB(curr_date,INTERVAL " . $i . " DAY),'%Y-%m-%d')) AS CHAR), 2, '0'), ' ', CAST(YEAR(DATE_FORMAT(DATE_SUB(curr_date,INTERVAL " . $i . " DAY),'%Y-%m-%d')) AS CHAR))
							    END, count_value, 0)) AS cnt_prev_" . $i . ",
	            sum(if(interval_value =
	                            CASE
							        WHEN interval_type = 'YEAR' THEN
									     CONCAT('Y', LPAD(CAST(YEAR(DATE_FORMAT(DATE_SUB(curr_date,INTERVAL " . $i . " YEAR),'%Y-01-01')) AS CHAR), 4, '0'))
									WHEN interval_type = 'QUARTER' THEN
										 CONCAT('Q', LPAD(CAST(QUARTER(DATE_FORMAT(DATE_SUB(curr_date,INTERVAL " . $i . " QUARTER),'%Y-%m-01')) AS CHAR), 2, '0'),' ', CAST(YEAR(DATE_FORMAT(DATE_SUB(curr_date,INTERVAL " . $i . " QUARTER),'%Y-%m-01')) AS CHAR))
									WHEN interval_type = 'MONTH' THEN
										 CONCAT('M', LPAD(CAST(MONTH(DATE_FORMAT(DATE_SUB(curr_date,INTERVAL " . $i . " MONTH),'%Y-%m-01')) AS CHAR), 2, '0'),' ', CAST(YEAR(DATE_FORMAT(DATE_SUB(curr_date,INTERVAL " . $i . " MONTH),'%Y-%m-01')) AS CHAR))
									WHEN interval_type = 'WEEK' THEN
										 CONCAT('W', LPAD(CAST(WEEK(DATE_FORMAT(DATE_SUB(curr_date,INTERVAL " . $i . " WEEK),'%Y-%m-%d')) AS CHAR), 2, '0'),' ', CAST(YEAR(DATE_FORMAT(DATE_SUB(curr_date,INTERVAL " . $i . " WEEK),'%Y-%m-%d')) AS CHAR))
									WHEN interval_type = 'DAY' THEN
										 CONCAT('D', LPAD(CAST(DAY(DATE_FORMAT(DATE_SUB(curr_date,INTERVAL " . $i . " DAY),'%Y-%m-%d')) AS CHAR), 2, '0'),' ', LPAD(CAST(MONTH(DATE_FORMAT(DATE_SUB(curr_date,INTERVAL " . $i . " DAY),'%Y-%m-%d')) AS CHAR), 2, '0'), ' ', CAST(YEAR(DATE_FORMAT(DATE_SUB(curr_date,INTERVAL " . $i . " DAY),'%Y-%m-%d')) AS CHAR))
							    END, amount_value, 0)) AS amt_prev_" . $i . "
			";
		}
		$query_from .= implode( ',', $to_be_added );

		$query_from .= ',
         ifnull(sum(count_value), 0) AS cnt_prev_all,
         ifnull(sum(amount_value), 0) AS amt_prev_all
         ';

		$query_from .= "
						FROM (SELECT u.ID AS user_id,
					             u.user_login,
					             u.user_email,
								 um1.meta_value AS user_first_name,
								 um2.meta_value AS user_last_name,
							     COALESCE(t.interval_type, '" . $filter['arc_interval_type'] . "') AS interval_type,
							     COALESCE(t.curr_interval_considered, " . $filter['arc_curr_intervals_considered'] . ") AS curr_interval_considered,
							     COALESCE(t.prev_interval_displayed, " . $filter['arc_intervals_displayed'] . ") AS prev_interval_displayed,
							     COALESCE(t.prev_interval_considered, " . $filter['arc_intervals_considered'] . ") AS prev_interval_considered,
							     COALESCE(t.curr_date, " . $run_date_sql . " ) AS curr_date,
							     COALESCE(t.th_cnt, " . $filter['arc_th_orders'] . ") AS th_cnt,
							     COALESCE(t.th_amt, " . $filter['arc_th_spent'] . ") AS th_amt,
				                 t.interval_value,
				                 t.amount_value,
				                 t.count_value,
				 				 user_role.role_list
				            FROM $wpdb->users AS u
							     JOIN $wpdb->usermeta AS um1 ON u.ID = um1.user_id
							     JOIN $wpdb->usermeta AS um2 ON u.ID = um2.user_id
				 LEFT JOIN
				 (
  SELECT um3.user_id,
		 GROUP_CONCAT(SUBSTRING_INDEX(SUBSTRING_INDEX(um3.meta_value, '\"', numbers.n),
                         '\"',
                         -1) SEPARATOR ', ') role_list
    FROM (SELECT ( hi.n * 16 + lo.n ) + 1 AS n
		  FROM
			(SELECT 0 n UNION ALL SELECT 1  UNION ALL SELECT 2  UNION ALL
			 SELECT 3   UNION ALL SELECT 4  UNION ALL SELECT 5  UNION ALL
			 SELECT 6   UNION ALL SELECT 7  UNION ALL SELECT 8  UNION ALL
			 SELECT 9   UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL
			 SELECT 12  UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL
			 SELECT 15) lo,
			(SELECT 0 n UNION ALL SELECT 1  UNION ALL SELECT 2  UNION ALL
			 SELECT 3   UNION ALL SELECT 4  UNION ALL SELECT 5  UNION ALL
			 SELECT 6   UNION ALL SELECT 7  UNION ALL SELECT 8  UNION ALL
			 SELECT 9   UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL
			 SELECT 12  UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL
			 SELECT 15) hi
		   LIMIT 16) numbers
         INNER JOIN (SELECT user_id, meta_value
                       FROM $wpdb->usermeta
                      WHERE meta_key LIKE '" . $wpdb->prefix . "%capabilities') um3
            ON CHAR_LENGTH(um3.meta_value) - CHAR_LENGTH(REPLACE(um3.meta_value, '\"', '')) >= numbers.n - 1
   WHERE mod(numbers.n, 2) = 0
GROUP BY user_id) AS user_role ON u.ID = user_role.user_id
				                 LEFT JOIN
				                 (  SELECT CAST(meta.meta_value AS UNSIGNED) AS user_id,
								           param.interval_type,
								           param.curr_interval_considered,
										   param.prev_interval_displayed,
				                           param.prev_interval_considered,
										   param.curr_date,
										   param.th_cnt,
										   param.th_amt,
				                           CASE
										        WHEN param.interval_type = 'YEAR' THEN
												     -- CONCAT('Y', LPAD(CAST(YEAR(posts.post_date) AS CHAR), 2, '0'),' ', CAST(YEAR(posts.post_date) AS CHAR))
												     CONCAT('Y', CAST(YEAR(posts.post_date) AS CHAR))
												WHEN param.interval_type = 'QUARTER' THEN
													 CONCAT('Q', LPAD(CAST(QUARTER(posts.post_date) AS CHAR), 2, '0'),' ', CAST(YEAR(posts.post_date) AS CHAR))
												WHEN param.interval_type = 'MONTH' THEN
													 CONCAT('M', LPAD(CAST(MONTH(posts.post_date) AS CHAR), 2, '0'),' ', CAST(YEAR(posts.post_date) AS CHAR))
												WHEN param.interval_type = 'WEEK' THEN
													 CONCAT('W', LPAD(CAST(WEEK(posts.post_date) AS CHAR), 2, '0'),' ', CAST(YEAR(posts.post_date) AS CHAR))
												WHEN param.interval_type = 'DAY' THEN
													 CONCAT('D', LPAD(CAST(DAY(posts.post_date) AS CHAR), 2, '0'),' ', LPAD(CAST(MONTH(posts.post_date) AS CHAR), 2, '0'), ' ', CAST(YEAR(posts.post_date) AS CHAR))
										   END AS interval_value,
				                           SUM(
				                              if(posts.post_status IN ('wc-completed', 'wc-processing')
				                                 AND posts.post_type IN ('" . implode( "','",
				wc_get_order_types( 'reports' ) ) . "'),
				                                 meta2.meta_value,
				                                 0))
				                              AS amount_value,
				                           SUM(if( posts.post_type     IN ('" . implode( "','",
				wc_get_order_types( 'order-count' ) ) . "')
				                                   AND posts.post_status   IN ('" . implode( "','",
				array_keys( wc_get_order_statuses() ) ) . "')
				                                  , 1, 0)) AS count_value
				                      FROM $wpdb->posts AS posts
				                           LEFT JOIN $wpdb->postmeta AS meta ON posts.ID = meta.post_id
				                           LEFT JOIN $wpdb->postmeta AS meta2 ON posts.ID = meta2.post_id
				                           JOIN (SELECT '" . $filter['arc_interval_type'] . "' AS interval_type,
				                                        " . $filter['arc_curr_intervals_considered'] . " AS curr_interval_considered,
				                                        " . $filter['arc_intervals_displayed'] . " AS prev_interval_displayed,
				                                        " . $filter['arc_intervals_considered'] . " AS prev_interval_considered,
														" . $run_date_sql . " AS curr_date,
														" . $filter['arc_th_orders'] . " AS th_cnt,
														" . $filter['arc_th_spent'] . " AS th_amt) AS param ON TRUE
				                     WHERE  CAST(posts.post_date as DATE) <= param.curr_date
				                           	-- posts.post_date >=
											--	  CAST(
											--		 CASE
											--			WHEN param.interval_type = 'YEAR' THEN
											--				DATE_FORMAT(DATE_SUB(param.curr_date,INTERVAL param.curr_interval_considered + param.prev_interval_displayed YEAR),'%Y-01-01')
											--			WHEN param.interval_type = 'QUARTER' THEN
											--				DATE_FORMAT(DATE_SUB(param.curr_date,INTERVAL param.curr_interval_considered + param.prev_interval_displayed QUARTER),'%Y-%m-01')
											--			WHEN param.interval_type = 'MONTH' THEN
											--			   DATE_FORMAT(DATE_SUB(param.curr_date,INTERVAL param.curr_interval_considered + param.prev_interval_displayed MONTH),'%Y-%m-01')
											--			WHEN param.interval_type = 'WEEK' THEN
											--				DATE_FORMAT(DATE_SUB(param.curr_date,INTERVAL param.curr_interval_considered + param.prev_interval_displayed WEEK),'%Y-%m-%d')
											--			WHEN param.interval_type = 'DAY' THEN
											--				DATE_FORMAT(DATE_SUB(param.curr_date,INTERVAL param.curr_interval_considered + param.prev_interval_displayed DAY),'%Y-%m-%d')
											--		 END
											--	  AS DATE)
				                           AND meta.meta_key = '_customer_user'
				                           AND meta2.meta_key = '_order_total'
				                  GROUP BY meta.meta_value,
								           param.interval_type,
								           param.curr_interval_considered,
										   param.prev_interval_displayed,
										   param.prev_interval_considered,
										   param.curr_date,
										   param.th_cnt,
										   param.th_amt,
				                           CASE
										        WHEN param.interval_type = 'YEAR' THEN
												     -- CONCAT('Y', LPAD(CAST(YEAR(posts.post_date) AS CHAR), 2, '0'),' ', CAST(YEAR(posts.post_date) AS CHAR))
												     CONCAT('Y', CAST(YEAR(posts.post_date) AS CHAR))
												WHEN param.interval_type = 'QUARTER' THEN
													 CONCAT('Q', LPAD(CAST(QUARTER(posts.post_date) AS CHAR), 2, '0'),' ', CAST(YEAR(posts.post_date) AS CHAR))
												WHEN param.interval_type = 'MONTH' THEN
													 CONCAT('M', LPAD(CAST(MONTH(posts.post_date) AS CHAR), 2, '0'),' ', CAST(YEAR(posts.post_date) AS CHAR))
												WHEN param.interval_type = 'WEEK' THEN
													 CONCAT('W', LPAD(CAST(WEEK(posts.post_date) AS CHAR), 2, '0'),' ', CAST(YEAR(posts.post_date) AS CHAR))
												WHEN param.interval_type = 'DAY' THEN
													 CONCAT('D', LPAD(CAST(DAY(posts.post_date) AS CHAR), 2, '0'),' ', LPAD(CAST(MONTH(posts.post_date) AS CHAR), 2, '0'), ' ', CAST(YEAR(posts.post_date) AS CHAR))
										   END
										   ) t
				                    ON u.ID = t.user_id
						      WHERE um1.meta_key = 'first_name'
							    AND um2.meta_key = 'last_name'
								AND CAST(u.user_registered as DATE) <= curr_date) x
				GROUP BY user_id,
				         user_login,
					     user_email,
						 user_first_name,
						 user_last_name,
						 -- interval_type,
						 -- curr_interval_considered,
						 -- prev_interval_displayed,
						 -- prev_interval_considered,
						 curr_date,
	     				 role_list
						 -- , th_cnt,
						 -- th_amt
				) Y
		";

		// query_select
		$interval_divider = array( 'NEW_1' => '', 'ACTIVE_1' => '', 'ACTIVE_2' => '', 'RETURNING_1' => '', 'RETURNING_2' => '', 'CHURNING_1' => '', 'CHURNING_2' => '', 'CHURNING_3' => '', 'INACTIVE_1' => '', 'INACTIVE_2' => '', 'INACTIVE_3' => '');
		if ( $filter['arc_int_cons_method'] == 'LEAST' ) {
			$interval_function = ' least(';
			$interval_delimiter = ',';
			$interval_suffix = ', 999999999999) ';
		} else if ( $filter['arc_int_cons_method'] == 'ADDITION' || $filter['arc_int_cons_method'] == 'AVERAGE' ) {
			$interval_function = ' (';
			$interval_delimiter = '+';
			$interval_suffix = ') ';
		}

		if ( $filter['arc_int_cons_method'] == 'AVERAGE' ) {
			$interval_divider['NEW_1'] = '/ ' . $filter['arc_curr_intervals_considered'] . ' ';
			$interval_divider['ACTIVE_1'] = '/ ' . $filter['arc_curr_intervals_considered'] . ' ';
			$interval_divider['ACTIVE_2'] = '/ ' . $filter['arc_intervals_considered'] . ' ';
			$interval_divider['RETURNING_1'] = '/ ' . $filter['arc_curr_intervals_considered'] . ' ';
			$interval_divider['RETURNING_2'] = '/ ' . $filter['arc_intervals_considered'] . ' ';
			$interval_divider['CHURNING_1'] = '/ ' . $filter['arc_curr_intervals_considered'] . ' ';
			$interval_divider['CHURNING_2'] = '/ ' . $filter['arc_intervals_considered'] . ' ';
			$interval_divider['CHURNING_3'] = '/ ' . $filter['arc_intervals_displayed'] . ' ';
			$interval_divider['INACTIVE_1'] = '/ ' . $filter['arc_curr_intervals_considered'] . ' ';
			$interval_divider['INACTIVE_2'] = '/ ' . $filter['arc_intervals_considered'] . ' ';
			$interval_divider['INACTIVE_3'] = '/ ' . $filter['arc_intervals_displayed'] . ' ';
		}

		$to_be_added_cnt_string = array();
		$to_be_added_amt_string = array();
		foreach ( $arc_columns as $key => $value ) {
			if ( $value->ky_typ == 'considered' ) {
				if ( $value->smpl_ky == 'cnt' ) {
					$to_be_added_cnt_string[] = $value->ky;
				}
				if ( $value->smpl_ky == 'amt' ) {
					$to_be_added_amt_string[] = $value->ky;
				}
			}
		}
		$cnt_string = implode( $interval_delimiter, $to_be_added_cnt_string );
		$amt_string = implode( $interval_delimiter, $to_be_added_amt_string );

		$to_be_added_cnt_string_d = array();
		$to_be_added_amt_string_d = array();
		foreach ( $arc_columns as $key => $value ) {
			if ( $value->ky_typ == 'displayed' ) {
				if ( $value->smpl_ky == 'cnt' ) {
					$to_be_added_cnt_string_d[] = $value->ky;
				}
				if ( $value->smpl_ky == 'amt' ) {
					$to_be_added_amt_string_d[] = $value->ky;
				}
			}
		}
		$cnt_string_d = implode( $interval_delimiter, $to_be_added_cnt_string_d );
		$amt_string_d = implode( $interval_delimiter, $to_be_added_amt_string_d );

		$to_be_added_cnt_string_c = array();
		$to_be_added_amt_string_c = array();
		foreach ( $arc_columns as $key => $value ) {
			if ( $value->ky_typ == 'current' ) {
				if ( $value->smpl_ky == 'cnt' ) {
					$to_be_added_cnt_string_c[] = $value->ky;
				}
				if ( $value->smpl_ky == 'amt' ) {
					$to_be_added_amt_string_c[] = $value->ky;
				}
			}
		}
		$cnt_string_c = implode( $interval_delimiter, $to_be_added_cnt_string_c );
		$amt_string_c = implode( $interval_delimiter, $to_be_added_amt_string_c );
		$cnt_string_m_c = '-' . implode( '-', $to_be_added_cnt_string_c );
		$amt_string_m_c = '-' . implode( '-', $to_be_added_amt_string_c );

		$arc_ind_case = array( 	'NEW_CNT_1' => '', 
								'NEW_CNT_2' => '', 
								'ACTIVE_CNT_1' => '', 
								'ACTIVE_CNT_2' => '', 
								'RETURNING_CNT_1' => '', 
								'RETURNING_CNT_2' => '', 
								'CHURNING_CNT_1' => '', 
								'CHURNING_CNT_2' => '', 
								'CHURNING_CNT_3' => '', 
								'INACTIVE_CNT_1' => '', 
								'INACTIVE_CNT_2' => '', 
								'INACTIVE_CNT_3' => '',
								'NEW_AMT_1' => '', 
								'NEW_AMT_2' => '', 
								'ACTIVE_AMT_1' => '', 
								'ACTIVE_AMT_2' => '', 
								'RETURNING_AMT_1' => '', 
								'RETURNING_AMT_2' => '', 
								'CHURNING_AMT_1' => '', 
								'CHURNING_AMT_2' => '', 
								'CHURNING_AMT_3' => '', 
								'INACTIVE_AMT_1' => '', 
								'INACTIVE_AMT_2' => '', 
								'INACTIVE_AMT_3' => '',);
		$filter['arc_measure_to_consider'] == 'BOTH' ? $arc_ind_case['LOGICAL_OPERATOR'] = $filter['arc_logical_operator'] : $arc_ind_case['LOGICAL_OPERATOR'] = '';
		if ( $filter['arc_measure_to_consider'] == 'CNT' || $filter['arc_measure_to_consider'] == 'BOTH' ) {
			$arc_ind_case['NEW_CNT_1'] = $interval_function . $cnt_string_c . $interval_suffix . $interval_divider['NEW_1'] . ">= th_cnt ";
			$arc_ind_case['NEW_CNT_2'] = " cnt_prev_all" . $cnt_string_m_c . " < th_cnt ";
			$arc_ind_case['ACTIVE_CNT_1'] = $interval_function . $cnt_string_c . $interval_suffix . $interval_divider['ACTIVE_1'] . ">= th_cnt ";
			$arc_ind_case['ACTIVE_CNT_2'] = $interval_function . $cnt_string . $interval_suffix . $interval_divider['ACTIVE_2'] . ">= th_cnt ";
			$arc_ind_case['RETURNING_CNT_1'] = $interval_function . $cnt_string_c . $interval_suffix . $interval_divider['RETURNING_1'] . ">= th_cnt ";
			$arc_ind_case['RETURNING_CNT_2'] = $interval_function . $cnt_string . $interval_suffix . $interval_divider['RETURNING_2'] . "< th_cnt ";
			$arc_ind_case['CHURNING_CNT_1'] = $interval_function . $cnt_string_c . $interval_suffix . $interval_divider['CHURNING_1'] . "< th_cnt ";
			$arc_ind_case['CHURNING_CNT_2'] = $interval_function . $cnt_string . $interval_suffix . $interval_divider['CHURNING_2'] . ">= th_cnt ";
			$arc_ind_case['CHURNING_CNT_3'] = $interval_function . $cnt_string_d . $interval_suffix . $interval_divider['CHURNING_3'] . ">= th_cnt ";
			$arc_ind_case['INACTIVE_CNT_1'] = $interval_function . $cnt_string_c . $interval_suffix . $interval_divider['INACTIVE_1'] . "< th_cnt ";
			$arc_ind_case['INACTIVE_CNT_2'] = $interval_function . $cnt_string . $interval_suffix . $interval_divider['INACTIVE_2'] . "< th_cnt ";
			$arc_ind_case['INACTIVE_CNT_3'] = $interval_function . $cnt_string_d . $interval_suffix . $interval_divider['INACTIVE_3'] . "< th_cnt ";
		}
		if ( $filter['arc_measure_to_consider'] == 'AMT' || $filter['arc_measure_to_consider'] == 'BOTH' ) {
			$arc_ind_case['NEW_AMT_1'] = $interval_function . $amt_string_c . $interval_suffix . $interval_divider['NEW_1'] . ">= th_amt ";
			$arc_ind_case['NEW_AMT_2'] = " amt_prev_all" . $amt_string_m_c . " < th_amt ";
			$arc_ind_case['ACTIVE_AMT_1'] = $interval_function . $amt_string_c . $interval_suffix . $interval_divider['ACTIVE_1'] . ">= th_amt ";
			$arc_ind_case['ACTIVE_AMT_2'] = $interval_function . $amt_string . $interval_suffix . $interval_divider['ACTIVE_2'] . ">= th_amt ";
			$arc_ind_case['RETURNING_AMT_1'] = $interval_function . $amt_string_c . $interval_suffix . $interval_divider['RETURNING_1'] . ">= th_amt ";
			$arc_ind_case['RETURNING_AMT_2'] = $interval_function . $amt_string . $interval_suffix . $interval_divider['RETURNING_2'] . "< th_amt ";
			$arc_ind_case['CHURNING_AMT_1'] = $interval_function . $amt_string_c . $interval_suffix . $interval_divider['CHURNING_1'] . "< th_amt ";
			$arc_ind_case['CHURNING_AMT_2'] = $interval_function . $amt_string . $interval_suffix . $interval_divider['CHURNING_2'] . ">= th_amt ";
			$arc_ind_case['CHURNING_AMT_3'] = $interval_function . $amt_string_d . $interval_suffix . $interval_divider['CHURNING_3'] . ">= th_amt ";
			$arc_ind_case['INACTIVE_AMT_1'] = $interval_function . $amt_string_c . $interval_suffix . $interval_divider['INACTIVE_1'] . "< th_amt ";
			$arc_ind_case['INACTIVE_AMT_2'] = $interval_function . $amt_string . $interval_suffix . $interval_divider['INACTIVE_2'] . "< th_amt ";
			$arc_ind_case['INACTIVE_AMT_3'] = $interval_function . $amt_string_d . $interval_suffix . $interval_divider['INACTIVE_3'] . "< th_amt ";
		}
		
		if ( !empty($cnt_string_d) && !empty($amt_string_d) ) {
			$sql_churning_string_d = "	OR
										(
											" . $arc_ind_case['CHURNING_CNT_3'] . "
											" . $arc_ind_case['LOGICAL_OPERATOR'] . "
											" . $arc_ind_case['CHURNING_AMT_3'] . "
										)";
			$sql_inactive_string_d = "	AND
										(
											" . $arc_ind_case['INACTIVE_CNT_3'] . "
											" . $arc_ind_case['LOGICAL_OPERATOR'] . "
											" . $arc_ind_case['INACTIVE_AMT_3'] . "
										)";
		} else {
			$sql_inactive_string_d = 'AND FALSE'; //moving to next CASE condition, below
			$sql_churning_string_d = 'OR FALSE'; //moving to next CASE condition, below
		}

		$query_select = "SELECT user_id,
						  user_login,
						  user_email,
						  user_first_name,
						  user_last_name,
						  IF(user_first_name <> '' AND user_last_name <> '', CONCAT(user_first_name, ', ', user_last_name), '-' ) AS user_name,
						  -- interval_type,
						  -- curr_interval_considered,
						  -- prev_interval_displayed,
						  -- prev_interval_considered,
						  curr_date,
						  -- th_cnt,
						  -- th_amt,
	   					  role_list,
						  CASE
							WHEN     
								(
					  		       " . $arc_ind_case['NEW_CNT_1'] . "
					               " . $arc_ind_case['LOGICAL_OPERATOR'] . " 
					               " . $arc_ind_case['NEW_AMT_1'] . "
						        )
								AND
								(
									" . $arc_ind_case['NEW_CNT_2'] . "
									" . $arc_ind_case['LOGICAL_OPERATOR'] . " 
									" . $arc_ind_case['NEW_AMT_2'] . "
								)
							THEN
								'New'
							WHEN
								(
									" . $arc_ind_case['ACTIVE_CNT_1'] . "
									" . $arc_ind_case['LOGICAL_OPERATOR'] . " 
						            " . $arc_ind_case['ACTIVE_AMT_1'] . "
								)
                            	AND 
                            	(   
                            		" . $arc_ind_case['ACTIVE_CNT_2'] . "
						            " . $arc_ind_case['LOGICAL_OPERATOR'] . " 
						            " . $arc_ind_case['ACTIVE_AMT_2'] . "
								)
							THEN
								'Active'
							WHEN
								(
									" . $arc_ind_case['RETURNING_CNT_1'] . "
						            " . $arc_ind_case['LOGICAL_OPERATOR'] . "
									" . $arc_ind_case['RETURNING_AMT_1'] . "
								)
								AND
								(
									" . $arc_ind_case['RETURNING_CNT_2'] . "
									" . $arc_ind_case['LOGICAL_OPERATOR'] . "
									" . $arc_ind_case['RETURNING_AMT_2'] . "
								)
							THEN
								'Returning'
							WHEN
								(
									" . $arc_ind_case['CHURNING_CNT_1'] . "
									" . $arc_ind_case['LOGICAL_OPERATOR'] . "
									" . $arc_ind_case['CHURNING_AMT_1'] . "
								)
								AND
								(
						  			(
						  				" . $arc_ind_case['CHURNING_CNT_2'] . "
										" . $arc_ind_case['LOGICAL_OPERATOR'] . "
										" . $arc_ind_case['CHURNING_AMT_2'] . "
									)
									" . $sql_churning_string_d . "
								)
							THEN
								'Churning'
							WHEN
								(
									" . $arc_ind_case['INACTIVE_CNT_1'] . "
						       		" . $arc_ind_case['LOGICAL_OPERATOR'] . "
									" . $arc_ind_case['INACTIVE_AMT_1'] . "
								)
								AND
									(
										" . $arc_ind_case['INACTIVE_CNT_2'] . "
										" . $arc_ind_case['LOGICAL_OPERATOR'] . "
										" . $arc_ind_case['INACTIVE_AMT_2'] . "
									)
								" . $sql_inactive_string_d . "
							THEN
								'Inactive'
							ELSE
								'ERROR - Undefined'
						END AS arc_ind";

		// add columns to select clause
		$to_be_added = array();
		if ( ! empty( $arc_columns ) ) {
			$query_select .= ',';
			foreach ( $arc_columns as $key => $value ) {
				$to_be_added[] = $value->ky . ' AS "' . $value->dscr . '"';
			}
			$query_select .= implode( ',', $to_be_added );
		}

		$query_select .= ',
			cnt_prev_all,
			amt_prev_all
			';

		$query_full_select = "{$query_select} {$query_from} {$query_where} {$query_limit}";
		$query_data = $wpdb->get_results( $query_full_select );		

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
				'query' => $query_full_select,
			),
		) );

		return apply_filters( 'wooreports_rest_prepare_report_customers_behaviour', $response, (object) $sales_data, $request );
	}
}