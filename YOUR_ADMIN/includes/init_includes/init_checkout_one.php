<?php
// -----
// Part of the One-Page Checkout plugin, provided under GPL 2.0 license by lat9 (cindy@vinosdefrutastropicales.com).
// Copyright (C) 2013-2016, Vinos de Frutas Tropicales.  All rights reserved.
//
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

define ('CHECKOUT_ONE_CURRENT_VERSION', '1.0.8-beta1');
define ('CHECKOUT_ONE_CURRENT_UPDATE_DATE', '2016-10-xx');
$version_release_date = CHECKOUT_ONE_CURRENT_VERSION . ' (' . CHECKOUT_ONE_CURRENT_UPDATE_DATE . ')';

$configurationGroupTitle = 'One-Page Checkout Settings';
$configuration = $db->Execute("SELECT configuration_group_id FROM " . TABLE_CONFIGURATION_GROUP . " WHERE configuration_group_title = '$configurationGroupTitle' LIMIT 1");
if ($configuration->EOF) {
    $db->Execute("INSERT INTO " . TABLE_CONFIGURATION_GROUP . " 
                 (configuration_group_title, configuration_group_description, sort_order, visible) 
                 VALUES ('$configurationGroupTitle', '$configurationGroupTitle', '1', '1');");
    $cgi = $db->Insert_ID(); 
    $db->Execute ("UPDATE " . TABLE_CONFIGURATION_GROUP . " SET sort_order = $cgi WHERE configuration_group_id = $cgi");
} else {
    $cgi = $configuration->fields['configuration_group_id'];
}

// -----
// Set the various configuration items, the plugin wasn't previously installed.
//
if (!defined ('CHECKOUT_ONE_ENABLED')) {
    $db->Execute ("INSERT INTO " . TABLE_CONFIGURATION . " ( configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, date_added, sort_order, use_function, set_function ) VALUES ( 'Enable One-Page Checkout?', 'CHECKOUT_ONE_ENABLED', 'false', 'Enable the one-page checkout processing for your store?  Default: <b>false</b>', $cgi, now(), 10, NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),')");
    
    define ('CHECKOUT_ONE_ENABLED', 'false');
    
    $db->Execute ("INSERT INTO " . TABLE_CONFIGURATION . " ( configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, date_added, sort_order, use_function, set_function ) VALUES ( 'Enable One-Page Checkout Debug?', 'CHECKOUT_ONE_DEBUG', 'false', 'When enabled, debug files named myDEBUG-one_page_checkout-<em>xx</em>.log are created in your /logs folder (<em>xx</em> is the customer_id for the checkout).  Use the <b>true</b> setting in combination with the <em>Debug: Customer List</em> setting to limit the customers for which the debug-action is taken.<br /><br />Default: <b>false</b>', $cgi, now(), 50, NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),')");
    
    $db->Execute ("INSERT INTO " . TABLE_CONFIGURATION . " ( configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, date_added, sort_order, use_function, set_function ) VALUES ( 'Debug: Customer List', 'CHECKOUT_ONE_DEBUG_EXTRA', '', 'When you enable the plugin\'s debug, use this setting to limit the customers for which the debug-logs are generated.  Leave the setting blank (the default) to debug <b>all</b> customers or identify a comma-separated list of customer_id values to limit the debug to just those customers.<br />', $cgi, now(), 51, NULL, NULL)");
} elseif (!defined ('CHECKOUT_ONE_MODULE_VERSION')) {
    $db->Execute ("UPDATE " . TABLE_CONFIGURATION . " SET configuration_group_id = $cgi WHERE configuration_key LIKE 'CHECKOUT_ONE_%'");
}

if (!defined ('CHECKOUT_ONE_MODULE_VERSION')) {
    $db->Execute ("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, date_added, sort_order, set_function) VALUES ('Version/Release Date', 'CHECKOUT_ONE_MODULE_VERSION', '" . $version_release_date . "', 'The One-Page Checkout version number and release date.', $cgi, now(), 1, 'trim(')");
    define ('CHECKOUT_ONE_MODULE_VERSION', $version_release_date);
}

if (!defined ('CHECKOUT_ONE_SHIPPING_TIMEOUT')) {
    $db->Execute ("INSERT INTO " . TABLE_CONFIGURATION . " ( configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, date_added, sort_order, use_function, set_function ) VALUES ( 'Update Shipping AJAX Time-out', 'CHECKOUT_ONE_SHIPPING_TIMEOUT', '5000', 'Enter the timeout to use for the plugin\'s request to update the shipping quotes on the &quot;checkout_one&quot; page. The default setting of 5000 (5 seconds) <em>should work</em> for most stores.  If your store has enabled multiple external shipping methods (e.g. USPS, UPS <b>and</b> FedEx), you might need to increase this value.<br />', $cgi, now(), 15, NULL, NULL)");
}

if (CHECKOUT_ONE_MODULE_VERSION != $version_release_date) {
    $db->Execute ("UPDATE " . TABLE_CONFIGURATION . " SET configuration_value = '$version_release_date', last_modified = now() WHERE configuration_key = 'CHECKOUT_ONE_MODULE_VERSION' LIMIT 1");
}

// -----
// If not already updated, update the configuration of the plugin's debug setting.  Starting with v1.0.1, there are now three settings.
//
if (defined ('CHECKOUT_ONE_DEBUG') && strpos (CHECKOUT_ONE_DEBUG, '<b>full</b>') === false) {
    $db->Execute (
        "UPDATE " . TABLE_CONFIGURATION . " 
            SET configuration_description = 'When enabled, debug files named myDEBUG-one_page_checkout-<em>xx</em>.log are created in your /logs folder (<em>xx</em> is the customer_id for the checkout).  Use the <b>true</b> or <b>full</b> settings in combination with the <em>Debug: Customer List</em> setting to limit the customers for which the debug-action is taken.  Setting the value to <b>full</b> will also set the PHP error-level for the checkout so that <b>all</b> PHP errors are logged.<br /><br />Default: <b>false</b>',
                set_function = 'zen_cfg_select_option(array(\'true\', \'false\', \'full\'),'
          WHERE configuration_key = 'CHECKOUT_ONE_DEBUG' LIMIT 1"
    );
}

// -----
// Register the plugin's configuration page for display on the menus.
//
if (!zen_page_key_exists ('configOnePageCheckout')) {
    $next_sort = $db->Execute ('SELECT MAX(sort_order) as max_sort FROM ' . TABLE_ADMIN_PAGES . " WHERE menu_key='configuration'", false, false, 0, true);
    zen_register_admin_page ('configOnePageCheckout', 'BOX_TOOLS_CHECKOUT_ONE', 'FILENAME_CONFIGURATION', "gID=$cgi", 'configuration', 'Y', $next_sort->fields['max_sort'] + 1);
}

// -----
// Now, check to make sure that the currently-active template's folder includes the jscript_framework.php file and disable the One-Page Checkout if
// that file's not found.
//
$template_check = $db->Execute ("SELECT DISTINCT template_dir FROM " . TABLE_TEMPLATE_SELECT);
while (!$template_check->EOF) {
    $jscript_dir = DIR_FS_CATALOG . 'includes/templates/' . $template_check->fields['template_dir'] . '/jscript';
    if (CHECKOUT_ONE_ENABLED !== 'false' && !is_dir ($jscript_dir) || !file_exists ("$jscript_dir/jscript_framework.php")) {
        $db->Execute ("UPDATE " . TABLE_CONFIGURATION . " SET configuration_value = 'false' WHERE configuration_key = 'CHECKOUT_ONE_ENABLED' LIMIT 1");
        $messageStack->add_session (sprintf (ERROR_STORESIDE_CONFIG, "$jscript_dir/jscript_framework.php"), 'error');
        break;
    }
    $template_check->MoveNext();
}