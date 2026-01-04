**Summary**
- **Context:** Windows dev on woosoo-nexus monorepo (Laravel + Vue + Nuxt PWA + Flutter relay app).
- **Goal:** Capture current setup, key findings, and next actions to resume quickly.

**Changes**
- **Main instructions:** Enhanced repository guide in [.github/copilot-instructions.md](.github/copilot-instructions.md) with quick start, architecture, workflows, troubleshooting, and Flutter notes.
- **PWA guide:** Verified tablet PWA instructions at [tablet-ordering-pwa/.github/copilot-instructions.md](tablet-ordering-pwa/.github/copilot-instructions.md).
- **Relay device scan:** Reviewed Flutter project structure ([relay-device/pubspec.yaml](relay-device/pubspec.yaml), [relay-device/lib/main.dart](relay-device/lib/main.dart)); confirmed no dedicated relay-device Copilot instructions file.

**Environment & Commands**
- **Setup:** `composer install; npm ci; cp .env.example .env; php artisan key:generate; php artisan migrate --seed; composer dev`.
- **PWA dev:** From [tablet-ordering-pwa/](tablet-ordering-pwa/): `npm install; npm run dev`.
- **Flutter dev:** From [relay-device/](relay-device/): `flutter pub get; flutter run -d <device>`.
- **Services:** HTTP 8000, Vite 5173, Reverb 6001, Print service 9100.

**Key Files**
- **Admin app init:** [resources/js/app.ts](resources/js/app.ts) (Inertia, Axios, Echo init).
- **Realtime channels:** [routes/channels.php](routes/channels.php) (admin.print, device.{id}, etc.).
- **Print service:** [print-service/index.js](print-service/index.js).
- **PWA Echo:** [tablet-ordering-pwa/plugins/echo.client.ts](tablet-ordering-pwa/plugins/echo.client.ts).

**Next Steps**
- **Relay instructions:** Add a concise relay-device Copilot guide in [relay-device/.github/](relay-device/.github/) for quick onboarding.
- **Verification:** Run `composer dev` and confirm Echo/Reverb connectivity (admin.print channel).
- **Testing:** Execute PHP tests (`composer test`) and Flutter tests (`flutter test`) to validate baseline.
- **Env review:** Align `.env` with recommended values (DB_*, VITE_REVERB_*, MAIN_API_URL).

**Notes**
- **Printing on Windows:** Use print stub or WSL+CUPS; see [docs/printer_manual.md](docs/printer_manual.md).
- **POS integration:** Read-only `pos` connection models under [app/Models/Krypton/](app/Models/Krypton/).