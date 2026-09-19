#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

API="${API_URL:-http://localhost:8010}"
KEY="${CRM_FLOW_API_KEY:-cf_live_demo_a1b2c3d4e5f6}"
SECRET="${CRM_FLOW_PAYMENT_WEBHOOK_SECRET:-crmflow-payment-whsec}"

echo "== 1. 5,000-request campaign burst =="
python3 - <<'PY'
import json, time, urllib.request, os, statistics
api = os.environ.get("API_URL", "http://localhost:8010")
key = os.environ.get("CRM_FLOW_API_KEY", "cf_live_demo_a1b2c3d4e5f6")
latencies = []
statuses = {}
for i in range(5000):
    body = json.dumps({"name": f"Burst {i}", "email": f"burst{i}@example.com", "form_id": "form-spring"}).encode()
    req = urllib.request.Request(f"{api}/api/v1/leads", data=body, headers={
        "Content-Type": "application/json",
        "X-Api-Key": key,
    })
    t0 = time.perf_counter()
    try:
        with urllib.request.urlopen(req, timeout=10) as res:
            status = res.status
    except urllib.error.HTTPError as e:
        status = e.code
    latencies.append((time.perf_counter() - t0) * 1000)
    statuses[status] = statuses.get(status, 0) + 1
print(json.dumps({
    "statuses": statuses,
    "p50_ms": round(statistics.median(latencies), 2),
    "p95_ms": round(sorted(latencies)[int(len(latencies)*0.95)-1], 2),
    "max_ms": round(max(latencies), 2),
    "min_ms": round(min(latencies), 2),
}, indent=2))
PY

echo
echo "== 2. Race two agents onto one lead =="
docker compose -f "$ROOT/docker-compose.yml" exec -T app php artisan tinker --execute="
\$lead = App\Models\Lead::query()->where('state', 'queued')->first();
if (!\$lead) { \$lead = App\Models\Lead::factory()->create(['state' => 'queued']); }
echo \$lead->id;
" > /tmp/crmflow-lead-id
LEAD=$(tr -d '\r\n' < /tmp/crmflow-lead-id | tail -n 1)
echo "Lead: $LEAD"
A=$(curl -s -o /tmp/a.json -w "%{http_code}" -X POST "$API/api/v1/leads/$LEAD/claim" -H "Content-Type: application/json" -d '{"agent_id":1}')
B=$(curl -s -o /tmp/b.json -w "%{http_code}" -X POST "$API/api/v1/leads/$LEAD/claim" -H "Content-Type: application/json" -d '{"agent_id":2}')
echo "Agent 1: HTTP $A $(cat /tmp/a.json)"
echo "Agent 2: HTTP $B $(cat /tmp/b.json)"

echo
echo "== 3. Duplicate payment webhook replay =="
ORDER=$(docker compose -f "$ROOT/docker-compose.yml" exec -T app php artisan tinker --execute="echo App\Models\Order::query()->value('id');")
if [ -z "$ORDER" ] || [ "$ORDER" = "" ]; then
  echo "No order yet — converting a working lead is required first. Skipping body if empty."
fi
BODY=$(cat <<JSON
{"provider":"checkouthub","event_id":"evt_demo_replay","type":"payment.succeeded","order_id":"${ORDER:-00000000-0000-0000-0000-000000000000}","payload":{"amount_minor":4900}}
JSON
)
echo "First:"; curl -s -H "X-Webhook-Secret: $SECRET" -H "Content-Type: application/json" -d "$BODY" "$API/api/v1/webhooks/payments"; echo
echo "Replay:"; curl -s -H "X-Webhook-Secret: $SECRET" -H "Content-Type: application/json" -d "$BODY" "$API/api/v1/webhooks/payments"; echo

echo
echo "== 4. Break a claim lock and show auto-release =="
docker compose -f "$ROOT/docker-compose.yml" exec -T app php artisan tinker --execute="App\Models\Lead::query()->where('state', 'claimed')->update(['claimed_at' => now()->subHour()]);"
echo "Deleting claim locks to simulate a crashed agent session..."
docker compose -f "$ROOT/docker-compose.yml" exec -T redis redis-cli EVAL "for _,k in ipairs(redis.call('keys', 'crmflow_lock:lead:*')) do redis.call('del', k) end; return 1" 0
docker compose -f "$ROOT/docker-compose.yml" exec -T app php artisan crmflow:release-expired
echo "Done. Claimed leads older than the lock TTL whose Redis lock vanished are back in queued."
