<?php

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class KomtetKassa_ReportsList extends WP_List_Table {

    public static $table_name;

    public function __construct()
    {
        parent::__construct(array(
            'singular' => 'komtetkassa_reports',
            'plural'   => 'komtetkassa_reports',
            'ajax'     => false
        ));

        global $wpdb;
        self::$table_name = $wpdb->prefix . KomtetKassa_Report::REPORT_TABLE_NAME;
    }

    public static function get_reports_data($per_page=5, $page_number=1)
    {
        global $wpdb;

        $sql = "SELECT * FROM " . self::$table_name;

        if (!empty($_REQUEST['orderby']) ) {
            $sql .= ' ORDER BY ' . esc_sql($_REQUEST['orderby']);
            $sql .= !empty($_REQUEST['order']) ? ' ' . esc_sql($_REQUEST['order']) : ' ASC';
        }

        $sql .= " LIMIT $per_page";
        $sql .= ' OFFSET ' . ($page_number - 1) * $per_page;


        $result = $wpdb->get_results($sql, 'ARRAY_A');

        return $result;
    }

    public static function record_count() {
        global $wpdb;

        $sql = "SELECT COUNT(*) FROM " . self::$table_name;

        return $wpdb->get_var($sql);
    }

    public function no_items() {
        echo '<div align="center">Список пуст</div>';
    }

    public function column_default($item, $column_name) {
        switch ( $column_name ) {
            case 'report_id':
            case 'order_id':
                return $item[$column_name];
            case 'status':
                $statuses = array(
                     'new' => "Новая",
                     'error' => "Ошибка",
                     'done' => "Выполнена"
                );
                return $statuses[$item[$column_name]];
            case 'created_at':
            case 'updated_at':
            case 'response_data':
            case 'report_data':
            case 'error':
                return $item[$column_name];
            default:
                return print_r($item, true);
        }
    }

    function get_columns() {
        $columns = array(
            'report_id' => "#",
            'created_at' => "Задача создана",
            'order_id' => "Номер заказа",
            'status' => "Статус",
            'updated_at' => "Задача обновлена",
            'response_data' => "Данные очереди",
            'report_data' => "Данные отчета",
            'error' => "Ошибка"
        );
        return $columns;
    }

    public function get_sortable_columns()
    {
        $columns = array(
            'report_id' => array('report_id', true),
            'order_id' => array( 'order_id', false )
        );
        return $columns;
    }

    public function prepare_items()
    {
        $this->_column_headers = array($this->get_columns(), array(), $this->get_sortable_columns());
        $per_page     = $this->get_items_per_page('customers_per_page', 5);
        $current_page = $this->get_pagenum();
        $total_items  = self::record_count();
        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page'    => $per_page
        ));
        $this->items = self::get_reports_data($per_page, $current_page);
    }
}


final class KomtetKassa_Report {

    const REPORT_TABLE_NAME = 'komtetkassa_reports';


    public function create($order_id, $request_check_data, $response_data, $error="")
    {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . self::REPORT_TABLE_NAME,
            array(
                'order_id' => $order_id,
                'report_data' => json_encode($request_check_data),
                'response_data' => $response_data != null ? json_encode($response_data) : null,
                'error' => empty($error) ? null : $error
            ),
            array('%d', '%s', '%s')
        );
    }

    public function update($order_id, $state, $report_data)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . self::REPORT_TABLE_NAME;

        $report = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE `order_id` = %d LIMIT 1;", $order_id ) );

        if ($report === null) {
            status_header(422);
            header('Content-Type: text/plain');
            echo "Order by external_id {$order_id} not found\n";
            exit;
        }

        $wpdb->update($table_name,
            array(
                'status' => $state,
                'report_data' => json_encode($report_data),
                'error' => $state == "error" ? $report_data['error_description'] : null
            ),
            array('order_id' => $order_id),
            array('%s', '%s', '%s'),
            array('%d')
        );
    }
}
