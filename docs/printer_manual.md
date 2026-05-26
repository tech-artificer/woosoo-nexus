# Printer API — Deprecated Workaround Notice

> **ARCHIVED — DO NOT USE FOR NEW INTEGRATIONS**
>
> This document described a temporary workaround that removed authentication from printer API
> endpoints. That change has been superseded. All printer API calls must now use a device
> Bearer token obtained through device registration.
>
> **See the current integration guide:** [printer_readme.md](printer_readme.md)

---

## What this document was

This file documented a short-lived change that moved printer endpoints out of the
`auth:device` middleware group so the Flutter printer app could call them without tokens.
That approach was a temporary workaround with known security implications.

## Current approach

- Register the printer app as a device using `POST /api/devices/register`.
- Use the returned Bearer token on all printer API calls.
- See [printer_readme.md](printer_readme.md) for the full integration guide including endpoint
  details, payload examples, WebSocket integration, and operational recommendations.

## Archive note

This file is retained for historical context only. It does not reflect the current system
behaviour. If you are setting up a new print integration, follow `printer_readme.md`.
