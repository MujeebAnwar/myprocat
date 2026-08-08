<?php
require_once __DIR__ . '/../config.php';
require_once DOCUMENT_ROOT . '/setup/start.php';
require_once DOCUMENT_ROOT . '/template/Master.php';
require_once DOCUMENT_ROOT . '/lib/account.php';
require_once __DIR__ . '/helper.php';

if (is_null($UserAccount) || !is_a($UserAccount, 'useraccount') || !$UserAccount->logged_in) {
	header('Location: /signup/login.php');
	exit;
}

$id_user = $UserAccount->user_details['id_user'];
$user_products = renew_support_get_user_products($DB, $id_user);
$owned_keys = renew_support_owned_product_keys($user_products);
$eligible_skus = renew_support_get_eligible_skus($DB, $owned_keys, $id_user);

$form_message = '';
$form_message_class = 'renew-support-notice renew-support-notice--error';

if (isset($_GET['error'])) {
	if ($_GET['error'] === 'invalid_plan') {
		$form_message = 'Please select a valid plan for your account.';
	} else if ($_GET['error'] === 'invalid_amount') {
		$form_message = 'The selected plan amount is invalid. Please contact support.';
	}
}

$selection = renew_support_get_selection();

global $RENEW_SUPPORT_TIERS;

$active_sku_key = '';
if ($selection && !empty($selection['sku_key'])) {
	foreach ($eligible_skus as $sku) {
		if ($sku['sku_key'] === $selection['sku_key']) {
			$active_sku_key = $selection['sku_key'];
			break;
		}
	}
}
if ($active_sku_key === '' && !empty($eligible_skus)) {
	$active_sku_key = $eligible_skus[0]['sku_key'];
}

$tier_order = array('standard', 'extended', 'premier');

ob_start();
?>
<link rel="stylesheet" href="/renew_support/css/renew_support.css">
<div class="renew-support-page">
	<div class="renew-support-header">
		<h1>Renew Support</h1>
		<p>Select a license package, then choose Standard, Extended, or Premier to renew.</p>
	</div>

	<?php if ($form_message !== '') { ?>
		<div class="<?php echo htmlspecialchars($form_message_class, ENT_QUOTES, 'UTF-8'); ?>">
			<?php echo htmlspecialchars($form_message, ENT_QUOTES, 'UTF-8'); ?>
		</div>
	<?php } ?>

	<div class="renew-support-separator"></div>

	<h2 class="renew-support-section-title">Your licenses</h2>
	<?php if (empty($user_products)) { ?>
		<div class="renew-support-empty">
			<p>No renew-support product licenses were found on your account.</p>
			<p><a href="/contact.php">Contact Support</a> if you need help with your license.</p>
		</div>
	<?php } else { ?>
		<div class="renew-support-licenses">
			<?php foreach ($user_products as $product) { ?>
				<div class="renew-support-license-chip">
					<span class="chip-title"><?php echo htmlspecialchars($product['display_name'], ENT_QUOTES, 'UTF-8'); ?></span>
					<span class="chip-meta">Expires <?php echo htmlspecialchars($product['expires_display'], ENT_QUOTES, 'UTF-8'); ?></span>
				</div>
			<?php } ?>
		</div>
	<?php } ?>

	<?php if (!empty($user_products)) { ?>
		<h2 class="renew-support-section-title">Renew current plan</h2>
		<?php if (empty($eligible_skus)) { ?>
			<div class="renew-support-empty">
				<p>No renewal options are available for your current licenses.</p>
				<p><a href="/contact.php">Contact Support</a> for assistance.</p>
			</div>
		<?php } else { ?>
			<div class="renew-support-tabs" id="renewSupportTabs">
				<div class="renew-support-tablist" role="tablist" aria-label="License packages">
					<?php foreach ($eligible_skus as $index => $sku) {
						$isActive = ($sku['sku_key'] === $active_sku_key);
						$tabId = 'renew-tab-' . $sku['sku_key'];
						$panelId = 'renew-panel-' . $sku['sku_key'];
						?>
						<button
							type="button"
							class="renew-support-tab<?php echo $isActive ? ' is-active' : ''; ?>"
							role="tab"
							id="<?php echo htmlspecialchars($tabId, ENT_QUOTES, 'UTF-8'); ?>"
							aria-controls="<?php echo htmlspecialchars($panelId, ENT_QUOTES, 'UTF-8'); ?>"
							aria-selected="<?php echo $isActive ? 'true' : 'false'; ?>"
							data-sku-key="<?php echo htmlspecialchars($sku['sku_key'], ENT_QUOTES, 'UTF-8'); ?>"
						><?php echo htmlspecialchars($sku['display_name'], ENT_QUOTES, 'UTF-8'); ?></button>
					<?php } ?>
				</div>

				<?php foreach ($eligible_skus as $sku) {
					$isActive = ($sku['sku_key'] === $active_sku_key);
					$tabId = 'renew-tab-' . $sku['sku_key'];
					$panelId = 'renew-panel-' . $sku['sku_key'];
					$desc = 'Renew your current license package.';
					if (!empty($sku['notes'])) {
						$desc = $sku['notes'];
					}
					?>
					<div
						class="renew-support-tabpanel<?php echo $isActive ? ' is-active' : ''; ?>"
						role="tabpanel"
						id="<?php echo htmlspecialchars($panelId, ENT_QUOTES, 'UTF-8'); ?>"
						aria-labelledby="<?php echo htmlspecialchars($tabId, ENT_QUOTES, 'UTF-8'); ?>"
						<?php echo $isActive ? '' : 'hidden'; ?>
					>
						<div class="renew-support-tabpanel-header">
							<!-- <div class="panel-plan">Plan <?php echo htmlspecialchars($sku['plan_label'], ENT_QUOTES, 'UTF-8'); ?></div> -->
							<h3><?php echo htmlspecialchars($sku['display_name'], ENT_QUOTES, 'UTF-8'); ?></h3>
							<!-- <p><?php echo htmlspecialchars($desc, ENT_QUOTES, 'UTF-8'); ?></p> -->
						</div>

						<?php
						$available_tiers = array();
						foreach ($tier_order as $tierKey) {
							if (isset($sku['tiers'][$tierKey])) {
								$available_tiers[] = $tierKey;
							}
						}
						$tier_count = count($available_tiers);
						?>
						<div class="renew-support-cards renew-support-cards--count-<?php echo (int)$tier_count; ?>">
							<?php
							$tierIndex = 0;
							foreach ($available_tiers as $tierKey) {
								$tierPrice = $sku['tiers'][$tierKey];
								$tierLabel = isset($RENEW_SUPPORT_TIERS[$tierKey]) ? $RENEW_SUPPORT_TIERS[$tierKey] : ucfirst($tierKey);
								$isAccent = ($tier_count > 1 && $tierIndex === 1);
								$cardClass = 'renew-support-card';
								if ($isAccent) {
									$cardClass .= ' renew-support-card--accent';
								}
								$features = array();
								if (isset($sku['features']) && is_array($sku['features'])) {
									$features = renew_support_features_for_tier($sku['features'], $tierKey);
								}
								$tierIndex++;
								?>
								<form method="post" action="/renew_support/process_payment.php" class="<?php echo $cardClass; ?>">
									<input type="hidden" name="sku_key" value="<?php echo htmlspecialchars($sku['sku_key'], ENT_QUOTES, 'UTF-8'); ?>">
									<input type="hidden" name="tier" value="<?php echo htmlspecialchars($tierKey, ENT_QUOTES, 'UTF-8'); ?>">

									<?php if ($isAccent) { ?>
										<div class="renew-support-badge">Recommended</div>
									<?php } ?>

									<h3><?php echo htmlspecialchars($tierLabel, ENT_QUOTES, 'UTF-8'); ?></h3>
									<?php
									$addonAmount = isset($sku['addon_amount']) ? (float)$sku['addon_amount'] : 0.0;
									$showSecondSeatAddon = !empty($sku['addon_plan_code']) && $sku['addon_plan_code'] === 'B' && $addonAmount > 0;
									$displayPrice = $showSecondSeatAddon
										? ((float)$tierPrice - $addonAmount)
										: (float)$tierPrice;
									?>
									<div class="card-price">$<?php echo number_format($displayPrice, 2); ?></div>
									<?php if ($showSecondSeatAddon) { ?>
										<div class="card-addon-label">+ $<?php echo number_format($addonAmount, 0); ?></div>
									<?php } ?>

									<ul class="card-features">
										<?php foreach ($features as $feature) { ?>
											<li>✓ <?php echo htmlspecialchars($feature, ENT_QUOTES, 'UTF-8'); ?></li>
										<?php } ?>
									</ul>

									<div class="card-actions">
										<button type="submit" class="renew-support-select-btn">Renew <?php echo htmlspecialchars($tierLabel, ENT_QUOTES, 'UTF-8'); ?></button>
									</div>
								</form>
							<?php } ?>
						</div>
					</div>
				<?php } ?>
			</div>
		<?php } ?>
	<?php } ?>

	
</div>
<script type="text/javascript">
(function () {
	var root = document.getElementById('renewSupportTabs');
	if (!root) return;

	var tabs = root.querySelectorAll('.renew-support-tab');
	var panels = root.querySelectorAll('.renew-support-tabpanel');

	function activateTab(tab) {
		var skuKey = tab.getAttribute('data-sku-key');
		tabs.forEach(function (t) {
			var active = t === tab;
			t.classList.toggle('is-active', active);
			t.setAttribute('aria-selected', active ? 'true' : 'false');
		});
		panels.forEach(function (panel) {
			var match = panel.id === ('renew-panel-' + skuKey);
			panel.classList.toggle('is-active', match);
			if (match) {
				panel.removeAttribute('hidden');
			} else {
				panel.setAttribute('hidden', 'hidden');
			}
		});
	}

	tabs.forEach(function (tab) {
		tab.addEventListener('click', function () {
			activateTab(tab);
		});
	});
})();
</script>
<?php
$page_html = ob_get_clean();

$set_title = 'Renew Support - MyProCAT';
$sidebar_title = 'Renew Support';
$page_banner = new content_block(NULL, 'div', array('class' => 'banner'));
$page_banner->push(new content_block('Renew Support', 'h1'));

$set_body = new content_block(NULL, 'div', array('class' => 'renew-support-wrap', 'style' => 'width: 100%;'));
$set_body->push(new content_block($page_html, 'raw'));

$breadcrumb_items = array(
	array('text' => 'Home', 'url' => '/resources.php'),
	array('text' => 'Renew Support', 'url' => '/renew_support/'),
);

require_once DOCUMENT_ROOT . '/templateV2/mainframe/mainframe.php';
?>
