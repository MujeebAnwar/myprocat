<?php
/**
 * Subscription Plans Configuration
 * 
 * This file fetches plan data from the database and combines it with
 * hardcoded features, card_features, and feature_rows.
 */

require_once(DOCUMENT_ROOT . '/lib/database.php');
require_once (DOCUMENT_ROOT.'/setup/start.php');
/**
 * Fetch subscription plans from database
 */
function getSubscriptionPlansFromDB() {
    $DB = new databaseI();
    
    // Fetch all active plans ordered by sort_order
    $sql = "SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY sort_order ASC";

    $stmt = $DB->_DB->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $plans = array();
    while ($row = $result->fetch_assoc()) {
        $plans[] = $row;
    }
    $stmt->close();
    
    return $plans;
}

/**
 * Fetch pricing tiers for a specific plan
 */
function getPricingTiersFromDB($planId) {
    $DB = new databaseI();
    
    $sql = "SELECT billing_type, min_hours, max_hours, rate FROM subscription_pricing_tiers WHERE plan_id = ? ORDER BY billing_type, min_hours";
    
    $stmt = $DB->_DB->prepare($sql);
    $stmt->bind_param('s', $planId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $tiers = array('monthly' => array(), 'annual' => array());
    while ($row = $result->fetch_assoc()) {
        $tiers[$row['billing_type']][] = array(
            'min' => (int)$row['min_hours'],
            'max' => (int)$row['max_hours'],
            'rate' => (float)$row['rate']
        );
    }
    $stmt->close();
    
    return $tiers;
}

/**
 * Fetch annual payment options from database
 */
function getAnnualPaymentOptionsFromDB() {
    $DB = new databaseI();
    
    $sql = "SELECT option_key, label, discount_percent, discount_label FROM annual_payment_options";
    
    $stmt = $DB->_DB->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $options = array();
    while ($row = $result->fetch_assoc()) {
        $options[$row['option_key']] = array(
            'label' => $row['label'],
            'discount_percent' => (float)$row['discount_percent'],
            'discount_label' => $row['discount_label']
        );
    }
    $stmt->close();
    
    return $options;
}

/**
 * Hardcoded features for each plan
 */
function getHardcodedFeatures() {
    return array(
        'classic' => array(
            'speaker_diarization' => 'check',
            'post_production' => 'check',
            'realtime_reporting' => 'check',
            'teleconferencing_scheduling' => 'check',
            'winner_lite_cat' => 'check',
            'deposnap_recorder' => 'check',
            'transcript_templates' => 'Standard template',
            'summaries' => '-',
            'custom_queries' => '-',
            'spanish_transcription' => '-',
            'workflow_management' => '-',
            'user_management_logs' => '-',
            'file_deletion_controls' => '-',
            'api_whitelabeling' => '-',
            'support' => 'Email Support',
            'training' => 'Video On Demand',
            'file_storage' => '10 GB / 30 days',
            'cold_storage' => '$0.25/GB/mo',
            'cold_storage_retrieval' => '$25 per retrieval',
            'compliance_security' => 'Standard',
            'deployment' => 'Cloud',
            'term_agreements' => 'Monthly / Standard Terms'
        ),
        'professional' => array(
            'speaker_diarization' => 'check',
            'post_production' => 'check',
            'realtime_reporting' => 'check',
            'teleconferencing_scheduling' => 'check',
            'winner_lite_cat' => 'check',
            'deposnap_recorder' => 'check',
            'transcript_templates' => 'Standard template + 2 custom',
            'summaries' => 'check',
            'custom_queries' => 'check',
            'spanish_transcription' => '-',
            'workflow_management' => 'check',
            'user_management_logs' => 'check',
            'file_deletion_controls' => 'Manual',
            'api_whitelabeling' => 'Available (costs apply)',
            'support' => 'Priority Email',
            'training' => 'Live Training, On Demand',
            'file_storage' => '50 GB / 30 days',
            'cold_storage' => '$0.20/GB/mo',
            'cold_storage_retrieval' => '$15 per retrieval',
            'compliance_security' => 'Standard',
            'deployment' => 'Cloud',
            'term_agreements' => '12‑Month / Standard Terms'
        ),
        'enterprise' => array(
            'speaker_diarization' => 'check',
            'post_production' => 'check',
            'realtime_reporting' => 'check',
            'teleconferencing_scheduling' => 'check',
            'winner_lite_cat' => 'check',
            'deposnap_recorder' => 'check',
            'transcript_templates' => 'Standard template + 10 custom',
            'summaries' => 'check',
            'custom_queries' => 'check',
            'spanish_transcription' => 'check',
            'workflow_management' => 'check',
            'user_management_logs' => 'check',
            'file_deletion_controls' => 'Programmable',
            'api_whitelabeling' => 'Available (costs apply)',
            'support' => 'Account Manager (per SLA)',
            'training' => 'Custom Live Training, On Demand',
            'file_storage' => '500 GB / 30 days',
            'cold_storage' => '$0.15/GB/mo',
            'cold_storage_retrieval' => '$10 per retrieval',
            'compliance_security' => 'Advanced',
            'deployment' => 'Private Cloud',
            'term_agreements' => '12‑Month / Custom'
        )
    );
}

/**
 * Hardcoded card features for each plan
 */
function getHardcodedCardFeatures() {
    return array(
        'classic' => array(
            'Speaker diarization',
            'Post production',
            'Realtime reporting',
            'Teleconferencing & scheduling',
            'Winner Lite CAT Software',
            'DepoSNAP Screen Recorder',
            'Transcript templates: Standard'
        ),
        'professional' => array(
            'Everything in Classic',
            'Transcript templates: +2 custom',
            'Summaries',
            'Custom queries',
            'User management & file logs',
            'File deletion controls: Manual',
            'Priority email support',
            'Live training, on demand'
        ),
        'enterprise' => array(
            '+10 custom transcript templates',
            'Summaries, queries, workflow mgmt',
            'User management & file logs',
            'Programmable deletion controls',
            'Account Manager (per SLA)',
            'Custom live training'
        )
    );
}

/**
 * Build the complete plans config array from database + hardcoded data
 */
function buildPlansConfig() {
    // Fetch data from database
    $dbPlans = getSubscriptionPlansFromDB();
    $annualPaymentOptions = getAnnualPaymentOptionsFromDB();
    
    // Get hardcoded data
    $hardcodedFeatures = getHardcodedFeatures();
    $hardcodedCardFeatures = getHardcodedCardFeatures();
    
    // Build plans array
    $plans = array();
    foreach ($dbPlans as $dbPlan) {
        $planId = $dbPlan['plan_id'];
        // Fetch pricing tiers for this plan
        $pricingTiers = getPricingTiersFromDB($planId);
        
        // Build plan array matching original structure
        $plan = array(
            'id' => $dbPlan['id'],
            'plan_id' => $dbPlan['plan_id'],
            'name' => $dbPlan['name'],
            'ideal_for' => $dbPlan['ideal_for'],
            'users' => $dbPlan['users'],
            'minimum_usage' => $dbPlan['minimum_usage'],
            'pricing' => array(
                'monthly' => $dbPlan['price_monthly'],
                'annual' => $dbPlan['price_annual'],
                'savings_percent' => (float)$dbPlan['savings_percent'],
                'savings_badge' => $dbPlan['savings_badge'],
                'tiers' => $pricingTiers,
                'minimum_hours' => $dbPlan['minimum_hours'] ? (int)$dbPlan['minimum_hours'] : null
            ),
            'has_price' => (bool)$dbPlan['has_price'],
            'price_id' => $dbPlan['price_id'],
            'savings_badge_id' => $dbPlan['savings_badge_id'],
            'card_price_id' => $dbPlan['card_price_id'],
            'card_savings_badge_id' => $dbPlan['card_savings_badge_id'],
            'button_class' => $dbPlan['button_class'],
            'button_text' => $dbPlan['button_text'],
            'button_type' => $dbPlan['button_type'],
            'button_href' => $dbPlan['button_href'],
            'features' => isset($hardcodedFeatures[$planId]) ? $hardcodedFeatures[$planId] : array(),
            'card_features' => isset($hardcodedCardFeatures[$planId]) ? $hardcodedCardFeatures[$planId] : array(),
            'is_accent' => (bool)$dbPlan['is_accent']
        );
        
        $plans[$planId] = $plan;
    }
    
    // Build feature rows (hardcoded)
    $featureRows = array(
        array('key' => 'ideal_for', 'label' => 'Ideal for:', 'type' => 'text'),
        array('key' => 'users', 'label' => 'Users', 'type' => 'text'),
        array('key' => 'minimum_usage', 'label' => 'Minimum Usage', 'type' => 'text'),
        array('key' => 'price', 'label' => 'Price/ Hour (per isolated channel)*', 'type' => 'price'),
        array('key' => 'speaker_diarization', 'label' => 'Speaker Diarization', 'type' => 'check'),
        array('key' => 'post_production', 'label' => 'Post Production', 'type' => 'check'),
        array('key' => 'realtime_reporting', 'label' => 'Realtime Reporting', 'type' => 'check'),
        array('key' => 'teleconferencing_scheduling', 'label' => 'Teleconferencing and Scheduling', 'type' => 'check'),
        array('key' => 'winner_lite_cat', 'label' => 'Winner Lite CAT Software', 'type' => 'check'),
        array('key' => 'deposnap_recorder', 'label' => 'DepoSNAP Screen Recorder', 'type' => 'check'),
        array('key' => 'transcript_templates', 'label' => 'Transcript Templates', 'type' => 'text'),
        array('key' => 'summaries', 'label' => 'Summaries', 'type' => 'check'),
        array('key' => 'custom_queries', 'label' => 'Custom Queries', 'type' => 'check'),
        array('key' => 'spanish_transcription', 'label' => 'Spanish Language Transcription', 'type' => 'check'),
        array('key' => 'workflow_management', 'label' => 'Workflow Management', 'type' => 'check'),
        array('key' => 'user_management_logs', 'label' => 'User Management and File Logs', 'type' => 'check'),
        array('key' => 'file_deletion_controls', 'label' => 'File Deletion Controls', 'type' => 'text'),
        array('key' => 'api_whitelabeling', 'label' => 'API / Whitelabeling', 'type' => 'text'),
        array('key' => 'support', 'label' => 'Support', 'type' => 'text'),
        array('key' => 'training', 'label' => 'Training', 'type' => 'text'),
        array('key' => 'file_storage', 'label' => 'File Storage (Active)*', 'type' => 'text'),
        array('key' => 'cold_storage', 'label' => 'Cold Storage (Add‑On)', 'type' => 'text'),
        array('key' => 'cold_storage_retrieval', 'label' => 'Cold Storage Retrieval', 'type' => 'text'),
        array('key' => 'compliance_security', 'label' => 'Compliance and Security', 'type' => 'text'),
        array('key' => 'deployment', 'label' => 'Deployment', 'type' => 'text'),
        array('key' => 'term_agreements', 'label' => 'Term / Agreements', 'type' => 'text')
    );
    
    return array(
        'annual_payment_options' => $annualPaymentOptions,
        'plans' => $plans,
        'feature_rows' => $featureRows
    );
}

// Build and export the config
$plans_config = buildPlansConfig();
?>
