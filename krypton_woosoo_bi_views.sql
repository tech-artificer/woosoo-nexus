-- ============================================================================
-- Krypton ↔ Woosoo BI Platform: Read-Only Views & Materialized Result Sets
-- ============================================================================
-- Purpose: Unified reconciliation layer for order drift detection and analysis
-- Dialects: MySQL 8.0+ and PostgreSQL 12+
-- Last Updated: 2025
-- ============================================================================

-- =============================================================================
-- SECTION 1: CORE FUSION VIEW (Real-Time)
-- =============================================================================
-- Consolidated order header view: Krypton POS ↔ Woosoo Nexus via crosswalk
-- Use for: Order-level reconciliation, dashboard KPIs, ad-hoc analysis

-- MySQL Version:
CREATE OR REPLACE VIEW bi_krypton_woosoo_order_fusion AS
SELECT
  k.id AS krypton_order_id,
  k.order_uuid AS krypton_order_uuid,
  k.date_time_opened AS krypton_opened_at,
  k.date_time_closed AS krypton_closed_at,
  k.total_amount AS krypton_total,
  
  wco.woosoo_order_id,
  wco.krypton_order_uuid,
  wco.woosoo_order_uuid,
  wco.created_at AS crosswalk_linked_at,
  
  w.order_uuid AS woosoo_order_uuid,
  w.date_time_opened AS woosoo_opened_at,
  w.date_time_closed AS woosoo_closed_at,
  w.total_amount AS woosoo_total,
  
  -- Reconciliation flags
  CASE
    WHEN wco.woosoo_order_id IS NULL THEN 'UNLINKED'
    WHEN wco.woosoo_order_id IS NOT NULL THEN 'LINKED'
    ELSE 'UNKNOWN'
  END AS crosswalk_status,
  
  CASE
    WHEN ABS(COALESCE(k.total_amount, 0) - COALESCE(w.total_amount, 0)) > 0.01 THEN 'AMOUNT_MISMATCH'
    ELSE 'AMOUNT_OK'
  END AS amount_reconciliation,
  
  TIMESTAMPDIFF(SECOND, k.date_time_opened, w.date_time_opened) AS opened_delta_seconds,
  TIMESTAMPDIFF(SECOND, k.date_time_closed, w.date_time_closed) AS closed_delta_seconds,
  
  -- Metadata
  NOW() AS view_generated_at
FROM krypton_woosoo.orders k
LEFT JOIN woosoo_crosswalk_orders wco ON wco.krypton_order_id = k.id
LEFT JOIN woosoo.orders w ON wco.woosoo_order_id = w.id;

-- PostgreSQL Version:
CREATE OR REPLACE VIEW bi_krypton_woosoo_order_fusion AS
SELECT
  k.id AS krypton_order_id,
  k.order_uuid AS krypton_order_uuid,
  k.date_time_opened AS krypton_opened_at,
  k.date_time_closed AS krypton_closed_at,
  k.total_amount AS krypton_total,
  
  wco.woosoo_order_id,
  wco.krypton_order_uuid,
  wco.woosoo_order_uuid,
  wco.created_at AS crosswalk_linked_at,
  
  w.order_uuid AS woosoo_order_uuid,
  w.date_time_opened AS woosoo_opened_at,
  w.date_time_closed AS woosoo_closed_at,
  w.total_amount AS woosoo_total,
  
  -- Reconciliation flags
  CASE
    WHEN wco.woosoo_order_id IS NULL THEN 'UNLINKED'
    WHEN wco.woosoo_order_id IS NOT NULL THEN 'LINKED'
    ELSE 'UNKNOWN'
  END AS crosswalk_status,
  
  CASE
    WHEN ABS(COALESCE(k.total_amount, 0) - COALESCE(w.total_amount, 0)) > 0.01 THEN 'AMOUNT_MISMATCH'
    ELSE 'AMOUNT_OK'
  END AS amount_reconciliation,
  
  EXTRACT(EPOCH FROM (w.date_time_opened - k.date_time_opened))::INTEGER AS opened_delta_seconds,
  EXTRACT(EPOCH FROM (w.date_time_closed - k.date_time_closed))::INTEGER AS closed_delta_seconds,
  
  -- Metadata
  NOW() AS view_generated_at
FROM krypton_woosoo.orders k
LEFT JOIN woosoo_crosswalk_orders wco ON wco.krypton_order_id = k.id
LEFT JOIN woosoo.orders w ON wco.woosoo_order_id = w.id;

-- =============================================================================
-- SECTION 2: DRIFT DETECTION VIEW (Real-Time)
-- =============================================================================
-- Identifies orders with reconciliation issues: unlinked, mismatched amounts, time drift
-- Use for: Alerting, exception reports, data quality dashboards

-- MySQL Version:
CREATE OR REPLACE VIEW bi_order_drift_detection AS
SELECT
  k.id AS krypton_order_id,
  k.order_uuid AS krypton_order_uuid,
  wco.woosoo_order_id,
  w.order_uuid AS woosoo_order_uuid,
  k.date_time_opened AS krypton_opened_at,
  w.date_time_opened AS woosoo_opened_at,
  k.date_time_closed AS krypton_closed_at,
  w.date_time_closed AS woosoo_closed_at,
  k.total_amount AS krypton_total,
  w.total_amount AS woosoo_total,
  
  -- Issue detection
  CASE
    WHEN wco.woosoo_order_id IS NULL THEN 'UNLINKED_CROSSWALK'
    ELSE NULL
  END AS issue_type_crosswalk,
  
  CASE
    WHEN ABS(COALESCE(k.total_amount, 0) - COALESCE(w.total_amount, 0)) > 0.01 THEN 'AMOUNT_MISMATCH'
    ELSE NULL
  END AS issue_type_amount,
  
  CASE
    WHEN ABS(TIMESTAMPDIFF(SECOND, k.date_time_opened, w.date_time_opened)) > 300 THEN 'OPENED_TIME_DRIFT'
    ELSE NULL
  END AS issue_type_timing,
  
  -- Severity scoring
  CASE
    WHEN wco.woosoo_order_id IS NULL THEN 'HIGH'
    WHEN ABS(COALESCE(k.total_amount, 0) - COALESCE(w.total_amount, 0)) > 0.01 THEN 'HIGH'
    WHEN ABS(TIMESTAMPDIFF(SECOND, k.date_time_opened, w.date_time_opened)) > 300 THEN 'MEDIUM'
    ELSE 'LOW'
  END AS severity,
  
  wco.created_at AS crosswalk_created_at,
  NOW() AS detected_at
FROM krypton_woosoo.orders k
LEFT JOIN woosoo_crosswalk_orders wco ON wco.krypton_order_id = k.id
LEFT JOIN woosoo.orders w ON wco.woosoo_order_id = w.id
WHERE
  wco.woosoo_order_id IS NULL
  OR ABS(COALESCE(k.total_amount, 0) - COALESCE(w.total_amount, 0)) > 0.01
  OR ABS(TIMESTAMPDIFF(SECOND, k.date_time_opened, w.date_time_opened)) > 300;

-- PostgreSQL Version:
CREATE OR REPLACE VIEW bi_order_drift_detection AS
SELECT
  k.id AS krypton_order_id,
  k.order_uuid AS krypton_order_uuid,
  wco.woosoo_order_id,
  w.order_uuid AS woosoo_order_uuid,
  k.date_time_opened AS krypton_opened_at,
  w.date_time_opened AS woosoo_opened_at,
  k.date_time_closed AS krypton_closed_at,
  w.date_time_closed AS woosoo_closed_at,
  k.total_amount AS krypton_total,
  w.total_amount AS woosoo_total,
  
  -- Issue detection
  CASE
    WHEN wco.woosoo_order_id IS NULL THEN 'UNLINKED_CROSSWALK'
    ELSE NULL
  END AS issue_type_crosswalk,
  
  CASE
    WHEN ABS(COALESCE(k.total_amount, 0) - COALESCE(w.total_amount, 0)) > 0.01 THEN 'AMOUNT_MISMATCH'
    ELSE NULL
  END AS issue_type_amount,
  
  CASE
    WHEN ABS(EXTRACT(EPOCH FROM (w.date_time_opened - k.date_time_opened))::INTEGER) > 300 THEN 'OPENED_TIME_DRIFT'
    ELSE NULL
  END AS issue_type_timing,
  
  -- Severity scoring
  CASE
    WHEN wco.woosoo_order_id IS NULL THEN 'HIGH'
    WHEN ABS(COALESCE(k.total_amount, 0) - COALESCE(w.total_amount, 0)) > 0.01 THEN 'HIGH'
    WHEN ABS(EXTRACT(EPOCH FROM (w.date_time_opened - k.date_time_opened))::INTEGER) > 300 THEN 'MEDIUM'
    ELSE 'LOW'
  END AS severity,
  
  wco.created_at AS crosswalk_created_at,
  NOW() AS detected_at
FROM krypton_woosoo.orders k
LEFT JOIN woosoo_crosswalk_orders wco ON wco.krypton_order_id = k.id
LEFT JOIN woosoo.orders w ON wco.woosoo_order_id = w.id
WHERE
  wco.woosoo_order_id IS NULL
  OR ABS(COALESCE(k.total_amount, 0) - COALESCE(w.total_amount, 0)) > 0.01
  OR ABS(EXTRACT(EPOCH FROM (w.date_time_opened - k.date_time_opened))::INTEGER) > 300;

-- =============================================================================
-- SECTION 3: LINE-ITEM RECONCILIATION VIEW (Real-Time)
-- =============================================================================
-- Item-level alignment: Krypton ordered_menus ↔ Woosoo device_order_items
-- Use for: Line-item drift, menu catalog alignment, quantity/price analysis

-- MySQL Version:
CREATE OR REPLACE VIEW bi_line_item_reconciliation AS
SELECT
  fus.krypton_order_id,
  fus.krypton_order_uuid,
  fus.woosoo_order_id,
  fus.woosoo_order_uuid,
  
  km.id AS krypton_item_id,
  km.menu_id AS krypton_menu_id,
  m.name AS krypton_menu_name,
  km.quantity AS krypton_qty,
  km.price AS krypton_price,
  km.subtotal AS krypton_subtotal,
  
  doi.id AS woosoo_item_id,
  doi.menu_id AS woosoo_menu_id,
  doi.quantity AS woosoo_qty,
  doi.price AS woosoo_price,
  doi.subtotal AS woosoo_subtotal,
  doi.tax AS woosoo_tax,
  doi.discount AS woosoo_discount,
  
  -- Item reconciliation
  CASE
    WHEN km.quantity = doi.quantity AND ABS(COALESCE(km.price, 0) - COALESCE(doi.price, 0)) < 0.01 THEN 'MATCHED'
    WHEN km.quantity != doi.quantity THEN 'QTY_MISMATCH'
    WHEN ABS(COALESCE(km.price, 0) - COALESCE(doi.price, 0)) > 0.01 THEN 'PRICE_MISMATCH'
    ELSE 'UNKNOWN'
  END AS item_reconciliation_status,
  
  (km.quantity - COALESCE(doi.quantity, 0)) AS qty_delta,
  (km.subtotal - COALESCE(doi.subtotal, 0)) AS subtotal_delta
  
FROM bi_krypton_woosoo_order_fusion fus
LEFT JOIN krypton_woosoo.ordered_menus km ON km.order_id = fus.krypton_order_id
LEFT JOIN krypton_woosoo.menus m ON m.id = km.menu_id
LEFT JOIN woosoo.device_order_items doi ON doi.order_id = fus.woosoo_order_id
  AND doi.menu_id = km.menu_id;

-- PostgreSQL Version:
CREATE OR REPLACE VIEW bi_line_item_reconciliation AS
SELECT
  fus.krypton_order_id,
  fus.krypton_order_uuid,
  fus.woosoo_order_id,
  fus.woosoo_order_uuid,
  
  km.id AS krypton_item_id,
  km.menu_id AS krypton_menu_id,
  m.name AS krypton_menu_name,
  km.quantity AS krypton_qty,
  km.price AS krypton_price,
  km.subtotal AS krypton_subtotal,
  
  doi.id AS woosoo_item_id,
  doi.menu_id AS woosoo_menu_id,
  doi.quantity AS woosoo_qty,
  doi.price AS woosoo_price,
  doi.subtotal AS woosoo_subtotal,
  doi.tax AS woosoo_tax,
  doi.discount AS woosoo_discount,
  
  -- Item reconciliation
  CASE
    WHEN km.quantity = doi.quantity AND ABS(COALESCE(km.price, 0) - COALESCE(doi.price, 0)) < 0.01 THEN 'MATCHED'
    WHEN km.quantity != doi.quantity THEN 'QTY_MISMATCH'
    WHEN ABS(COALESCE(km.price, 0) - COALESCE(doi.price, 0)) > 0.01 THEN 'PRICE_MISMATCH'
    ELSE 'UNKNOWN'
  END AS item_reconciliation_status,
  
  (km.quantity - COALESCE(doi.quantity, 0)) AS qty_delta,
  (km.subtotal - COALESCE(doi.subtotal, 0)) AS subtotal_delta
  
FROM bi_krypton_woosoo_order_fusion fus
LEFT JOIN krypton_woosoo.ordered_menus km ON km.order_id = fus.krypton_order_id
LEFT JOIN krypton_woosoo.menus m ON m.id = km.menu_id
LEFT JOIN woosoo.device_order_items doi ON doi.order_id = fus.woosoo_order_id
  AND doi.menu_id = km.menu_id;

-- =============================================================================
-- SECTION 4: MATERIALIZED RESULT SETS (Snapshots for BI / Historical Analysis)
-- =============================================================================
-- These tables store point-in-time snapshots for trend analysis, auditing, and BI consumption

-- 4.1 Daily Order Reconciliation Snapshot
CREATE TABLE bi_order_reconciliation_snapshot (
  snapshot_id BIGINT AUTO_INCREMENT PRIMARY KEY,
  snapshot_date DATE NOT NULL,
  krypton_order_id BIGINT NOT NULL,
  krypton_order_uuid VARCHAR(255),
  woosoo_order_id BIGINT,
  woosoo_order_uuid VARCHAR(255),
  crosswalk_status VARCHAR(50),
  amount_reconciliation VARCHAR(50),
  krypton_total DECIMAL(12, 2),
  woosoo_total DECIMAL(12, 2),
  amount_delta DECIMAL(12, 2),
  opened_delta_seconds INT,
  closed_delta_seconds INT,
  krypton_opened_at DATETIME,
  woosoo_opened_at DATETIME,
  severity VARCHAR(50),
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_daily_order (snapshot_date, krypton_order_id),
  KEY idx_snapshot_date (snapshot_date),
  KEY idx_crosswalk_status (crosswalk_status),
  KEY idx_severity (severity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PostgreSQL Version:
CREATE TABLE bi_order_reconciliation_snapshot (
  snapshot_id BIGSERIAL PRIMARY KEY,
  snapshot_date DATE NOT NULL,
  krypton_order_id BIGINT NOT NULL,
  krypton_order_uuid VARCHAR(255),
  woosoo_order_id BIGINT,
  woosoo_order_uuid VARCHAR(255),
  crosswalk_status VARCHAR(50),
  amount_reconciliation VARCHAR(50),
  krypton_total NUMERIC(12, 2),
  woosoo_total NUMERIC(12, 2),
  amount_delta NUMERIC(12, 2),
  opened_delta_seconds INT,
  closed_delta_seconds INT,
  krypton_opened_at TIMESTAMP,
  woosoo_opened_at TIMESTAMP,
  severity VARCHAR(50),
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (snapshot_date, krypton_order_id),
  CONSTRAINT idx_snapshot_date_fk UNIQUE (snapshot_date, krypton_order_id)
);
CREATE INDEX idx_bi_snapshot_date ON bi_order_reconciliation_snapshot (snapshot_date);
CREATE INDEX idx_bi_crosswalk_status ON bi_order_reconciliation_snapshot (crosswalk_status);
CREATE INDEX idx_bi_severity ON bi_order_reconciliation_snapshot (severity);

-- 4.2 Materialized Refresh Procedure (MySQL)
DELIMITER $$
CREATE PROCEDURE refresh_bi_order_reconciliation_snapshot()
BEGIN
  INSERT INTO bi_order_reconciliation_snapshot
  (snapshot_date, krypton_order_id, krypton_order_uuid, woosoo_order_id, woosoo_order_uuid, 
   crosswalk_status, amount_reconciliation, krypton_total, woosoo_total, amount_delta, 
   opened_delta_seconds, closed_delta_seconds, krypton_opened_at, woosoo_opened_at, 
   severity, notes)
  SELECT
    CURDATE(),
    fus.krypton_order_id,
    fus.krypton_order_uuid,
    fus.woosoo_order_id,
    fus.woosoo_order_uuid,
    fus.crosswalk_status,
    fus.amount_reconciliation,
    fus.krypton_total,
    fus.woosoo_total,
    COALESCE(fus.krypton_total, 0) - COALESCE(fus.woosoo_total, 0),
    fus.opened_delta_seconds,
    fus.closed_delta_seconds,
    fus.krypton_opened_at,
    fus.woosoo_opened_at,
    CASE
      WHEN fus.crosswalk_status = 'UNLINKED' THEN 'HIGH'
      WHEN fus.amount_reconciliation = 'AMOUNT_MISMATCH' THEN 'HIGH'
      WHEN ABS(COALESCE(fus.opened_delta_seconds, 0)) > 300 THEN 'MEDIUM'
      ELSE 'LOW'
    END,
    CONCAT_WS(' | ', 
      IF(fus.crosswalk_status = 'UNLINKED', 'No crosswalk link', NULL),
      IF(fus.amount_reconciliation = 'AMOUNT_MISMATCH', 
        CONCAT('Amount delta: ', COALESCE(fus.krypton_total, 0) - COALESCE(fus.woosoo_total, 0)), NULL)
    )
  FROM bi_krypton_woosoo_order_fusion fus
  ON DUPLICATE KEY UPDATE
    notes = VALUES(notes),
    created_at = NOW();
END$$
DELIMITER ;

-- 4.3 Materialized Refresh Procedure (PostgreSQL)
CREATE OR REPLACE FUNCTION refresh_bi_order_reconciliation_snapshot()
RETURNS void AS $$
BEGIN
  INSERT INTO bi_order_reconciliation_snapshot
  (snapshot_date, krypton_order_id, krypton_order_uuid, woosoo_order_id, woosoo_order_uuid, 
   crosswalk_status, amount_reconciliation, krypton_total, woosoo_total, amount_delta, 
   opened_delta_seconds, closed_delta_seconds, krypton_opened_at, woosoo_opened_at, 
   severity, notes)
  SELECT
    CURRENT_DATE,
    fus.krypton_order_id,
    fus.krypton_order_uuid,
    fus.woosoo_order_id,
    fus.woosoo_order_uuid,
    fus.crosswalk_status,
    fus.amount_reconciliation,
    fus.krypton_total,
    fus.woosoo_total,
    COALESCE(fus.krypton_total, 0) - COALESCE(fus.woosoo_total, 0),
    fus.opened_delta_seconds,
    fus.closed_delta_seconds,
    fus.krypton_opened_at,
    fus.woosoo_opened_at,
    CASE
      WHEN fus.crosswalk_status = 'UNLINKED' THEN 'HIGH'
      WHEN fus.amount_reconciliation = 'AMOUNT_MISMATCH' THEN 'HIGH'
      WHEN ABS(COALESCE(fus.opened_delta_seconds, 0)) > 300 THEN 'MEDIUM'
      ELSE 'LOW'
    END,
    CONCAT_WS(' | ', 
      CASE WHEN fus.crosswalk_status = 'UNLINKED' THEN 'No crosswalk link' ELSE NULL END,
      CASE WHEN fus.amount_reconciliation = 'AMOUNT_MISMATCH' 
        THEN 'Amount delta: ' || (COALESCE(fus.krypton_total, 0) - COALESCE(fus.woosoo_total, 0))::TEXT
        ELSE NULL END
    )
  ON CONFLICT (snapshot_date, krypton_order_id) DO UPDATE
  SET notes = EXCLUDED.notes, created_at = NOW();
END
$$ LANGUAGE plpgsql;

-- =============================================================================
-- SECTION 5: SUMMARY VIEWS FOR BI DASHBOARDS
-- =============================================================================

-- 5.1 Order Summary by Status (MySQL & PostgreSQL)
CREATE OR REPLACE VIEW bi_order_summary_by_status AS
SELECT
  crosswalk_status,
  amount_reconciliation,
  COUNT(*) AS order_count,
  SUM(krypton_total) AS total_krypton_amount,
  SUM(woosoo_total) AS total_woosoo_amount,
  AVG(ABS(COALESCE(opened_delta_seconds, 0))) AS avg_opened_delta_sec,
  MIN(krypton_opened_at) AS earliest_order,
  MAX(krypton_opened_at) AS latest_order
FROM bi_krypton_woosoo_order_fusion
GROUP BY crosswalk_status, amount_reconciliation;

-- 5.2 Drift Issues Summary (MySQL & PostgreSQL)
CREATE OR REPLACE VIEW bi_drift_issues_summary AS
SELECT
  severity,
  COUNT(*) AS issue_count,
  GROUP_CONCAT(DISTINCT issue_type_crosswalk) AS crosswalk_issues,
  GROUP_CONCAT(DISTINCT issue_type_amount) AS amount_issues,
  GROUP_CONCAT(DISTINCT issue_type_timing) AS timing_issues
FROM bi_order_drift_detection
GROUP BY severity;

-- =============================================================================
-- SECTION 6: HELPER VIEWS FOR DIMENSION TABLES
-- =============================================================================

-- 6.1 Krypton Order Metadata (MySQL & PostgreSQL)
CREATE OR REPLACE VIEW bi_krypton_order_metadata AS
SELECT
  o.id AS krypton_order_id,
  o.order_uuid,
  o.date_time_opened,
  o.date_time_closed,
  o.total_amount,
  COUNT(DISTINCT om.id) AS item_count,
  SUM(om.quantity) AS total_qty,
  COUNT(DISTINCT oc.id) AS check_count,
  MAX(oc.is_settled) AS is_settled,
  MAX(oc.is_voided) AS is_voided
FROM krypton_woosoo.orders o
LEFT JOIN krypton_woosoo.ordered_menus om ON o.id = om.order_id
LEFT JOIN krypton_woosoo.order_checks oc ON o.id = oc.order_id
GROUP BY o.id, o.order_uuid, o.date_time_opened, o.date_time_closed, o.total_amount;

-- 6.2 Woosoo Order Metadata (MySQL & PostgreSQL)
CREATE OR REPLACE VIEW bi_woosoo_order_metadata AS
SELECT
  o.id AS woosoo_order_id,
  o.order_uuid,
  o.date_time_opened,
  o.date_time_closed,
  o.total_amount,
  COUNT(DISTINCT do.id) AS device_order_count,
  COUNT(DISTINCT do.device_id) AS unique_device_count,
  COUNT(DISTINCT doi.id) AS item_count,
  SUM(doi.quantity) AS total_qty
FROM woosoo.orders o
LEFT JOIN woosoo.device_orders do ON o.id = do.order_id
LEFT JOIN woosoo.device_order_items doi ON do.order_id = doi.order_id
GROUP BY o.id, o.order_uuid, o.date_time_opened, o.date_time_closed, o.total_amount;

-- =============================================================================
-- SECTION 7: INDEXES FOR PERFORMANCE OPTIMIZATION
-- =============================================================================

-- MySQL Indexes:
ALTER TABLE woosoo_crosswalk_orders ADD INDEX idx_krypton_order_id (krypton_order_id);
ALTER TABLE woosoo_crosswalk_orders ADD INDEX idx_woosoo_order_id (woosoo_order_id);
ALTER TABLE krypton_woosoo.ordered_menus ADD INDEX idx_order_id (order_id);
ALTER TABLE woosoo.device_order_items ADD INDEX idx_order_id (order_id);

-- PostgreSQL Indexes:
CREATE INDEX idx_woosoo_crosswalk_krypton ON woosoo_crosswalk_orders (krypton_order_id);
CREATE INDEX idx_woosoo_crosswalk_woosoo ON woosoo_crosswalk_orders (woosoo_order_id);
CREATE INDEX idx_krypton_ordered_menus ON krypton_woosoo.ordered_menus (order_id);
CREATE INDEX idx_woosoo_device_order_items ON woosoo.device_order_items (order_id);

-- =============================================================================
-- SECTION 8: USAGE EXAMPLES FOR BI TOOLS
-- =============================================================================

-- Query 1: Get order reconciliation for order_id = 19643
-- SELECT * FROM bi_krypton_woosoo_order_fusion WHERE krypton_order_id = 19643;

-- Query 2: Detect all drifted orders (last 7 days)
-- SELECT * FROM bi_order_drift_detection 
-- WHERE detected_at >= DATE_SUB(NOW(), INTERVAL 7 DAY);

-- Query 3: Line-item reconciliation for problematic orders
-- SELECT * FROM bi_line_item_reconciliation 
-- WHERE item_reconciliation_status != 'MATCHED';

-- Query 4: Daily reconciliation snapshot (for BI ingestion)
-- SELECT * FROM bi_order_reconciliation_snapshot 
-- WHERE snapshot_date = CURDATE();

-- Query 5: Drift summary by severity
-- SELECT * FROM bi_drift_issues_summary ORDER BY severity DESC;

-- =============================================================================
-- SECTION 9: REFRESH SCHEDULE (Recommendations)
-- =============================================================================

-- For MySQL: Schedule via cron or Events
-- CREATE EVENT IF NOT EXISTS refresh_bi_snapshot_daily
-- ON SCHEDULE EVERY 1 DAY
-- STARTS CURRENT_TIMESTAMP + INTERVAL 1 DAY
-- DO CALL refresh_bi_order_reconciliation_snapshot();

-- For PostgreSQL: Schedule via pg_cron extension (requires installation)
-- SELECT cron.schedule('refresh_bi_snapshot_daily', '0 2 * * *', 
--   'SELECT refresh_bi_order_reconciliation_snapshot();');

-- =============================================================================
-- SECTION 10: GRANT PERMISSIONS (Read-Only for BI Users)
-- =============================================================================

-- MySQL:
-- CREATE USER 'bi_readonly'@'%' IDENTIFIED BY 'secure_password';
-- GRANT SELECT ON krypton_woosoo.* TO 'bi_readonly'@'%';
-- GRANT SELECT ON woosoo.* TO 'bi_readonly'@'%';
-- GRANT SELECT ON bi_schema.* TO 'bi_readonly'@'%';
-- FLUSH PRIVILEGES;

-- PostgreSQL:
-- CREATE ROLE bi_readonly WITH LOGIN PASSWORD 'secure_password';
-- GRANT CONNECT ON DATABASE krypton_woosoo TO bi_readonly;
-- GRANT USAGE ON SCHEMA public TO bi_readonly;
-- GRANT SELECT ON ALL TABLES IN SCHEMA public TO bi_readonly;
-- ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT ON TABLES TO bi_readonly;

