---
status: canonical
last_reviewed: 2026-06-02
scope: ecosystem
---

# Woosoo software development documentation

This package is the canonical software development documentation set for Woosoo. It is organized by reader need and by documentation type, so a reader can move from general context to specific procedures without mixing process, product, and user material.

## Documentation set

| Document | Audience | Use it for |
|---|---|---|
| [Process documentation](PROCESS_DOCUMENTATION.md) | Developers, reviewers, release managers, DevOps maintainers | How Woosoo work is planned, implemented, validated, released, maintained, and documented. |
| [Product documentation](PRODUCT_DOCUMENTATION.md) | Developers, architects, stakeholders, DevOps maintainers | What Woosoo is, what it does, how it is architected, and which contracts define its behavior. |
| [User documentation](USER_DOCUMENTATION.md) | Restaurant operators, staff, admins, print relay operators, support staff | How to use, operate, troubleshoot, and recover the running system. |

Generated DOCX versions live beside the Markdown source:

- `woosoo-process-documentation.docx`
- `woosoo-product-documentation.docx`
- `woosoo-user-documentation.docx`

The Markdown files are the maintainable source. Regenerate DOCX files after changing the Markdown.

## Format model

This package follows three documentation principles:

- **Software documentation split:** process/development documentation explains how the system is built and controlled; product documentation explains the delivered system; user documentation teaches operation and reference use.
- **Diataxis-style information design:** tutorials and procedures are separate from reference and explanation.
- **Developer-documentation style:** headings are direct, commands use code formatting, procedures use numbered steps, and tables are used only for comparable data.

Reference sources:

- FIPS software documentation guidance: `https://www.govinfo.gov/content/pkg/GOVPUB-C13-bd0fd9e78f9c77ec6fcfe6f0d7e6b2b5/pdf/GOVPUB-C13-bd0fd9e78f9c77ec6fcfe6f0d7e6b2b5.pdf`
- Diataxis documentation framework: `https://nix.dev/contributing/documentation/diataxis`
- Google developer documentation style guide: `https://developers.google.com/style/highlights`

## Current truth sources

The documentation package must be checked against live source before publication. The current truth sources are:

- Platform context: `docs/AI_CONTEXT.md`
- Contracts: `contracts/*.md`
- Nexus routes and events: `woosoo-nexus/routes/*.php`, `woosoo-nexus/app/Events/**`
- Tablet Reverb client: `tablet-ordering-pwa/composables/useBroadcasts.ts`, `tablet-ordering-pwa/plugins/echo.client.ts`
- Print Bridge relay behavior: `woosoo-print-bridge/lib/services/reverb_service.dart`, `woosoo-print-bridge/lib/state/app_controller.dart`
- Platform runtime: root `compose.yaml` and `docs/deployment/*`
- Per-repo change history: local `git log` output from each app repository

## Regenerate DOCX

From `woosoo-nexus/`:

```powershell
C:\Users\Pc1\.cache\codex-runtimes\codex-primary-runtime\dependencies\python\python.exe docs\software-development\build_docx.py
```

If the absolute bundled Python path differs on another workstation, use the Codex workspace dependency loader or the local Python environment that has `python-docx` installed.

## Revision policy

- Update Markdown first.
- Regenerate DOCX after Markdown changes.
- Keep changelog entries tied to verified commit subjects or mark them as pending verification.
- Archive stale documents instead of leaving conflicting canonical guidance.
- Do not include secrets, real tokens, passwords, or private credentials.
