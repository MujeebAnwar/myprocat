-- Align renew_support_orders.transaction_id collation with casepad_payment_invoices.
ALTER TABLE `renew_support_orders`
  MODIFY `transaction_id` VARCHAR(128)
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_general_ci
    DEFAULT NULL;
