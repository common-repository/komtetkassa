<?php
/*
Plugin Name: WooCommerce - КОМТЕТ Касса
Description: Фискализация платежей с помощью сервиса КОМТЕТ Касса для плагина WooCommerce
Plugin URI: http://wordpress.org/plugins/komtetkassa/
Author: Komtet
Version: 1.3.0
Author URI: http://kassa.komtet.ru/
*/

use Komtet\KassaSdk\Client;
use Komtet\KassaSdk\QueueManager;
use Komtet\KassaSdk\Check;
use Komtet\KassaSdk\Payment;
use Komtet\KassaSdk\Position;
use Komtet\KassaSdk\Vat;
use Komtet\KassaSdk\TaxSystem;
use Komtet\KassaSdk\Exception\ClientException;
use Komtet\KassaSdk\Exception\SdkException;

final class KomtetKassa {

    public $version = '1.3.0';

    const DEFAULT_QUEUE_NAME = 'default';
    const DISCOUNT_NOT_AVAILABLE = 0;

    private static $_instance = null;

    public static function instance() {
        if (is_null(self::$_instance) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct()
    {
        $this->define('KOMTETKASSA_ABSPATH', plugin_dir_path( __FILE__));
        $this->define('KOMTETKASSA_ABSPATH_VIEWS', plugin_dir_path( __FILE__) . 'includes/views/');
        $this->define('KOMTETKASSA_BASENAME', plugin_basename( __FILE__ ));

        $this->includes();
        $this->hooks();
        $this->wp_hooks();
        $this->wp_endpoints();
        $this->load_options();
        $this->init();
    }

    public function wp_hooks()
    {
        register_activation_hook( __FILE__, array('KomtetKassa_Install', 'activation'));
        add_action('woocommerce_order_status_' . get_option('komtetkassa_fiscalize_on_order_status'), array($this, 'fiscalize'));
    }

    public function wp_endpoints()
    {
        add_filter('query_vars', array($this, 'add_query_vars'), 0);
        add_action('init', array($this, 'add_endpoint'), 0);
        add_action('parse_request', array($this, 'handle_requests'), 0);
    }

    public function hooks()
    {
        add_action('komtet_kassa_action_success', array($this, 'action_success'));
        add_action('komtet_kassa_action_fail', array($this, 'action_fail'));
        add_action('komtet_kassa_report_create', array($this, 'report_create'), 10, 4);
        add_action('komtet_kassa_report_update', array($this, 'report_update'), 10, 3);
    }

    public function includes()
    {
        require_once(KOMTETKASSA_ABSPATH . 'includes/class-komtetkassa-install.php');
        require_once(KOMTETKASSA_ABSPATH . 'includes/class-komtetkassa-report.php');
        require_once(KOMTETKASSA_ABSPATH . 'includes/libs/komtet-kassa-php-sdk/autoload.php');

        if (is_admin()) {
            require_once(KOMTETKASSA_ABSPATH . 'includes/class-komtetkassa-admin.php');
            add_action('init', array( 'KomtetKassa_Admin', 'init'));
        }
    }

    private function define($name, $value)
    {
        if (!defined( $name )) {
            define( $name, $value );
        }
    }

    public function load_options() {
        $this->shop_id = get_option('komtetkassa_shop_id');
        $this->secret_key = get_option('komtetkassa_secret_key');
        $this->queue_id = get_option('komtetkassa_queue_id');
    }

    public function init()
    {
        do_action('before_komtetkassa_init');
        $this->client = new Client($this->shop_id, $this->secret_key);
        $this->queueManager = new QueueManager($this->client);
        $this->queueManager->registerQueue(self::DEFAULT_QUEUE_NAME, $this->queue_id);
        $this->queueManager->setDefaultQueue(self::DEFAULT_QUEUE_NAME);
        $this->report = new KomtetKassa_Report();
        do_action('komtetkassa_init');
    }

    public function taxSystems() {
		return array(
			TaxSystem::COMMON => 'ОСН',
			TaxSystem::SIMPLIFIED_IN => 'УСН доход',
			TaxSystem::SIMPLIFIED_IN_OUT => 'УСН доход - расход',
			TaxSystem::UTOII => 'ЕНВД',
			TaxSystem::UST => 'ЕСН',
			TaxSystem::PATENT => 'Патент'
		);
	}

    public function fiscalize($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $tax_system = intval(get_option('komtetkassa_tax_system'));

        $clientContact = "";
        if ($order->get_billing_email()) {
            $clientContact = $order->get_billing_email();
        } else {
            $clientContact = $order->get_billing_phone();
            $clientContact = mb_eregi_replace("[^0-9]", '', $clientContact);
        }

        $check = new Check($order_id, $clientContact, Check::INTENT_SELL, $tax_system);

        $check->setShouldPrint(get_option('komtetkassa_should_print'));

        if (sizeof($order->get_items()) > 0 ) {
            foreach ($order->get_items('line_item') as $item) {
                $check->addPosition(new Position(
                     $item->get_name(),
                     $order->get_item_total($item, true, true),
                     $item->get_quantity(),
                     $order->get_line_total($item, true, true),
                     $order->get_line_subtotal($item, false, true) - $order->get_item_total($item, false, true),
                     new Vat(Vat::RATE_NO)
                ));
            }
            // shipping
            foreach ($order->get_items('shipping') as $item) {
                $check->addPosition(new Position(
                    $item->get_name(),
                    $order->get_item_total($item, true, true),
                    $item->get_quantity(),
                    $order->get_line_total($item, true, true),
                    self::DISCOUNT_NOT_AVAILABLE,
                    new Vat(Vat::RATE_NO)
               ));
            }
        }

        $payment = new Payment(Payment::TYPE_CARD, floatval($order->get_total()));
        $check->addPayment($payment);

        $error_message = "";
        $response = null;
        try {
            $response = $this->queueManager->putCheck($check);
        } catch (SdkException $e) {
            $error_message = $e->getMessage();
        }
        do_action('komtet_kassa_report_create', $order_id, $check->asArray(), $response, $error_message);
    }

    public function add_query_vars($vars) {
        $vars[] = 'komtet-kassa';
        return $vars;
    }

    public static function add_endpoint() {
		add_rewrite_endpoint('komtet-kassa', EP_ALL);
    }

    public function handle_requests() {
        global $wp;

        if (empty($wp->query_vars['komtet-kassa'])) {
             return;
        }

        $komtet_kassa_action = strtolower(wc_clean( $wp->query_vars['komtet-kassa']));
        do_action('komtet_kassa_action_' . $komtet_kassa_action);
        die(-1);
    }

    public function action_success()
    {
        $this->handle_action('success');
    }

    public function action_fail()
    {
        $this->handle_action('fail');
    }

    public function handle_action($action) {
        global $wp;

        if (!array_key_exists('HTTP_X_HMAC_SIGNATURE', $_SERVER)) {
            status_header(401);
            exit();
        }

        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            status_header(405);
            header('Allow: POST');
            exit();
        }

        if (empty($this->secret_key)) {
			error_log('Unable to handle request: komtetkassa_secret_key is not defined');
			status_header(500);
        }

        $scheme = array_key_exists('HTTPS', $_SERVER) && strtolower($_SERVER['HTTPS']) !== 'off' ? 'https' : 'http';
        $url = sprintf('%s://%s%s', $scheme, $_SERVER['SERVER_NAME'], $_SERVER['REQUEST_URI']);
        $data = file_get_contents('php://input');

        $signature = hash_hmac('md5', $_SERVER['REQUEST_METHOD'] . $url . $data, $this->secret_key);

		if ($signature != $this->request->server['HTTP_X_HMAC_SIGNATURE']) {
            status_header(403);
		 	exit();
        }

        $data = json_decode($data, true);

        foreach (array('external_id', 'state') as $key) {
            if (!array_key_exists($key, $data)) {
                status_header(422);
                header('Content-Type: text/plain');
                echo $key." is required\n";
                exit();
            }
        }
        do_action('komtet_kassa_report_update', intval($data['external_id']), $data['state'], $data);
    }

    public function report_create($order_id, $request_check_data, $response_data, $error="")
    {
        $this->report->create($order_id, $request_check_data, $response_data, $error);
    }

    public function report_update($order_id, $state, $report_data)
    {
        $this->report->update($order_id, $state, $report_data);
    }
}

function Komtet_Kassa() {
	return KomtetKassa::instance();
}

$GLOBALS['komtetkassa'] = Komtet_Kassa();
