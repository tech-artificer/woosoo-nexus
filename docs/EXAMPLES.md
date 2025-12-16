# Examples and Expected Deliverables

This document contains example outputs and expected deliverables for Copilot agent changes.

Example PR body (required skeleton included)

[PR-TEMPLATE]
title: chore: add Copilot agent guidelines and enforcement
motivation: Standardize how Copilot-style agents operate on this repository.
changes:

- .github/COPILOT_PROMPT_GUIDELINES.md: add strict agent rules and repo purpose
- .github/PROMPT_TEMPLATES.md: add required prompt templates
- docs/AGENT_WORKFLOWS.md: add workflows and verification commands
- docs/EXAMPLES.md: add examples and expected deliverables
- .github/workflows/pr_template_check.yml: add PR-body validation Action

verification:
run: composer install || true (backend)
run: npm ci && npm run lint || true (frontend)

acceptance_criteria:
All five files present on branch chore/copilot-guidelines
PR contains the [PR-TEMPLATE] skeleton
No secrets or compiled binaries committed

risk_level: low

Example verification commands and expected results

- `composer install || true` — expected: command completes (exit 0) or reports missing composer; vendor files not committed
- `npm ci` — expected: dependencies installed cleanly
- `npm run lint` — expected: either no lint errors or a list of lint messages; agent should include exit codes and logs if failures occur

Example agent deliverable list

- One paragraph summary (what was done and why)
- Exact list of files added or modified
- Commands to verify locally and their expected outputs
- Tests run (or reason not run) and their outputs/exit codes
- PR title and PR body (including `[PR-TEMPLATE]`)
# Examples and PR Skeletons

This file contains example prompts, PR body skeletons and expected deliverables for agent authors and reviewers.

PR Body Skeleton (copy/paste into PR body)

[PR-TEMPLATE] title: chore: add Copilot agent guidelines and enforcement
[PR-TEMPLATE] motivation: Standardize how Copilot-style agents operate on this repository. 
[PR-TEMPLATE] changes:

.github/COPILOT_PROMPT_GUIDELINES.md: add strict agent rules and repo purpose
.github/PROMPT_TEMPLATES.md: add required prompt templates
docs/AGENT_WORKFLOWS.md: add workflows and verification commands
docs/EXAMPLES.md: add examples and expected deliverables
.github/workflows/pr_template_check.yml: add PR-body validation Action

[PR-TEMPLATE] verification:
run: composer install || true (backend)
run: npm ci && npm run lint || true (frontend) 

[PR-TEMPLATE] acceptance_criteria:
All five files present on branch chore/copilot-guidelines
PR contains the [PR-TEMPLATE] skeleton
No secrets or compiled binaries committed

[PR-TEMPLATE] risk_level: low

[PR-TEMPLATE] tests: NONE

How to use
----------
- Paste the skeleton into a PR body and fill in any repo-specific notes. For PWA PRs add offline-testing notes. For relay-device PRs add `architect_approve` and `test_plan` fields if firmware changes are involved.

Example prompt (short)
----------------------
Add Copilot agent guidelines to this repo. Files to create: .github/COPILOT_PROMPT_GUIDELINES.md, .github/PROMPT_TEMPLATES.md, docs/AGENT_WORKFLOWS.md, docs/EXAMPLES.md, .github/workflows/pr_template_check.yml. Run `composer install || true` and `npm ci` to verify. Do not push or open the PR; prepare the branch and commit locally.

