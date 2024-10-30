<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

global $wpdb;

include_once(dirname( __FILE__ ).'/includes/class-komtetkassa-install.php');
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}komtetkassa_reports");
delete_option(KomtetKassa_Install::OPTION_DB_VERSION_KEY);
