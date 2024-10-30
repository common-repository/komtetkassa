<?php

final class KomtetKassa_Admin {

    public static function init() {
        add_action('admin_head', array(__CLASS__, 'menu_correction'));
        add_action('admin_menu', array(__CLASS__, 'add_menu'));
        add_action('admin_menu', array(__CLASS__, 'add_menu_settings'));
        add_action('admin_menu', array(__CLASS__, 'add_menu_reports'));

        require_once(KOMTETKASSA_ABSPATH . 'includes/class-komtetkassa-admin-settings.php');
        require_once(KOMTETKASSA_ABSPATH . 'includes/class-komtetkassa-admin-reports.php');
    }

    public static function add_menu() {
        add_menu_page(
            'KOMTET Касса',
            'KOMTET Касса',
            'manage_options',
            'komtetkassa',
            array(__CLASS__, 'settings_page'),
            null,
            '56.2'
        );
    }

    public static function add_menu_settings() {
        add_submenu_page(
            'komtetkassa',
            'KOMTET Касса - Настройки',
            'Настройки',
            'manage_options',
            'komtetkassa-settings',
            array(__CLASS__, 'settings_page')
        );
    }

    public static function add_menu_reports() {
        add_submenu_page(
            'komtetkassa',
            'KOMTET Касса - Отчеты',
            'Отчеты',
            'manage_options',
            'komtetkassa-reports',
            array(__CLASS__, 'reports_page')
        );
    }

    public static function menu_correction() {
        global $submenu;

        if (isset($submenu['komtetkassa'])) {
            unset($submenu['komtetkassa'][0]);
        }
    }

    public static function settings_page() {
        KomtetKassa_AdminSettings::out();
    }

    public static function reports_page() {
        KomtetKassa_AdminReports::out();
    }
}
