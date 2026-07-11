<?php
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/setup/start.php');
require_once (DOCUMENT_ROOT.'/template/Master.php');
require_once (DOCUMENT_ROOT.'/template/room.php');
require_once (DOCUMENT_ROOT.'/template/roomicon.php');
class invoices extends content_block
{
	protected $DB = NULL;
	protected $userOb = NULL;
	public $invoices = array();
	protected $filters = array();

	protected function getDateRangeCondition($dateRange, $fromDate, $toDate)
	{
		$today = date('Y-m-d');
		
		switch($dateRange) {
			case 'today':
				return array(
					'condition' => ' AND DATE(casepad_payment_invoices.invoice_date) = ?',
					'params' => array($today)
				);
			case 'last_24_hours':
				$yesterday = date('Y-m-d', strtotime('-1 day'));
				return array(
					'condition' => ' AND casepad_payment_invoices.invoice_date >= ?',
					'params' => array($yesterday . ' 00:00:00')
				);
			case 'last_week':
				$lastWeek = date('Y-m-d', strtotime('-7 days'));
				return array(
					'condition' => ' AND DATE(casepad_payment_invoices.invoice_date) >= ?',
					'params' => array($lastWeek)
				);
			case 'last_month':
				$lastMonth = date('Y-m-d', strtotime('-1 month'));
				return array(
					'condition' => ' AND DATE(casepad_payment_invoices.invoice_date) >= ?',
					'params' => array($lastMonth)
				);
			case 'custom':
				if (!empty($fromDate) && !empty($toDate)) {
					return array(
						'condition' => ' AND DATE(casepad_payment_invoices.invoice_date) BETWEEN ? AND ?',
						'params' => array($fromDate, $toDate)
					);
				} elseif (!empty($fromDate)) {
					return array(
						'condition' => ' AND DATE(casepad_payment_invoices.invoice_date) >= ?',
						'params' => array($fromDate)
					);
				} elseif (!empty($toDate)) {
					return array(
						'condition' => ' AND DATE(casepad_payment_invoices.invoice_date) <= ?',
						'params' => array($toDate)
					);
				}
				break;
		}
		
		return array('condition' => '', 'params' => array());
	}

	protected function fetch_list()
	{
		$this->invoices = array (
			'invoice_id',
			'invoice_number',
			'invoice_date',
			'plan_name',
			'rate',
			'minutes',
			'storage',
			'discount',
			'total_amount',
			'payment_method',
			'customer_name',
			'customer_email',
			'status',
			'subscription_last_four_digits',
			'subscription_card_expiry_date'
		);
		
		// Build filter conditions
		$dateRange = isset($this->filters['date_range']) ? $this->filters['date_range'] : '';
		$fromDate = isset($this->filters['from_date']) ? $this->filters['from_date'] : '';
		$toDate = isset($this->filters['to_date']) ? $this->filters['to_date'] : '';
		$textFilter = isset($this->filters['text_filter']) ? trim($this->filters['text_filter']) : '';
		
		$dateCondition = $this->getDateRangeCondition($dateRange, $fromDate, $toDate);
		
		// Text filter condition
		$textCondition = '';
		$textParams = array();
		if (!empty($textFilter)) {
			$textCondition = ' AND (
				casepad_payment_invoices.invoice_number LIKE ? OR
				subscription_plans.name LIKE ? OR
				CONCAT(accounts.first_name, " ", accounts.mid_name, " ", accounts.last_name) LIKE ? OR
				accounts.email LIKE ?
			)';
			$searchTerm = '%' . $textFilter . '%';
			$textParams = array($searchTerm, $searchTerm, $searchTerm, $searchTerm);
		}
		
		$baseQuery = 'SELECT 
				casepad_payment_invoices.id as invoice_id,
				casepad_payment_invoices.invoice_number,
                casepad_payment_invoices.invoice_date,
                casepad_minutes_credits.source as `name`,
                casepad_payment_invoices.rate,
                casepad_minutes_credits.minutes, 
                casepad_storage_credits.storage,
				casepad_payment_invoices.discount,
                casepad_payment_invoices.total_amount,
                casepad_payment_invoices.payment_method,
                CONCAT(accounts.first_name, " ", accounts.mid_name, " ", accounts.last_name) as customer_name,
                accounts.email as customer_email,
				casepad_payment_invoices.status,
				casepad_subscribed_plan.last_four_digits as subscription_last_four_digits,
				casepad_subscribed_plan.card_expiry_date as subscription_card_expiry_date
                FROM casepad_payment_invoices
                LEFT JOIN casepad_subscribed_plan
                ON casepad_subscribed_plan.id = casepad_payment_invoices.subscribed_plan_id
                LEFT JOIN subscription_plans 
                ON casepad_subscribed_plan.plan_id = subscription_plans.id
                LEFT JOIN casepad_minutes_credits
                ON casepad_minutes_credits.invoice_id = casepad_payment_invoices.id
                LEFT JOIN casepad_storage_credits
                ON casepad_storage_credits.invoice_id = casepad_payment_invoices.id
                LEFT JOIN accounts
                ON accounts.id_user = casepad_subscribed_plan.id_owner';
		
		if(is_null($this->userOb) || !$this->userOb->logged_in)
		{
			$whereClause = ' WHERE accounts.id_user = ?' . $dateCondition['condition'] . $textCondition;
			$params = array_merge(array($this->userOb->user_details['id_user']), $dateCondition['params'], $textParams);
			$types = str_repeat('s', count($params));
			
			$this->DB->sql(
				$baseQuery . $whereClause . ' ORDER BY casepad_payment_invoices.invoice_date DESC',
				array_merge(array($types), $params),
				$this->invoices
			);

		} else {
			if($this->userOb->user_details['is_admin'])
			{
				$whereClause = ' WHERE 1=1' . $dateCondition['condition'] . $textCondition;
				$params = array_merge($dateCondition['params'], $textParams);
				
				if (count($params) > 0) {
					$types = str_repeat('s', count($params));
					$this->DB->sql(
						$baseQuery . $whereClause . ' ORDER BY casepad_payment_invoices.invoice_date DESC',
						array_merge(array($types), $params),
						$this->invoices
					);
				} else {
					$this->DB->sql(
						$baseQuery . ' ORDER BY casepad_payment_invoices.invoice_date DESC',
						array(''),
						$this->invoices
					);
				}

			} else {
				$whereClause = ' WHERE accounts.id_user = ?' . $dateCondition['condition'] . $textCondition;
				$params = array_merge(array($this->userOb->user_details['id_user']), $dateCondition['params'], $textParams);
				$types = str_repeat('s', count($params));
				
				$this->DB->sql(
					$baseQuery . $whereClause . ' ORDER BY casepad_payment_invoices.invoice_date DESC',
					array_merge(array($types), $params),
					$this->invoices
				);
			}
		}
	}
	
	public function __construct($userOb = NULL, $DB = NULL, $filters = array())
	{
		if(is_null($DB) || !is_a($DB,'databaseI'))
		{
			exit("Invalid constructor for roomlist, missing database interface");
		}
		$this->userOb = &$userOb;
		$this->DB = &$DB;
		$this->filters = $filters;
		$this->fetch_list();
	}
}
?>