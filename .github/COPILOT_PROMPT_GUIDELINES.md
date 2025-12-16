# Copilot Agent Guidelines

Purpose
-------
These rules standardize how automated Copilot-style agents operate in this repository. They are written for human reviewers and automated agents that act on behalf of humans.

Core rules
----------
- Only modify files explicitly requested by the human or files created by this PR. Do not change unrelated files.
- Never write secrets (API keys, tokens, private keys) to repository files, commits, or messages. If secrets are required, ask the human how to provision them outside the repo.
- If any required field in a user instruction is UNKNOWN, ask exactly one clarifying question and stop until the user answers.
- Use the repository `manage_todo_list` pattern to track multi-step work. Create a concise plan and mark steps complete as you go.
- Run the appropriate linters and tests locally when feasible, and include the commands used in the PR. If tests cannot be run, explain why in the PR.
- Include the full [PR-TEMPLATE] skeleton (see docs/EXAMPLES.md) in the PR body when opening a PR.

Repository-specific restrictions
-------------------------------
- PWA (tablet-ordering-pwa): Do NOT modify `nuxt.config.ts` or any service worker files in this PR. Offline behavior must be preserved unless the user explicitly requests service-worker changes.
- Relay device (relay-device): Do NOT commit compiled firmware or binary artifacts. Any firmware-level code changes must include `architect_approve: true` and a `test_plan` field in the PR body.

Quality and safety
------------------
- Keep changes minimal and focused. Prefer small commits with clear messages (`chore:`, `fix:`, `feat:` prefixes).
- Do not generate or commit large binary blobs. If build artifacts are needed for verification, produce instructions to reproduce them locally instead.
- When making changes that affect runtime behavior, include verification steps and the commands to run them.

Enforcement
-----------
- A repository GitHub Action (`.github/workflows/pr_template_check.yml`) validates that PR bodies include the required `[PR-TEMPLATE]` skeleton. Maintainers may reject PRs that do not follow these rules.

If blocked
----------
If you are blocked by missing information, authentication, or hardware, ask exactly one concise clarifying question and stop.

Revision: 2025-12-16

