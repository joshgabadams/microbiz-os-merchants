#!/bin/sh
set -e

BASE_URL="http://127.0.0.1:8000"
TELLER_ID=1
CUSTOMER_ACCOUNT_ID=1
TOKEN="PASTE_A_VALID_TOKEN_HERE"
WITHDRAWAL_AMOUNT=1000
CONCURRENT_REQUESTS=10

echo "=== Pre-test balance ==="
curl -s "$BASE_URL/api/tellers/$TELLER_ID" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" | python3 -m json.tool

echo ""
echo "=== Firing $CONCURRENT_REQUESTS simultaneous withdrawal requests of $WITHDRAWAL_AMOUNT each ==="

for i in $(seq 1 $CONCURRENT_REQUESTS); do
  curl -s -X POST "$BASE_URL/api/v1/customer/withdraw" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d "{\"teller_id\":$TELLER_ID,\"customer_account_id\":$CUSTOMER_ACCOUNT_ID,\"amount\":$WITHDRAWAL_AMOUNT}" \
    > "/tmp/concurrency_result_$i.json" &
done

wait

echo ""
echo "=== Results ==="
SUCCESS_COUNT=0
for i in $(seq 1 $CONCURRENT_REQUESTS); do
  RESULT=$(cat "/tmp/concurrency_result_$i.json")
  SUCCESS=$(echo "$RESULT" | python3 -c "import json,sys; print(json.load(sys.stdin).get('success', False))" 2>/dev/null || echo "PARSE_ERROR")
  echo "Request $i: success=$SUCCESS"
  if [ "$SUCCESS" = "True" ]; then
    SUCCESS_COUNT=$((SUCCESS_COUNT + 1))
  fi
done

echo ""
echo "=== Post-test balance ==="
curl -s "$BASE_URL/api/tellers/$TELLER_ID" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" | python3 -m json.tool

echo ""
echo "=== Verdict ==="
echo "Successful withdrawals: $SUCCESS_COUNT (expected: at most 5, since 5 x 1000 = 5000)"
echo "Manually confirm above that available_balance never went negative."
echo "If more than 5 requests succeeded, or balance is negative, lockForUpdate() did NOT protect against this race condition -- report this back for investigation."

rm -f /tmp/concurrency_result_*.json
