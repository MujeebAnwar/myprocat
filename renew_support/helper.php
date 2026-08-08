<?php
/**
 * Renew / Support helpers.
 * Entitlements come from rooms + room_permissions; pricing from renew_support_* tables.
 */

require_once __DIR__ . '/config.php';

/**
 * Active renew-support products the user can currently read.
 *
 * @return array<int, array{product_key:string,display_name:string,room_title:string,product_role:string,expires:string,expires_display:string,id_room:int|null}>
 */
function renew_support_get_user_products($DB, $id_user)
{
	$columns = array(
		'product_key',
		'display_name',
		'room_title',
		'product_role',
		'expires',
		'expires_display',
		'id_room',
	);
	$ok = $DB->sql(
		'SELECT
			p.product_key,
			p.display_name,
			p.room_title,
			p.product_role,
			rp.expires,
			DATE_FORMAT(rp.expires + INTERVAL 3 DAY, \'%b %e, %Y\') AS expires_display,
			COALESCE(p.id_room, r.id_room) AS id_room
		FROM room_permissions rp
		INNER JOIN rooms r ON r.id_room = rp.id_room
		INNER JOIN ' . RENEW_SUPPORT_TABLE_PRODUCTS . ' p
			ON p.room_title = r.room_title AND p.is_active = 1
		WHERE rp.id_user = ?
			AND rp.can_read = 1
			AND (rp.expires + INTERVAL 3 DAY) > NOW()
		ORDER BY p.sort_order ASC',
		array('s', $id_user),
		$columns
	);

	if ($ok === false || empty($columns)) {
		return array();
	}

	$products = array();
	foreach ($columns as $row) {
		if (!is_array($row) || !isset($row['product_key']) || $row['product_key'] === 'product_key') {
			continue;
		}
		$products[] = $row;
	}
	return $products;
}

/**
 * @param array $user_products from renew_support_get_user_products()
 * @return string[]
 */
function renew_support_owned_product_keys(array $user_products)
{
	$keys = array();
	foreach ($user_products as $product) {
		if (!empty($product['product_key'])) {
			$keys[] = $product['product_key'];
		}
	}
	return array_values(array_unique($keys));
}

/**
 * Map owned primary products → candidate sku_key list (renew current only).
 *
 * @param string[] $owned_keys
 * @return string[]
 */
function renew_support_candidate_sku_keys(array $owned_keys)
{
	$owned = array_fill_keys($owned_keys, true);
	$candidates = array();

	if (isset($owned['winner_xp'])) {
		$candidates = array_merge($candidates, array(
			'winner_xp_only',
			'winner_xp_subscription',
			'winner_xp_captivision',
			'winner_xp_subscription_captivision',
			'winner_xp_plus_xp2',
		));
	}
	if (isset($owned['winner_vr'])) {
		$candidates = array_merge($candidates, array(
			'winner_vr_only',
			'winner_vr_subscription',
			'winner_vr_captivision',
			'winner_vr_subscription_captivision',
			'winner_vr_plus_vr2',
		));
	}
	if (isset($owned['edit_only'])) {
		$candidates = array_merge($candidates, array(
			'edit_only',
			'edit_only_subscription',
		));
	}
	if (isset($owned['student'])) {
		$candidates[] = 'student';
	}
	if (isset($owned['student_xp'])) {
		$candidates[] = 'student_xp';
	}
	if (isset($owned['student_vr'])) {
		$candidates[] = 'student_vr';
	}

	return array_values(array_unique($candidates));
}

/**
 * Keep only fully-owned SKUs that are not a strict subset of another fully-owned SKU.
 * So a user with XP + Subscription renews that package, not also "Winner XP only".
 *
 * @param array<int, array> $skus
 * @return array<int, array>
 */
function renew_support_filter_maximal_current_skus(array $skus)
{
	$kept = array();
	foreach ($skus as $sku) {
		$keys = isset($sku['granted_product_keys']) ? $sku['granted_product_keys'] : array();
		$isSubset = false;
		foreach ($skus as $other) {
			if ($other['sku_key'] === $sku['sku_key']) {
				continue;
			}
			$otherKeys = isset($other['granted_product_keys']) ? $other['granted_product_keys'] : array();
			if (count($otherKeys) <= count($keys)) {
				continue;
			}
			$coversAll = true;
			foreach ($keys as $key) {
				if (!in_array($key, $otherKeys, true)) {
					$coversAll = false;
					break;
				}
			}
			if ($coversAll) {
				$isSubset = true;
				break;
			}
		}
		if (!$isSubset) {
			$sku['is_current'] = true;
			$sku['is_upgrade'] = false;
			$kept[] = $sku;
		}
	}
	return $kept;
}

/**
 * Student renewal row for a product key, or defaults when table/row missing.
 *
 * @return array{renewal_count:int,terminated:int}
 */
function renew_support_get_student_renewal_state($DB, $id_user, $product_key)
{
	$default = array('renewal_count' => 0, 'terminated' => 0);
	$columns = array('renewal_count', 'terminated');
	$ok = @$DB->sql(
		'SELECT renewal_count, terminated
		 FROM ' . RENEW_SUPPORT_TABLE_STUDENT_RENEWALS . '
		 WHERE id_user = ? AND product_key = ?
		 LIMIT 1',
		array('ss', $id_user, $product_key),
		$columns
	);
	if ($ok === false || empty($columns)) {
		return $default;
	}
	foreach ($columns as $row) {
		if (!is_array($row) || !isset($row['renewal_count']) || $row['renewal_count'] === 'renewal_count') {
			continue;
		}
		return array(
			'renewal_count' => (int)$row['renewal_count'],
			'terminated' => (int)$row['terminated'],
		);
	}
	return $default;
}

/**
 * Load SKU room product_keys for many sku ids.
 *
 * @param int[] $sku_ids
 * @return array<int, string[]> sku_id => product_keys
 */
function renew_support_load_sku_room_map($DB, array $sku_ids)
{
	$map = array();
	if (empty($sku_ids)) {
		return $map;
	}

	$placeholders = implode(',', array_fill(0, count($sku_ids), '?'));
	$types = str_repeat('i', count($sku_ids));
	$params = array_merge(array($types), array_map('intval', $sku_ids));

	$columns = array('sku_id', 'product_key');
	$ok = $DB->sql(
		'SELECT sr.sku_id, p.product_key
		 FROM ' . RENEW_SUPPORT_TABLE_SKU_ROOMS . ' sr
		 INNER JOIN ' . RENEW_SUPPORT_TABLE_PRODUCTS . ' p ON p.id = sr.product_id
		 WHERE sr.sku_id IN (' . $placeholders . ')
		 ORDER BY sr.sort_order ASC',
		$params,
		$columns
	);

	if ($ok === false || empty($columns)) {
		return $map;
	}

	foreach ($columns as $row) {
		if (!is_array($row) || !isset($row['sku_id']) || $row['sku_id'] === 'sku_id') {
			continue;
		}
		$skuId = (int)$row['sku_id'];
		if (!isset($map[$skuId])) {
			$map[$skuId] = array();
		}
		$map[$skuId][] = $row['product_key'];
	}
	return $map;
}

/**
 * Price row for a SKU — prefer view, fall back to plan join.
 *
 * @return array|null
 */
function renew_support_load_sku_price_row($DB, $sku_key)
{
	$columns = array(
		'sku_key',
		'display_name',
		'pricing_column',
		'base_plan_code',
		'addon_plan_code',
		'addon_standard',
		'total_standard',
		'total_extended',
		'total_premier',
		'requires_active_product_key',
		'max_renewals',
		'is_active',
	);

	$ok = @$DB->sql(
		'SELECT
			sku_key, display_name, pricing_column, base_plan_code, addon_plan_code,
			addon_standard, total_standard, total_extended, total_premier,
			requires_active_product_key, max_renewals, is_active
		 FROM ' . RENEW_SUPPORT_VIEW_SKU_PRICES . '
		 WHERE sku_key = ?
		 LIMIT 1',
		array('s', $sku_key),
		$columns
	);

	if ($ok !== false && !empty($columns)) {
		foreach ($columns as $row) {
			if (is_array($row) && isset($row['sku_key']) && $row['sku_key'] !== 'sku_key') {
				return $row;
			}
		}
	}

	$fallback = array(
		'sku_key',
		'display_name',
		'pricing_column',
		'base_plan_code',
		'addon_plan_code',
		'addon_standard',
		'total_standard',
		'total_extended',
		'total_premier',
		'requires_active_product_key',
		'max_renewals',
		'is_active',
	);
	$ok = $DB->sql(
		'SELECT
			s.sku_key,
			s.display_name,
			s.pricing_column,
			s.base_plan_code,
			s.addon_plan_code,
			COALESCE(ap.standard_price, 0) AS addon_standard,
			(bp.standard_price + COALESCE(ap.standard_price, 0)) AS total_standard,
			CASE WHEN bp.extended_price IS NULL THEN NULL
				ELSE bp.extended_price + COALESCE(ap.standard_price, 0) END AS total_extended,
			CASE WHEN bp.premier_price IS NULL THEN NULL
				ELSE bp.premier_price + COALESCE(ap.standard_price, 0) END AS total_premier,
			s.requires_active_product_key,
			s.max_renewals,
			s.is_active
		FROM ' . RENEW_SUPPORT_TABLE_SKUS . ' s
		INNER JOIN ' . RENEW_SUPPORT_TABLE_PLANS . ' bp ON bp.plan_code = s.base_plan_code
		LEFT JOIN ' . RENEW_SUPPORT_TABLE_PLANS . ' ap ON ap.plan_code = s.addon_plan_code
		WHERE s.sku_key = ? AND s.is_active = 1
		LIMIT 1',
		array('s', $sku_key),
		$fallback
	);

	if ($ok === false || empty($fallback)) {
		return null;
	}
	foreach ($fallback as $row) {
		if (is_array($row) && isset($row['sku_key']) && $row['sku_key'] !== 'sku_key') {
			return $row;
		}
	}
	return null;
}

/**
 * @param array $sku_price_row
 * @param string $tier standard|extended|premier
 * @return float|null
 */
function renew_support_price_for_tier($sku_price_row, $tier)
{
	$field = 'total_' . $tier;
	if (!isset($sku_price_row[$field]) || $sku_price_row[$field] === '' || $sku_price_row[$field] === null) {
		return null;
	}
	return (float)$sku_price_row[$field];
}

/**
 * Decode plan features_json into tier => string[] map.
 * Accepts either:
 *   {"standard":["..."],"extended":["..."],"premier":["..."]}
 * or a flat list:
 *   ["Standard support coverage","Renews your current license package"]
 *
 * @param string|null $json
 * @return array<string, string[]>
 */
function renew_support_decode_features_json($json)
{
	$empty = array(
		'standard' => array(),
		'extended' => array(),
		'premier' => array(),
	);
	if ($json === null || $json === '') {
		return $empty;
	}

	$decoded = json_decode($json, true);
	if (!is_array($decoded)) {
		return $empty;
	}

	// Flat list of strings → apply to every tier.
	if (array_keys($decoded) === range(0, count($decoded) - 1)) {
		$list = array();
		foreach ($decoded as $item) {
			if (is_string($item) && trim($item) !== '') {
				$list[] = trim($item);
			}
		}
		return array(
			'standard' => $list,
			'extended' => $list,
			'premier' => $list,
		);
	}

	$result = $empty;
	foreach (array('standard', 'extended', 'premier') as $tier) {
		if (!isset($decoded[$tier]) || !is_array($decoded[$tier])) {
			continue;
		}
		$list = array();
		foreach ($decoded[$tier] as $item) {
			if (is_string($item) && trim($item) !== '') {
				$list[] = trim($item);
			}
		}
		$result[$tier] = $list;
	}
	return $result;
}

/**
 * Feature list for one tier from a decoded features map.
 *
 * @param array<string, string[]> $features_by_tier
 * @param string $tier
 * @return string[]
 */
function renew_support_features_for_tier(array $features_by_tier, $tier)
{
	if (!isset($features_by_tier[$tier]) || !is_array($features_by_tier[$tier])) {
		return array();
	}
	return $features_by_tier[$tier];
}

/**
 * Load and decode features_json for a plan code.
 *
 * @return array<string, string[]>
 */
function renew_support_get_plan_features($DB, $plan_code)
{
	$columns = array('features_json');
	$ok = $DB->sql(
		'SELECT features_json
		 FROM ' . RENEW_SUPPORT_TABLE_PLANS . '
		 WHERE plan_code = ?
		 LIMIT 1',
		array('s', $plan_code),
		$columns
	);
	if ($ok === false || empty($columns)) {
		return renew_support_decode_features_json(null);
	}
	foreach ($columns as $row) {
		if (!is_array($row) || !array_key_exists('features_json', $row) || $row['features_json'] === 'features_json') {
			continue;
		}
		return renew_support_decode_features_json($row['features_json']);
	}
	return renew_support_decode_features_json(null);
}

/**
 * Available tiers with prices for a SKU price row.
 *
 * @return array<string, float> tier => amount
 */
function renew_support_available_tiers($sku_price_row)
{
	global $RENEW_SUPPORT_TIERS;
	$tiers = array();
	foreach (array_keys($RENEW_SUPPORT_TIERS) as $tier) {
		$price = renew_support_price_for_tier($sku_price_row, $tier);
		if ($price !== null) {
			$tiers[$tier] = $price;
		}
	}
	return $tiers;
}

/**
 * Eligible SKUs for renewing the user's current package only (no upgrades).
 *
 * @param string[] $owned_product_keys
 * @param string|null $id_user needed for student renewal checks
 * @return array<int, array>
 */
function renew_support_get_eligible_skus($DB, array $owned_product_keys, $id_user = null)
{
	$candidateKeys = renew_support_candidate_sku_keys($owned_product_keys);
	if (empty($candidateKeys)) {
		return array();
	}

	$ownedLookup = array_fill_keys($owned_product_keys, true);
	$placeholders = implode(',', array_fill(0, count($candidateKeys), '?'));
	$types = str_repeat('s', count($candidateKeys));
	$params = array_merge(array($types), $candidateKeys);

	$columns = array(
		'id',
		'sku_key',
		'display_name',
		'pricing_column',
		'base_plan_code',
		'addon_plan_code',
		'requires_active_product_key',
		'max_renewals',
		'notes',
		'sort_order',
	);
	$ok = $DB->sql(
		'SELECT
			id, sku_key, display_name, pricing_column, base_plan_code, addon_plan_code,
			requires_active_product_key, max_renewals, notes, sort_order
		 FROM ' . RENEW_SUPPORT_TABLE_SKUS . '
		 WHERE is_active = 1 AND sku_key IN (' . $placeholders . ')
		 ORDER BY sort_order ASC',
		$params,
		$columns
	);

	if ($ok === false || empty($columns)) {
		return array();
	}

	$skuRows = array();
	$skuIds = array();
	foreach ($columns as $row) {
		if (!is_array($row) || !isset($row['sku_key']) || $row['sku_key'] === 'sku_key') {
			continue;
		}
		$skuRows[] = $row;
		$skuIds[] = (int)$row['id'];
	}

	$roomMap = renew_support_load_sku_room_map($DB, $skuIds);
	$eligible = array();

	foreach ($skuRows as $row) {
		$skuId = (int)$row['id'];
		$skuKey = $row['sku_key'];
		$requiredKey = isset($row['requires_active_product_key']) ? trim((string)$row['requires_active_product_key']) : '';

		if ($requiredKey !== '' && !isset($ownedLookup[$requiredKey])) {
			continue;
		}

		$maxRenewals = isset($row['max_renewals']) && $row['max_renewals'] !== '' && $row['max_renewals'] !== null
			? (int)$row['max_renewals']
			: null;

		if ($maxRenewals !== null && $id_user !== null) {
			$studentProductKey = 'student';
			if ($skuKey === 'student_xp') {
				$studentProductKey = 'student_xp';
			} else if ($skuKey === 'student_vr') {
				$studentProductKey = 'student_vr';
			}
			$state = renew_support_get_student_renewal_state($DB, $id_user, $studentProductKey);
			if (!empty($state['terminated']) || (int)$state['renewal_count'] >= $maxRenewals) {
				continue;
			}
		}

		$grantedKeys = isset($roomMap[$skuId]) ? $roomMap[$skuId] : array();
		if (empty($grantedKeys)) {
			continue;
		}

		// Renew current only: every room in the SKU must already be owned.
		$missingKeys = array();
		foreach ($grantedKeys as $productKey) {
			if (!isset($ownedLookup[$productKey])) {
				$missingKeys[] = $productKey;
			}
		}
		if (!empty($missingKeys)) {
			continue;
		}

		$priceRow = renew_support_load_sku_price_row($DB, $skuKey);
		if ($priceRow === null) {
			continue;
		}
		$tiers = renew_support_available_tiers($priceRow);
		if (empty($tiers)) {
			continue;
		}

		$planLabel = $row['base_plan_code'];
		if (!empty($row['addon_plan_code'])) {
			$planLabel .= ' + ' . $row['addon_plan_code'];
		}

		$features = renew_support_get_plan_features($DB, $row['base_plan_code']);
		$addonAmount = 0.0;
		if (
			!empty($row['addon_plan_code'])
			&& isset($priceRow['addon_standard'])
			&& $priceRow['addon_standard'] !== ''
			&& $priceRow['addon_standard'] !== null
		) {
			$addonAmount = (float)$priceRow['addon_standard'];
		}

		$eligible[] = array(
			'id' => $skuId,
			'sku_key' => $skuKey,
			'display_name' => $row['display_name'],
			'pricing_column' => $row['pricing_column'],
			'base_plan_code' => $row['base_plan_code'],
			'addon_plan_code' => $row['addon_plan_code'],
			'plan_label' => $planLabel,
			'notes' => isset($row['notes']) ? $row['notes'] : '',
			'sort_order' => (int)$row['sort_order'],
			'granted_product_keys' => $grantedKeys,
			'missing_product_keys' => array(),
			'is_current' => true,
			'is_upgrade' => false,
			'tiers' => $tiers,
			'features' => $features,
			'addon_amount' => $addonAmount,
			'max_renewals' => $maxRenewals,
			'price_row' => $priceRow,
		);
	}

	return renew_support_filter_maximal_current_skus($eligible);
}

function renew_support_save_selection($sku_key, $tier, $amount, $display_name = '')
{
	if (session_status() === PHP_SESSION_NONE) {
		session_start();
	}
	$_SESSION['renew_support'] = array(
		'sku_key' => $sku_key,
		'tier' => $tier,
		'amount' => (float)$amount,
		'display_name' => $display_name,
		'selected_at' => date('c'),
	);
}

/**
 * @return array|null
 */
function renew_support_get_selection()
{
	if (session_status() === PHP_SESSION_NONE) {
		session_start();
	}
	if (empty($_SESSION['renew_support']) || !is_array($_SESSION['renew_support'])) {
		return null;
	}
	return $_SESSION['renew_support'];
}

/**
 * Find eligible SKU by key.
 *
 * @param array $eligible
 * @return array|null
 */
function renew_support_find_eligible_sku(array $eligible, $sku_key)
{
	foreach ($eligible as $sku) {
		if (isset($sku['sku_key']) && $sku['sku_key'] === $sku_key) {
			return $sku;
		}
	}
	return null;
}
