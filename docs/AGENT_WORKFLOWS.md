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
   # Agent Workflows (woosoo-nexus)



Relay device - relay-device
```powershell
if (Test-Path ./build.sh) { ./build.sh --dry-run || true }
```

Notes

