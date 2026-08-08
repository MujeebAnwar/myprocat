-- Install Renew / Support schema + seed data.
-- Run against the MyProCAT database (same DB as rooms / room_permissions).
--
-- Order matters (FK dependencies):
--   1. plans
--   2. products
--   3. skus
--   4. sku_rooms
--   5. orders (+ student renewals)
--
-- mysql -u USER -p DATABASE < renew_support/install.sql
--
-- Or run each file individually in the same order.

SOURCE plans_table.sql;
SOURCE products_table.sql;
SOURCE skus_table.sql;
SOURCE sku_rooms_table.sql;
SOURCE orders_table.sql;
SOURCE sku_prices_view.sql;
-- If plans already existed without features_json, also run:
-- SOURCE plans_features_alter.sql;
-- If convenience SKUs still use Plan B only, also run:
-- SOURCE sku_convenience_ab_update.sql;
