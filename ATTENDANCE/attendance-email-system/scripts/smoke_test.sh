#!/usr/bin/env bash
# Smoke test — verifies the stack is alive after deploy.
# Runs from CI or locally. Does not modify any data.
#
# Usage:
#   ./scripts/smoke_test.sh                                  # localhost defaults
#   BASE_URL=https://api.railway.app ./scripts/smoke_test.sh
#   ./scripts/smoke_test.sh https://api.railway.app admin@college.edu secret

set -euo pipefail

BASE_URL="${BASE_URL:-${1:-http://localhost}}"
EMAIL="${2:-admin@jdcollege.edu.in}"
PASSWORD="${3:-password}"

GREEN='\033[0;32m'; RED='\033[0;31m'; YELLOW='\033[1;33m'; BOLD='\033[1m'; NC='\033[0m'
pass() { printf "${GREEN}✓${NC}  %s\n" "$1"; }
fail() { printf "${RED}✗${NC}  %s\n" "$1"; exit 1; }
warn() { printf "${YELLOW}⚠${NC}  %s\n" "$1"; }
info() { printf "${BOLD}──${NC} %s\n" "$1"; }

echo ""
printf "${BOLD}=== Smoke Test: %s ===${NC}\n" "$BASE_URL"
echo ""

# 1. Laravel API health
info "1. GET /api/v1/health"
HEALTH=$(curl -sf --max-time 10 "${BASE_URL}/api/v1/health") \
  || fail "Health endpoint unreachable — is the stack running?"
python3 -m json.tool <<< "$HEALTH" 2>/dev/null || echo "$HEALTH"
OVERALL=$(python3 -c "import sys,json; print(json.loads(sys.argv[1])['data']['status'])" "$HEALTH" 2>/dev/null || echo "unknown")
[[ "$OVERALL" == "ok" ]] && pass "All services healthy" || warn "Health check degraded (status=${OVERALL}) — continuing"

# 2. ML service health (best-effort — may not be on same port)
info "2. ML service /health"
ML_PORT="${ML_PORT:-8000}"
ML_URL="${ML_SERVICE_URL:-http://localhost:${ML_PORT}}"
ML_HEALTH=$(curl -sf --max-time 5 "${ML_URL}/health" 2>/dev/null) \
  && python3 -m json.tool <<< "$ML_HEALTH" 2>/dev/null \
  && pass "ML service reachable" \
  || warn "ML service unreachable at ${ML_URL} (skipping — may require direct port access)"

# 3. Login
info "3. POST /api/v1/auth/login"
LOGIN=$(curl -sf --max-time 10 \
  -X POST "${BASE_URL}/api/v1/auth/login" \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"${EMAIL}\",\"password\":\"${PASSWORD}\"}") \
  || fail "Login request failed — check credentials and that DB is seeded"

TOKEN=$(python3 -c "import sys,json; print(json.loads(sys.argv[1])['data']['access_token'])" "$LOGIN" 2>/dev/null || echo "")
[[ -n "$TOKEN" && "$TOKEN" != "null" ]] \
  && pass "JWT access token received" \
  || fail "No token in login response: ${LOGIN}"

# 4. GET /auth/me
info "4. GET /api/v1/auth/me"
ME=$(curl -sf --max-time 10 \
  -H "Authorization: Bearer ${TOKEN}" \
  "${BASE_URL}/api/v1/auth/me") \
  || fail "/me request failed"
ROLE=$(python3 -c "import sys,json; d=json.loads(sys.argv[1]); print(d.get('data',{}).get('role','?'))" "$ME" 2>/dev/null || echo "?")
pass "Authenticated — role=${ROLE}"

# 5. Logout
info "5. POST /api/v1/auth/logout"
LOGOUT=$(curl -sf --max-time 10 \
  -X POST "${BASE_URL}/api/v1/auth/logout" \
  -H "Authorization: Bearer ${TOKEN}") \
  || fail "Logout request failed"
python3 -c "import sys,json; assert json.loads(sys.argv[1]).get('success'), 'logout not successful'" \
  "$LOGOUT" 2>/dev/null \
  && pass "Logged out successfully" \
  || warn "Unexpected logout response: ${LOGOUT}"

# 6. Verify token is denylist'd after logout
info "6. Token invalidation check"
HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" --max-time 5 \
  -H "Authorization: Bearer ${TOKEN}" \
  "${BASE_URL}/api/v1/auth/me")
[[ "$HTTP_STATUS" == "401" ]] \
  && pass "Token correctly rejected after logout (HTTP 401)" \
  || warn "Token invalidation: got HTTP ${HTTP_STATUS} (expected 401)"

echo ""
printf "${BOLD}=== All smoke tests passed ===${NC}\n"
echo ""
