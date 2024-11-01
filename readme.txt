=== WooReports API ===
Contributors: luciancapdefier
Donate link: https://woo.report/
Tags: woocommerce, reports, analytics, new customers, churning customers, active customers, churn customers, returning customers, stock value, order report, customer report, product report
Requires at least: 4.0.1
Tested up to: 4.7.4
Stable tag: 2.0.2
License: GPLv3 or later License
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Enhance WooCommerce reporting and analytical capabilities of WooCommerce with WooReports!

== Description ==
Understand who are your new customers. Check out WooReports for advanced analytics and full report – New, Active, Returning, Churning, Inactive WooCommerce Customers List as well as other interesting reports.

== Installation ==
Download to /wp-content/plugins/wooreports-free directory, go to WordPress Admin > Plugins > active the WooReports plugin.

== Changelog ==

= 2.0.2 =
Fixed bug in Customers Behaviour report, when MySQL version is grater than 5.5.5 and is not configured with crypto functions on

= 2.0.1 =
Adding Products Affinity

= 2.0.0 =
Major redesign of WooReports. Current WordPress Plugin is called WooReports API and will constitute the part of the solution which exposes data to WooReports Dashboard, a hosted service which you can access at https://woo.report

= 1.0.2 =
Minor changes for WordPress.org catalog listing.

= 1.0.0 =
Released.

== Upgrade Notice ==

= 2.0.1 =
Upgrade using standard WordPress plugins upgrade procedure

= 2.0.0 =
Deactivate or uninstall previous versions of WooReports or WooReports Free, then follow the steps described here https://woo.report/setting-up-wooreports/

= 1.0.2 =
No special actions needed.

= 1.0.0 =
Released.

== Frequently Asked Questions ==

= How are is Number of Orders calculated? =

Number of Orders is a count, considering:
a/ post type is in a defined list by WooCommerce (using the function wc_get_order_types( 'order-count' ) ); this assures we're always in-sync with how WooCommerce looks at counting orders.
b/ post status is in a defined list by WooCommerce (using wc_get_order_statuses() ), so pretty much all possible post type status values; same advantages as above.
c/ obviously, it's broken down by Month, in case it's a monthly metric or it's not, in case of Orders All.

= How are is Amount Spent calculated? =

Amount Spent is a count, considering:
a/ post type is in a defined list by WooCommerce (using the function wc_get_order_types( 'reports' ) ); this assures we're always in-sync with how WooCommerce looks at calculating the amount spent by a customer.
b/ post status is in a defined list, Completed or Processing, ( ('wc-completed', 'wc-processing') ); this is important to understand as it does not represent just th evalue of the order, it represents the value of an order once the payment has been made, provided that the usual flow of statuses is preserved and the store owner sets the order in status completed or processing once he/she acknoledges the money have been transfered to the store.
c/ obviously, it's broken down by Month, in case it's a monthly metric or it's not, in case of Spent All.

= Can I export the data? =

Exporting as csv (comma separated values) is available in the Premium edition, together with other valuable customer insights.

= How can I find out which customers are churning, returning, active, inactive? =

WooReports Premium offers extended analysis by providing churning, returning, active, inactive customers. Moreover, you can set you threshold at any desired value and define more than 2 current intervals and as many as you like number of previous intervals, needed for the calculation of the other indicator values (churning, returning, etc.)

= How can I stay connected to what's new? =

Subscribing to our newsletter is a good way to do that. We promise we'll just update you with what we believe as being relevant. Checkout subscription form at the bottom of our page, https://woo.report/

== Screenshots ==
1. Welcome screen of WooReports
2. Select your WooCommerce store, once you've authenticated WooReports API with WooReports Dashboard (this is a secure method provided by WooCommerce)
3. Select / search the report you desire, using the Menu tree on the left
4. Upon Report selection, from the menu, data will be fetched using default filter values; to change these values press the funnel button in the header of the data grid panel; set your desired report filters; obviously, report filters vary by report; not all reports have available filters
5. Easy code completion and data validation is available for filter fields (and usually for most input or display fields)
6. Report data can be displayed using at least two methods, simple Grid and an Pivot Grid; Pivot Grid is quite similar to Microsoft's Pivot feature; Fields are available, they can be positioned in a matrix, on columns, rows and at the intersection, offering you the possibility to analyze the data in the desired way
7. In Pivot Grids, fields can be expanded and collapsed
8. In Pivot Grids, subtotals can be displayed or not
9. In Pivot Grids, totals can be displayed or not
10. Data can be refreshed (re-fetched from your server - from WooCommerce) by pushing the button in the header of the data grid panel
11. The data display panel can be maximized for deeper focus on analyzed data
12. Data can be exported, showing Pivot Grid export feature; in Pivot Grid, you can export either only visible data, or all configured/initial displayed data, both in Excel and CSV formats
13. Data can be displayed also in simple Grids, a tabular display of data
14. Data export is available in Grids as well, both in Excel and CSV formats
15. Some reports have comprehensive filter fields, like Customer Behavior report
16. Filter fields validation is ensured by date selectors, number increment-ors and drop-down lists
17. Filter fields validation is ensured by date selectors, number increment-ors and drop-down lists
18. Filter fields validation is ensured by date selectors, number increment-ors and drop-down lists
19. Once data is fetched from your server, Pivot Data can be further filtered, for example, if you want to look at products  that their name start with 'Product'
20. By pressing the question mark button in the header of the data grid panel, the help window is displayed
21. Each reports is briefly presented in a help window
22. Once WooCommerce Stores are defined (see our blog on how to achieve this), they need to be authenticated; this procedure must be done just one time