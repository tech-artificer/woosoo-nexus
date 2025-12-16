PR and agent prompt templates
============================

This file contains the required `[PR-TEMPLATE]` skeleton and common prompt templates agents should use when interacting with maintainers.

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

tests: NONE

Agent prompt templates
----------------------

1) Create a branch and add files (feature)

Prompt: "Create a new branch `chore/copilot-guidelines`, add the five guideline files, run verification commands, and prepare the PR body that includes the `[PR-TEMPLATE]` skeleton. If any step fails, include failing logs and remediation steps."

2) Small change / doc update

Prompt: "Add or update documentation file X with the following content... Commit to `chore/copilot-guidelines` and provide the exact git commands used."

3) If blocked by auth

Prompt: "I cannot push/create PR due to missing GitHub auth. Please supply a GitHub PAT with repo scope or push the branch and open the PR manually."
# Prompt Templates for Agents

Use these templates when asking an automated agent to perform work. Fill in the fields exactly.

Template skeleton
-----------------
- template: <feature|bugfix|chore|docs>
- repo: <owner/repo>
- target_branch: <branch>
- files_to_change: <list of file paths>
- motivation: <short reason for change>
- acceptance_criteria: <what success looks like>
- run_commands: <commands the agent should run locally to verify>
- tests_to_add_or_run: <tests to add or run, or NONE>
- risk_level: <low|medium|high>
- architect_approve: <required|not_required>
- additional_notes: <any constraints>

Behavioral rules for prompts
----------------------------
- Be explicit about which files the agent may edit. If a field is missing or UNKNOWN, the agent must ask one clarifying question and stop.
- Include `run_commands` so the agent knows how to verify changes locally.
- For firmware or binary-affecting changes, set `architect_approve: required`.

Examples
--------
See docs/EXAMPLES.md for full PR and prompt examples.

