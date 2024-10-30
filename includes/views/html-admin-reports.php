<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<h2>Отчеты</h2>

<?php 
    $reports_list->prepare_items();
    $reports_list->display();
?>