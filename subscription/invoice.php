<?php
// Include required files first
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/template/Master.php');
require_once (DOCUMENT_ROOT.'/subscription/invoices_data.php');

// Get filter values from request (default to 'today' if not set)
$filterDateRange = isset($_GET['date_range']) ? $_GET['date_range'] : 'today';
$filterFromDate = isset($_GET['from_date']) ? $_GET['from_date'] : '';
$filterToDate = isset($_GET['to_date']) ? $_GET['to_date'] : '';
$filterText = isset($_GET['text_filter']) ? $_GET['text_filter'] : '';

// Build filters array
$filters = array(
    'date_range' => $filterDateRange,
    'from_date' => $filterFromDate,
    'to_date' => $filterToDate,
    'text_filter' => $filterText
);

// Set page title
$set_title = "Myprocat.com: Invoices";

// Create the main content body
$set_body = new content_block(NULL, 'div', array('style' => 'width: 100%;height: 100%;'));

// Banner section
$banner = new content_block(NULL, 'div', array('class' => 'banner'));
$banner->push(new content_block('Invoices', 'h1', array('style' => 'text-align: center; margin:5px 0px;')));
$set_body->push($banner);

// Section heading
$sectionHeading = new content_block('Billing Activity Report', 'h2', array('class' => 'section-heading', 'style' => 'text-align:center; margin-top:20px;'));
$set_body->push($sectionHeading);

// Form box container
$formBox = new content_block(NULL, 'div', array('class' => 'form-box', 'style' => 'max-width: 100dvw; overflow-x: auto;'));
$formBoxInner = new content_block(NULL, 'div', array('style' => 'padding: 12px 0;'));

// Filter Form
$filterForm = new content_block(NULL, 'form', array('method' => 'GET', 'action' => '', 'id' => 'invoiceFilterForm'));

// Filters Row
$filtersRow = new content_block(NULL, 'div', array('style' => 'display:flex; flex-wrap:wrap; align-items:flex-end; gap:16px; margin-bottom:16px; max-width: 100%;'));

// Date range dropdown filter
$dateRangeDiv = new content_block(NULL, 'div', array('style' => 'min-width:180px; flex:1;'));
$dateRangeDiv->push(new content_block('Date Range', 'label', array('style' => 'display:block; font-size:12px; color:#666; margin-bottom:6px;')));
$dateRangeWrapper = new content_block(NULL, 'div', array('class' => 'hours-input-wrapper', 'style' => 'max-width:100%; margin:0; justify-content:flex-start;'));
$dateRangeSelect = new content_block(NULL, 'select', array('class' => 'hours-input', 'id' => 'dateRangeSelect', 'name' => 'date_range', 'onchange' => 'toggleCustomDateRange()', 'style' => 'cursor:pointer;'));

// Date range options with selected state
$dateRangeOptions = array(
    'today' => 'Today',
    'last_24_hours' => 'Last 24 Hours',
    'last_week' => 'Last Week',
    'last_month' => 'Last Month',
    'custom' => 'Custom'
);
foreach ($dateRangeOptions as $value => $label) {
    $optionAttrs = array('value' => $value);
    if ($filterDateRange === $value) {
        $optionAttrs['selected'] = 'selected';
    }
    $dateRangeSelect->push(new content_block($label, 'option', $optionAttrs));
}

$dateRangeWrapper->push($dateRangeSelect);
$dateRangeDiv->push($dateRangeWrapper);
$filtersRow->push($dateRangeDiv);

// Custom date range container (hidden by default, shown if custom selected)
$customDateStyle = ($filterDateRange === 'custom') ? 'display:flex; min-width:320px; flex:2;' : 'display:none; min-width:320px; flex:2;';
$customDateDiv = new content_block(NULL, 'div', array('id' => 'customDateRange', 'style' => $customDateStyle));
$customDateInner = new content_block(NULL, 'div', array('style' => 'display:flex; gap:12px; align-items:flex-end;'));

// From date filter
$fromDateDiv = new content_block(NULL, 'div', array('style' => 'flex:1;'));
$fromDateDiv->push(new content_block('From', 'label', array('style' => 'display:block; font-size:12px; color:#666; margin-bottom:6px;')));
$fromDateWrapper = new content_block(NULL, 'div', array('class' => 'hours-input-wrapper', 'style' => 'max-width:100%; margin:0; justify-content:flex-start;'));
$fromDateWrapper->push(new content_block(NULL, 'input', array('class' => 'hours-input', 'type' => 'date', 'id' => 'fromDate', 'name' => 'from_date', 'value' => $filterFromDate)));
$fromDateDiv->push($fromDateWrapper);
$customDateInner->push($fromDateDiv);

// To date filter
$toDateDiv = new content_block(NULL, 'div', array('style' => 'flex:1;'));
$toDateDiv->push(new content_block('To', 'label', array('style' => 'display:block; font-size:12px; color:#666; margin-bottom:6px;')));
$toDateWrapper = new content_block(NULL, 'div', array('class' => 'hours-input-wrapper', 'style' => 'max-width:100%; margin:0; justify-content:flex-start;'));
$toDateWrapper->push(new content_block(NULL, 'input', array('class' => 'hours-input', 'type' => 'date', 'id' => 'toDate', 'name' => 'to_date', 'value' => $filterToDate)));
$toDateDiv->push($toDateWrapper);
$customDateInner->push($toDateDiv);

$customDateDiv->push($customDateInner);
$filtersRow->push($customDateDiv);

// Filter text input
$filterDiv = new content_block(NULL, 'div', array('style' => 'min-width:280px; flex:2;'));
$filterDiv->push(new content_block('Filter', 'label', array('style' => 'display:block; font-size:12px; color:#666; margin-bottom:6px;')));
$filterWrapper = new content_block(NULL, 'div', array('class' => 'hours-input-wrapper', 'style' => 'max-width:100%; margin:0; justify-content:flex-start;'));
$filterWrapper->push(new content_block(NULL, 'input', array('class' => 'hours-input', 'type' => 'text', 'name' => 'text_filter', 'placeholder' => 'Invoice #, Name, Class of service', 'value' => htmlspecialchars($filterText))));
$filterDiv->push($filterWrapper);
$filtersRow->push($filterDiv);

// Buttons
$buttonsDiv = new content_block(NULL, 'div', array('style' => 'display:flex; gap:12px; margin-left:auto;'));
$buttonsDiv->push(new content_block('Search', 'button', array('style' => 'margin-bottom: 0;', 'class' => 'primary_button text-white', 'type' => 'submit')));
$buttonsDiv->push(new content_block('Clear', 'button', array('style' => 'margin-bottom: 0;', 'class' => 'secondary_button', 'type' => 'button', 'onclick' => 'clearFilters()')));
$filtersRow->push($buttonsDiv);

$filterForm->push($filtersRow);
$formBoxInner->push($filterForm);

// Get the invoices with filters
$invoicesObj = new invoices($UserAccount, $DB, $filters);
$invoices = $invoicesObj->invoices;
// echo '<pre>';
// print_r($invoices);die;
// foreach($invoices as $invoice) {
//     $invoice_number = $invoice['invoice_number'];
//     $company_name = $invoice['company_name'];
//     $invoice_date = $invoice['invoice_date'];
//     $class_of_service = $invoice['class_of_service'];
// }

// Results Table Container
$tableContainer = new content_block(NULL, 'div', array('style' => 'max-width: 100%; overflow-x:auto;border: 1px solid rgb(234, 234, 234);border-radius: 12px;'));

// Add custom CSS for alternating row colors
$tableStyle = new content_block('.invoice-table tbody tr:nth-child(even){ background-color:#f7f7f7; }', 'style');
$tableContainer->push($tableStyle);

// Create table
$table = new content_block(NULL, 'table', array('class' => 'pricing-table invoice-table', 'style' => 'border-collapse:collapse; min-width: 960px;width: 100%;'));

// Table header
$thead = new content_block(NULL, 'thead');
$headerRow = new content_block(NULL, 'tr');
$headerRow->push(new content_block('Invoice #', 'th', array('style' => 'border-color: rgb(234, 234, 234);')));
$headerRow->push(new content_block('Company/Name', 'th', array('style' => 'border-color: rgb(234, 234, 234);')));
$headerRow->push(new content_block('Invoice date', 'th', array('style' => 'border-color: rgb(234, 234, 234);')));
$headerRow->push(new content_block('Class of Service', 'th', array('style' => 'border-color: rgb(234, 234, 234);')));
$headerRow->push(new content_block('Rate', 'th', array('style' => 'border-color: rgb(234, 234, 234);')));
$headerRow->push(new content_block('Hours of Service', 'th', array('style' => 'border-color: rgb(234, 234, 234);')));
$headerRow->push(new content_block('Storage', 'th', array('style' => 'border-color: rgb(234, 234, 234);')));
$headerRow->push(new content_block('Discount', 'th', array('style' => 'border-color: rgb(234, 234, 234);')));
$headerRow->push(new content_block('Total Order', 'th', array('style' => 'border-color: rgb(234, 234, 234);')));
$headerRow->push(new content_block('Status', 'th', array('style' => 'border-color: rgb(234, 234, 234);')));
$headerRow->push(new content_block('Payment Method', 'th', array('style' => 'border-color: rgb(234, 234, 234);')));
$headerRow->push(new content_block('', 'th', array('style' => 'border-color: rgb(234, 234, 234);')));
$thead->push($headerRow);
$table->push($thead);



// Table body
$tbody = new content_block(NULL, 'tbody');

foreach($invoices as $invoice) {
// Row 2
    $row2 = new content_block(NULL, 'tr');
    $row2->push(new content_block(' # '.$invoice['invoice_number'], 'td'));
    $row2->push(new content_block($invoice['customer_name'], 'td'));
    $row2->push(new content_block($invoice['invoice_date'], 'td'));
    $row2->push(new content_block(is_null($invoice['plan_name']) ? 'N/A' : strtoupper($invoice['plan_name']), 'td'));
    $row2->push(new content_block($invoice['rate'], 'td'));
    $row2->push(new content_block(is_null($invoice['minutes']) ? 'N/A' : $invoice['minutes']/60, 'td'));
    $row2->push(new content_block(is_null($invoice['storage']) ? 'N/A' : $invoice['storage'], 'td'));
    $row2->push(new content_block($invoice['discount'], 'td'));
    $row2->push(new content_block('$'.$invoice['total_amount'], 'td'));
    $row2->push(new content_block($invoice['status'] == 1 ? '<span style="color:green;">Paid</span>' : '<span style="color:red;">Unpaid</span>', 'td'));
    $row2->push(new content_block($invoice['payment_method'], 'td'));
    if($invoice['status'] == 0){
        $row2->push(new content_block(
            '<a href="/subscription/pay_invoice.php?invoice_number='.$invoice['invoice_id'].'"
                class="primary_button text-white"
                style="margin-bottom:0; display:inline-block; font-size:14px; padding:8px 14px;"
                data-invoice-number="'.$invoice['invoice_id'].'"
                data-card-last4="'.htmlspecialchars(isset($invoice['subscription_last_four_digits']) ? (string)$invoice['subscription_last_four_digits'] : '').'"
                data-card-exp="'.htmlspecialchars(isset($invoice['subscription_card_expiry_date']) ? (string)$invoice['subscription_card_expiry_date'] : '').'"
                onclick="return openPayInvoiceModal(this);"
            >Pay</a>',
            'td'
        ));
    }else{
        $row2->push(new content_block('', 'td'));
    }
    $tbody->push($row2);
}

// Totals row
$totalsRow = new content_block(NULL, 'tr');
$totalsRow->push(new content_block('<strong>Totals</strong>', 'td', array('style' => 'border:0px;')));
$totalsRow->push(new content_block('<strong>Total Invoices: '.count($invoices).'</strong>', 'td', array('style' => 'border:0px;')));
$totalsRow->push(new content_block('', 'td', array('style' => 'border:0px;')));
$totalsRow->push(new content_block('', 'td', array('style' => 'border:0px;')));
$totalsRow->push(new content_block('', 'td', array('style' => 'border:0px;')));
$totalsRow->push(new content_block('<strong>'.number_format(array_sum(array_column($invoices, 'minutes'))/60, 2).'</strong>', 'td', array('style' => 'border:0px;')));
$totalsRow->push(new content_block('<strong>'.number_format(array_sum(array_column($invoices, 'storage')), 2).'</strong>', 'td', array('style' => 'border:0px;')));
$totalsRow->push(new content_block('<strong>$'.number_format(array_sum(array_column($invoices, 'discount')), 2).'</strong>', 'td', array('style' => 'border:0px;')));
$totalsRow->push(new content_block('<strong>$'.number_format(array_sum(array_column($invoices, 'total_amount')), 2).'</strong>', 'td', array('style' => 'border:0px;')));
$totalsRow->push(new content_block('', 'td', array('style' => 'border:0px;')));
$totalsRow->push(new content_block('', 'td', array('style' => 'border:0px;')));
$tbody->push($totalsRow);

$table->push($tbody);
$tableContainer->push($table);
$formBoxInner->push($tableContainer);

$formBox->push($formBoxInner);
$set_body->push($formBox);

// Add JavaScript for date range toggle and clear filters
$dateRangeScript = new content_block('
function toggleCustomDateRange() {
    var select = document.getElementById("dateRangeSelect");
    var customRange = document.getElementById("customDateRange");
    
    if (select.value === "custom") {
        customRange.style.display = "flex";
    } else {
        customRange.style.display = "none";
        // Clear custom date values when switching away from custom
        document.getElementById("fromDate").value = "";
        document.getElementById("toDate").value = "";
    }
}

function clearFilters() {
    // Reset all form fields (today is default)
    document.getElementById("dateRangeSelect").value = "today";
    document.getElementById("fromDate").value = "";
    document.getElementById("toDate").value = "";
    document.querySelector("input[name=\'text_filter\']").value = "";
    
    // Hide custom date range
    document.getElementById("customDateRange").style.display = "none";
    
    // Submit the form to reload with default filters
    document.getElementById("invoiceFilterForm").submit();
}

// Initialize on page load
document.addEventListener("DOMContentLoaded", function() {
    toggleCustomDateRange();
});
', 'script', array('type' => 'text/javascript'));
$set_body->push($dateRangeScript);

// Pay invoice modal (existing vs new card)
$payModalStyles = new content_block('
#payInvoiceModalOverlay{
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.45);
    display: none;
    align-items: center;
    justify-content: center;
    padding: 16px;
    z-index: 2000;
}
#payInvoiceModal{
    width: 100%;
    max-width: 560px;
    margin: 0;
    text-align: center;
    padding: 34px 28px;
    position: relative;
}
#payInvoiceModalClose{
    position: absolute;
    top: 12px;
    right: 12px;
    width: 36px;
    height: 36px;
    border-radius: 999px;
    border: 1px solid #e9ecef;
    background: #fff;
    cursor: pointer;
    font-size: 18px;
    line-height: 34px;
    color: #333;
}
#payInvoiceModalActions{
    display: flex;
    gap: 12px;
    justify-content: center;
    flex-wrap: wrap;
    margin-top: 18px;
}
#payInvoiceModalActions a{
    margin-bottom: 0;
    font-size: 14px;
    padding: 10px 16px;
    display: inline-block;
}
', 'style');
$set_body->push($payModalStyles);

$payModalHtml = new content_block('
<div id="payInvoiceModalOverlay" onclick="payInvoiceModalOverlayClick(event)">
  <div id="payInvoiceModal" class="form-box" role="dialog" aria-modal="true" aria-labelledby="payInvoiceModalTitle">
    <button id="payInvoiceModalClose" type="button" onclick="closePayInvoiceModal()">×</button>
    <h2 id="payInvoiceModalTitle" style="color:#27475f; font-size:22px; margin-bottom:10px;">Pay Invoice <span style="color:#666; font-size:14px;">#<span id="payInvoiceModalInvoiceNumber"></span></span></h2>
    <p id="payInvoiceModalCardLine" style="color:#666; font-size:14px; margin-bottom: 10px;">Do you want to use your existing saved card, or use a new card?</p>

    <div id="payInvoiceModalActions">
      <a id="payInvoiceExistingBtn" class="primary_button text-white" href="#">Use existing card</a>
      <a id="payInvoiceNewBtn" class="secondary_button" href="#">Use new card</a>
      <a class="secondary_button" href="#" onclick="closePayInvoiceModal(); return false;">Cancel</a>
    </div>
  </div>
</div>
', 'div');
$set_body->push($payModalHtml);

$payModalScript = new content_block('
function openPayInvoiceModal(el){
    try{
        var invoiceNumber = el.getAttribute("data-invoice-number") || "";
        var last4 = (el.getAttribute("data-card-last4") || "").trim();
        var exp = (el.getAttribute("data-card-exp") || "").trim();
        var overlay = document.getElementById("payInvoiceModalOverlay");
        var invEl = document.getElementById("payInvoiceModalInvoiceNumber");
        var cardLine = document.getElementById("payInvoiceModalCardLine");
        var existingBtn = document.getElementById("payInvoiceExistingBtn");
        var newBtn = document.getElementById("payInvoiceNewBtn");

        if(invEl){ invEl.textContent = invoiceNumber; }

        if(cardLine){
            if(last4){
                var expText = "";
                // exp is stored as YYYY-MM-DD. Show as MM/YY if possible.
                if(exp && exp.length >= 10){
                    var mm = exp.substring(5,7);
                    var yy = exp.substring(2,4);
                    expText = " (exp " + mm + "/" + yy + ")";
                }
                cardLine.textContent = "Saved card ending in " + last4 + expText + ". Or use a new card.";
            }else{
                cardLine.textContent = "No saved card found for this subscription. Please use a new card.";
            }
        }

        // Existing card: charge using vault
        if(existingBtn){
            existingBtn.href = "/subscription/pay_invoice.php?invoice_number=" + encodeURIComponent(invoiceNumber) + "&method=existing";
            if(!last4){
                existingBtn.style.opacity = "0.5";
                existingBtn.style.pointerEvents = "none";
            }else{
                existingBtn.style.opacity = "1";
                existingBtn.style.pointerEvents = "auto";
            }
        }

        // New card: start a Sage UI payment transaction (new card)
        if(newBtn){
            newBtn.href = "/subscription/pay_invoice.php?invoice_number=" + encodeURIComponent(invoiceNumber) + "&method=new";
        }

        if(overlay){
            overlay.style.display = "flex";
        }

        // ESC to close
        document.addEventListener("keydown", payInvoiceModalEscClose);
    }catch(e){}
    return false;
}

function closePayInvoiceModal(){
    var overlay = document.getElementById("payInvoiceModalOverlay");
    if(overlay){ overlay.style.display = "none"; }
    document.removeEventListener("keydown", payInvoiceModalEscClose);
}

function payInvoiceModalEscClose(e){
    if(e && (e.key === "Escape" || e.keyCode === 27)){
        closePayInvoiceModal();
    }
}

function payInvoiceModalOverlayClick(e){
    var overlay = document.getElementById("payInvoiceModalOverlay");
    if(e && overlay && e.target === overlay){
        closePayInvoiceModal();
    }
}
', 'script', array('type' => 'text/javascript'));
$set_body->push($payModalScript);

// Include mainframe to render the page
require_once('mainframe.php');
?>

