<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<h2>Настройки</h2>
<div>
	<form method="post" name="settings_form">
		<table class="form-table">
        <tr>
            <th>
                <label>ID магазина:</label>
            </th>
            <td>
                <input type="text" name="komtetkassa_shop_id" value="<?php echo get_option("komtetkassa_shop_id") ?>" />
            </td>
        </tr>
        <tr>
            <th>
                <label>Секретный ключ магазина:</label>
            </th>
            <td>
                <input type="text" name="komtetkassa_secret_key" value="<?php echo get_option("komtetkassa_secret_key") ?>" />
            </td>
        </tr>
        <tr>
            <th>
                <label>Печатать чек:</label>
            </th>
            <td>
                <input type="checkbox" name="komtetkassa_should_print" value="1" <?php echo get_option("komtetkassa_should_print") == "1" ? "checked" : "" ?> />
            </td>
        </tr>
        <tr>
            <th>
                <label>ID очереди:</label>
            </th>
            <td>
                <input type="text" name="komtetkassa_queue_id" value="<?php echo get_option("komtetkassa_queue_id") ?>" />
            </td>
        </tr>
        <tr>
            <th>
                <label for="tax_system">Система налогообложения:</label>
            </th>
            <td>
                <select name="komtetkassa_tax_system" id="tax_system">
                    <?php foreach(Komtet_Kassa()->taxSystems() as $val => $name): ?>
                    <option value="<?php echo $val ?>" <?php echo get_option("komtetkassa_tax_system") == $val ? "selected" : ""  ?>><?php echo $name ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <th>
                <label for="tax_system">Статус заказа при котором будет фискализирован чек:</label>
            </th>
            <td>
                <select id="order_status" name="komtetkassa_fiscalize_on_order_status">
                <?php
                    $statuses = wc_get_order_statuses();
                    foreach ($statuses as $status => $status_name): 
                        $status = str_replace( 'wc-', '', $status);
                    ?>
					<option value="<?php echo esc_attr($status) ?>" <?php echo selected($status, get_option("komtetkassa_fiscalize_on_order_status"), false) ?>><?php echo esc_html($status_name) ?></option>
                <?php endforeach; ?>
				</select>
            </td>
        </tr>
        </table>
        <p class="submit">
            <input class="button button-primary" type="submit" value="Cохранить" name="submit">
        </p>
	</form>
</div>
