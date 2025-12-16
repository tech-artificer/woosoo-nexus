# Agent Workflows

This document describes the expected local workflow for Copilot-style agents making repository changes.

Local workflow (agent)
----------------------
1. Create a new branch from the current mainline commit:

   git switch -c chore/copilot-guidelines

2. Add the required files under `.github/` and `docs/`.

3. Stage only the files the agent created or intentionally modified:

   git add .github/COPILOT_PROMPT_GUIDELINES.md .github/PROMPT_TEMPLATES.md docs/AGENT_WORKFLOWS.md docs/EXAMPLES.md .github/workflows/pr_template_check.yml

4. Commit with a clear message:

   git commit -m "chore: add Copilot agent guidelines and enforcement"

5. Run verification commands (repository-specific). Examples:

   # backend
   composer install || true
   ./vendor/bin/pest --filter=none || true

   # frontend
   npm ci || true
   npm run lint || true

6. If verification succeeds, push the branch and open a PR, including the `[PR-TEMPLATE]` skeleton in the PR body.

If unable to push or create PR due to missing auth, stop and request a GitHub PAT or for a human to push/open the PR.

CI enforcement
--------------
This repository includes a GitHub Action `.github/workflows/pr_template_check.yml` that validates PR bodies contain the required `[PR-TEMPLATE]` skeleton.
# Agent Workflows

This document explains the agent lifecycle and the verification commands for this repository.

Agent lifecycle
---------------
1. Clarify: If any required field is UNKNOWN, ask one question and stop.
2. Plan: Use `manage_todo_list` to create a small list of concrete steps.
3. Implement: Create branch `chore/copilot-guidelines` (or a feature branch specified by the user) and make minimal changes.
4. Verify: Run linters and tests locally where possible.
5. Commit: Add only the intended files (`git add <files>`), commit with a clear message.
6. PR: Open a PR to `main` including the full `[PR-TEMPLATE]` skeleton in the body.

Verification commands (copy/paste)
--------------------------------

Repository (root) - Quick checks
```powershell
composer install || true
./vendor/bin/pest --filter=none || true
npm ci || true
npm run lint || true
```

PWA - tablet-ordering-pwa
```powershell
cd tablet-ordering-pwa
npm ci
npm run lint || true
npm run test || true
```

Relay device - relay-device
```powershell
cd relay-device
flutter pub get || true
flutter test || true
if (Test-Path ./build.sh) { ./build.sh --dry-run || true }
```

Notes
-----
- Do not commit binaries or firmware images into the repository. For device firmware changes, require `architect_approve` and attach a test plan.
- PWA offline behavior must be preserved unless explicitly requested; do not change `nuxt.config.ts` or service worker files in guideline PRs.

