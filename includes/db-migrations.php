<?php

if (!defined('ABSPATH')) {
    exit;
}

function komtetkassa_0001_create_tables() {
    global $wpdb;

    $collate = $wpdb->get_charset_collate();

    $tables = "
CREATE TABLE IF NOT EXISTS {$wpdb->prefix}komtetkassa_reports (
    report_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW() ON UPDATE now(),
    order_id BIGINT UNSIGNED NOT NULL,
    status varchar(16) NOT NULL DEFAULT 'new',
    request_data TEXT DEFAULT NULL,
    response_data TEXT DEFAULT NULL,
    report_data TEXT DEFAULT NULL,
    error TEXT DEFAULT NULL,
    PRIMARY KEY (report_id)
) $collate";
    require_once(ABSPATH.'wp-admin/includes/upgrade.php');
    dbDelta($tables);
}
