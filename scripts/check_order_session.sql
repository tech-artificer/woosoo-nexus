SELECT 
    do.id as device_order_id,
    do.order_id,
    do.session_id as order_session_id,
    do.status,
    do.table_id,
    do.device_id,
    do.created_at,
    (SELECT id FROM krypton_woosoo.sessions ORDER BY id DESC LIMIT 1) as latest_session_id,
    CASE 
        WHEN do.session_id = (SELECT id FROM krypton_woosoo.sessions ORDER BY id DESC LIMIT 1) 
        THEN 'SESSION MATCH - Will appear in live orders'
        ELSE 'SESSION MISMATCH - Filtered out by OrderController'
    END as diagnosis
FROM device_orders do
WHERE do.order_id = 19598;
