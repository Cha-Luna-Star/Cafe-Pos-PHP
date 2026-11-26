# Cafe POS

## Sales reporting database migration

Run database/sales_reporting.sql against the CafePOS database before running this version of the app. For a group test installation, run it against CafePOS_Test after the test database setup. The script can be run again and changes only the view and procedure definitions.

- dbo.vw_CompletedOrders exposes completed orders for reporting.
- dbo.GetSalesReportTotals takes inclusive start and end timestamps and returns the overall total, daily totals, and totals by payment method.
- dbo.GetBestSellingMenuItems takes the same range and returns the top ten products by quantity sold, with item revenue before order-level discounts. Sales Reports and Dashboard share this procedure.

## Order history database migration

Run database/order_read_procedures.sql against CafePOS (or CafePOS_Test for group testing) before using Order History or opening receipts. It creates GetOrderHistory, GetOrderReceipt, and GetOrderReceiptItems without changing order records.

## New Order menu migration

Run database/pos_read_procedures.sql against CafePOS or CafePOS_Test to install the category, sellable menu, and size modifier procedures.

## Inventory read migration

Run database/inventory_read_procedures.sql against CafePOS and any group test database. Its seven read procedures cover Current Stock, archived ingredients, batches, movements, and reconciliation. GetInventoryBatches reads the shared vw_InventoryBatches view, which combines ingredient and product batches.

## Dashboard read migration

Run database/dashboard_read_procedures.sql after database/inventory_read_procedures.sql and database/sales_reporting.sql. Dashboard procedures return daily totals, chart periods, recent and pending orders, and stock alerts. The expiry alert uses vw_InventoryBatches.

## Audit Log read migration

Run database/audit_read_procedures.sql against CafePOS or CafePOS_Test. GetAuditActions supplies the filter choices. CountAuditEntries counts rows matching the optional filters, and GetAuditEntries returns the requested page in newest-first order.

## Staff Accounts read migration

Run database/staff_read_procedures.sql against CafePOS and CafePOS_Test before deploying the Staff Accounts changes. GetStaffAccounts accepts optional username and role filters. HasStaffAccountStatus checks whether account status and session version columns are installed. Install this script before deploying to keep login and session checks working.

## Login and session read migration

Run database/auth_read_procedures.sql before deploying the login and session changes. GetLoginAccount and GetSessionAccount return the account fields PHP needs to verify an active login or session. GetCurrentAccountPassword supports the existing password rehash race check.

## Menu Management read migration

Run database/menu_read_procedures.sql after database/pos_read_procedures.sql. Menu Management shares GetMenuCategories with New Order. GetManagedMenuItems loads the list and preserves the default stock threshold for older databases. CountMenuCategory validates submitted categories and HasProductLowStockThreshold checks whether the optional column exists.

## Checkout read migration

Run database/checkout_read_procedures.sql against CafePOS or CafePOS_Test. The eight procedures handle checkout lookups, usable stock, and ordered batch reads. PHP still owns the checkout transaction and writes.

## Stock-in read migration

Run database/stock_in_read_procedures.sql against CafePOS or CafePOS_Test. GetStockInIngredient and GetStockInProduct find active items under the original stock-in transaction locks.
