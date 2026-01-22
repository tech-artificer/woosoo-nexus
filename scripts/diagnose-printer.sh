#!/bin/bash
echo "=== Printer Device Diagnostics ==="
echo ""

echo "1. Check device registration:"
mysql -u root woosoo_api -e "SELECT id, device_uuid, name, branch_id, ip_address, is_active FROM devices WHERE name LIKE '%print%';"

echo ""
echo "2. Check unacknowledged print events:"
mysql -u root woosoo_api -e "SELECT COUNT(*) as total, COUNT(DISTINCT device_order_id) as orders FROM print_events WHERE is_acknowledged = 0;"

echo ""
echo "3. Check recent print events:"
mysql -u root woosoo_api -e "SELECT pe.id, pe.event_type, do.order_id, pe.created_at FROM print_events pe LEFT JOIN device_orders do ON pe.device_order_id = do.id WHERE pe.is_acknowledged = 0 ORDER BY pe.created_at DESC LIMIT 5;"

echo ""
echo "4. Test device login (update IP) - HTTP:"
curl -s -X POST "http://192.168.100.85:8000/api/devices/login" \
  -H "Content-Type: application/json" \
  -d '{"ip_address":"192.168.100.XX"}' | python -m json.tool || echo "Note: Update IP address above"

echo ""
echo "5. Check Reverb status - HTTP:"
curl -s "http://192.168.100.85:6001/" | head -5 || echo "Reverb may not be accessible via HTTP"

echo ""
echo "=== Summary ==="
echo "If device is registered and print events exist, check:"
echo "1. Relay device logs for WebSocket connection errors"
echo "2. Laravel logs: tail -f storage/logs/laravel.log | grep -i print"
echo "3. Ensure relay device is using HTTP (not HTTPS)"
