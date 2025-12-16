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

