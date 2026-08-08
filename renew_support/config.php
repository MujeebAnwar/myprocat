<?php
/**
 * Renew / Support feature config.
 * Pricing / SKU tables sit on top of rooms + room_permissions.
 */

if (!defined('RENEW_SUPPORT_TABLE_PLANS')) {
	define('RENEW_SUPPORT_TABLE_PLANS', 'renew_support_plans');
	define('RENEW_SUPPORT_TABLE_PRODUCTS', 'renew_support_products');
	define('RENEW_SUPPORT_TABLE_SKUS', 'renew_support_skus');
	define('RENEW_SUPPORT_TABLE_SKU_ROOMS', 'renew_support_sku_rooms');
	define('RENEW_SUPPORT_TABLE_ORDERS', 'renew_support_orders');
	define('RENEW_SUPPORT_TABLE_STUDENT_RENEWALS', 'renew_support_student_renewals');
	define('RENEW_SUPPORT_VIEW_SKU_PRICES', 'renew_support_sku_prices');
}

/**
 * Supported license tiers from the pricing sheet.
 */
$RENEW_SUPPORT_TIERS = array(
	'standard' => 'Standard',
	'extended' => 'Extended',
	'premier'  => 'Premier',
);

/**
 * Default room_permissions flags when applying a paid SKU.
 */
$RENEW_SUPPORT_DEFAULT_PERMISSIONS = array(
	'can_read'   => 1,
	'can_upload' => 0,
	'can_remove' => 0,
);
