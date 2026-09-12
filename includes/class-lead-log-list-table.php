<?php
/**
 * Renders the lead log as a native WordPress admin table (sortable,
 * paginated, uses the same look as Posts/Users lists).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class LAI_Lead_Log_List_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct( array(
			'singular' => 'lead',
			'plural'   => 'leads',
			'ajax'     => false,
		) );
	}

	public function get_columns() {
		return array(
			'created_at' => 'Date',
			'source'     => 'Source',
			'lead_name'  => 'Name',
			'lead_email' => 'Email',
			'n8n_status' => 'n8n Status',
			'http_code'  => 'HTTP',
		);
	}

	protected function get_sortable_columns() {
		return array(
			'created_at' => array( 'created_at', true ),
			'n8n_status' => array( 'n8n_status', false ),
		);
	}

	public function prepare_items() {
		global $wpdb;
		$table    = $wpdb->prefix . LAI_TABLE;
		$per_page = 20;
		$paged    = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		$offset   = ( $paged - 1 ) * $per_page;

		$orderby = in_array( $_GET['orderby'] ?? '', array( 'created_at', 'n8n_status' ), true )
			? sanitize_key( $_GET['orderby'] )
			: 'created_at';
		$order = ( strtoupper( $_GET['order'] ?? '' ) === 'ASC' ) ? 'ASC' : 'DESC';

		$total_items = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		$this->items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
				$per_page,
				$offset
			),
			ARRAY_A
		);

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page'    => $per_page,
			'total_pages' => (int) ceil( $total_items / $per_page ),
		) );
	}

	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'created_at':
				return esc_html( $item['created_at'] );
			case 'source':
				return esc_html( strtoupper( $item['source'] ) );
			case 'lead_name':
				return esc_html( $item['lead_name'] );
			case 'lead_email':
				return esc_html( $item['lead_email'] );
			case 'n8n_status':
				return $this->render_status_badge( $item['n8n_status'] );
			case 'http_code':
				return esc_html( $item['n8n_http_code'] ?: '—' );
			default:
				return '';
		}
	}

	private function render_status_badge( $status ) {
		$colors = array(
			'sent'    => '#1e7e34',
			'failed'  => '#c62828',
			'pending' => '#8a6d00',
			'skipped' => '#666',
			'invalid' => '#c62828',
		);
		$color = $colors[ $status ] ?? '#333';
		return sprintf(
			'<span style="color:%s;font-weight:600;">%s</span>',
			esc_attr( $color ),
			esc_html( ucfirst( $status ) )
		);
	}
}
