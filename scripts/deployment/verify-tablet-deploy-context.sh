#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
NEXUS_DIR="${NEXUS_DIR:-$ROOT_DIR}"
TABLET_DIR="${TABLET_DIR:-$(cd "$NEXUS_DIR/.." && pwd)/tablet-ordering-pwa}"
COMPOSE_FILE="${COMPOSE_FILE:-$NEXUS_DIR/compose.yaml}"
ALLOW_DIRTY="${ALLOW_DIRTY:-0}"

compose_cmd=(docker compose -f "$COMPOSE_FILE")

require_repo() {
  local path="$1"
  local name="$2"
  if [[ ! -d "$path/.git" ]]; then
    echo "ERROR: ${name} repo not found at ${path}" >&2
    exit 1
  fi
}

repo_branch() {
  git -C "$1" rev-parse --abbrev-ref HEAD
}

repo_commit() {
  git -C "$1" rev-parse HEAD
}

repo_status() {
  if [[ -n "$(git -C "$1" status --porcelain)" ]]; then
    echo "dirty"
  else
    echo "clean"
  fi
}

require_repo "$NEXUS_DIR" "Nexus"
require_repo "$TABLET_DIR" "Tablet"

nexus_branch="$(repo_branch "$NEXUS_DIR")"
nexus_commit="$(repo_commit "$NEXUS_DIR")"
nexus_status="$(repo_status "$NEXUS_DIR")"

tablet_branch="$(repo_branch "$TABLET_DIR")"
tablet_commit="$(repo_commit "$TABLET_DIR")"
tablet_status="$(repo_status "$TABLET_DIR")"

if [[ "$ALLOW_DIRTY" != "1" ]]; then
  if [[ "$nexus_status" != "clean" ]]; then
    echo "ERROR: Nexus repo is dirty. Commit/stash changes or set ALLOW_DIRTY=1." >&2
    exit 1
  fi
  if [[ "$tablet_status" != "clean" ]]; then
    echo "ERROR: Tablet repo is dirty. Commit/stash changes or set ALLOW_DIRTY=1." >&2
    exit 1
  fi
fi

export TABLET_BUILD_NEXUS_BRANCH="${TABLET_BUILD_NEXUS_BRANCH:-$nexus_branch}"
export TABLET_BUILD_NEXUS_COMMIT="${TABLET_BUILD_NEXUS_COMMIT:-$nexus_commit}"
export TABLET_BUILD_TABLET_BRANCH="${TABLET_BUILD_TABLET_BRANCH:-$tablet_branch}"
export TABLET_BUILD_TABLET_COMMIT="${TABLET_BUILD_TABLET_COMMIT:-$tablet_commit}"
export TABLET_BUILD_FINGERPRINT="${TABLET_BUILD_FINGERPRINT:-${TABLET_BUILD_NEXUS_COMMIT:0:12}-${TABLET_BUILD_TABLET_COMMIT:0:12}}"

compose_config="$("${compose_cmd[@]}" config)"

if [[ -z "$compose_config" ]]; then
  echo "ERROR: Failed to resolve compose config for ${COMPOSE_FILE}" >&2
  exit 1
fi

resolved_tablet_service="$(
  printf '%s\n' "$compose_config" | awk '
    /^services:/ {in_services=1; next}
    in_services && /^  tablet-pwa:/ {in_block=1}
    in_block {
      if ($0 ~ /^[^[:space:]]/ || ($0 ~ /^  [^[:space:]][^:]*:/ && $0 !~ /^  tablet-pwa:/)) exit
      print
    }
  '
)"

if [[ -z "$resolved_tablet_service" ]]; then
  echo "ERROR: tablet-pwa service not found in resolved compose config" >&2
  exit 1
fi

dockerfile_line="$(printf '%s\n' "$resolved_tablet_service" | awk -F': ' '/^[[:space:]]+dockerfile:/ {print $2; exit}')"
resolved_dockerfile="${dockerfile_line:-<unknown>}"

resolved_build_args="$(
  printf '%s\n' "$resolved_tablet_service" | awk '
    /^[[:space:]]+args:/ {capture=1; next}
    capture {
      if ($0 ~ /^[[:space:]]{4}[[:alnum:]_.-]+:/) exit
      if ($0 ~ /^[[:space:]]*$/) next
      print
    }
  '
)"

resolved_runtime_env="$(
  printf '%s\n' "$resolved_tablet_service" | awk '
    /^[[:space:]]+environment:/ {capture=1; next}
    capture {
      if ($0 ~ /^[[:space:]]{4}[[:alnum:]_.-]+:/) exit
      if ($0 ~ /^[[:space:]]*$/) next
      print
    }
  '
)"

echo "=== Tablet Deploy Context Preflight ==="
echo "Nexus repo:    ${NEXUS_DIR}"
echo "Nexus branch:  ${nexus_branch}"
echo "Nexus commit:  ${nexus_commit}"
echo "Nexus status:  ${nexus_status}"
echo
echo "Tablet repo:   ${TABLET_DIR}"
echo "Tablet branch: ${tablet_branch}"
echo "Tablet commit: ${tablet_commit}"
echo "Tablet status: ${tablet_status}"
echo
echo "Build fingerprint args:"
echo "  TABLET_BUILD_NEXUS_BRANCH=${TABLET_BUILD_NEXUS_BRANCH}"
echo "  TABLET_BUILD_NEXUS_COMMIT=${TABLET_BUILD_NEXUS_COMMIT}"
echo "  TABLET_BUILD_TABLET_BRANCH=${TABLET_BUILD_TABLET_BRANCH}"
echo "  TABLET_BUILD_TABLET_COMMIT=${TABLET_BUILD_TABLET_COMMIT}"
echo "  TABLET_BUILD_FINGERPRINT=${TABLET_BUILD_FINGERPRINT}"
echo

echo "Resolved tablet Dockerfile: ${resolved_dockerfile}"
echo "Resolved tablet build args:"
if [[ -n "$resolved_build_args" ]]; then
  printf '%s\n' "$resolved_build_args"
else
  echo "  <none>"
fi

echo "Resolved tablet runtime env:"
if [[ -n "$resolved_runtime_env" ]]; then
  printf '%s\n' "$resolved_runtime_env"
else
  echo "  <none>"
fi

echo "Resolved compose service (tablet-pwa):"
printf '%s\n' "$resolved_tablet_service"
