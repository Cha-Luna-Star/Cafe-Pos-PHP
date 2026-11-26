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
