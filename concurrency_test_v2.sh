#!/bin/sh
set -e

cd /Users/user/microbiz/microbiz-os

BASE_URL="http://127.0.0.1:8000"
TELLER_ID=2
CUSTOMER_ACCOUNT_ID=1
TOKEN="12|6FDlOLOsIBrORRdlpEUTycSYVyM7Ay8uQ1rbghfI2ecc97aa"
WITHDRAWAL_AMOUNT=1000
CONCURRENT_REQUESTS=10
STARTING_BALANCE=5000

echo "=== Setting known starting balance ($STARTING_BALANCE) ==="
php artisan tinker --execute="\App\Models\TellerBalance::where('teller_id', $TELLER_ID)->update(['ledger_balance' => $STARTING_BALANCE, 'available_balance' => $STARTING_BALANCE]); echo 'Balance set.';"

echo ""
echo "=== Confirmed pre-test balance (ground truth via Tinker, not the API) ==="
php artisan tinker --execute="\$b = \App\Models\TellerBalance::where('teller_id', $TELLER_ID)->first(); echo 'available_balance: ' . \$b->available_balance;"

echo ""
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
echo "=== Full results, including actual rejection reasons ==="
SUCCESS_COUNT=0
for i in $(seq 1 $CONCURRENT_REQUESTS); do
  RESULT=$(cat "/tmp/concurrency_result_$i.json")
  echo "--- Request $i ---"
  echo "$RESULT" | python3 -m json.tool 2>/dev/null || echo "$RESULT"
  SUCCESS=$(echo "$RESULT" | python3 -c "import json,sys; print(json.load(sys.stdin).get('success', False))" 2>/dev/null || echo "PARSE_ERROR")
  if [ "$SUCCESS" = "True" ]; then
    SUCCESS_COUNT=$((SUCCESS_COUNT + 1))
  fi
done

echo ""
echo "=== Post-test balance (ground truth via Tinker) ==="
php artisan tinker --execute="\$b = \App\Models\TellerBalance::where('teller_id', $TELLER_ID)->first(); echo 'available_balance: ' . \$b->available_balance;"

echo ""
echo ""
echo "=== Verdict ==="
EXPECTED_MAX=$((STARTING_BALANCE / WITHDRAWAL_AMOUNT))
echo "Successful withdrawals: $SUCCESS_COUNT (mathematically possible maximum: $EXPECTED_MAX, since $STARTING_BALANCE / $WITHDRAWAL_AMOUNT = $EXPECTED_MAX)"
echo "Confirm above that available_balance is non-negative and exactly matches: $STARTING_BALANCE - (SUCCESS_COUNT x $WITHDRAWAL_AMOUNT)"
echo "Review each request's actual message above -- every failure should say"
echo "'Insufficient teller cash balance.' specifically. Any other error"
echo "(timeout, deadlock, connection reset) means lockForUpdate() caused a"
echo "real problem under contention, not just correct rejection."

rm -f /tmp/concurrency_result_*.json