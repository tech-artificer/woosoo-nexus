<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Woosoo User Manual</title>
<link href="https://fonts.googleapis.com/css2?family=Kanit:wght@500;600;700;800&family=Raleway:wght@400;500;600;700;800&family=Roboto+Flex:opsz,wght@8..144,400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root {
    color-scheme: dark;
    --page: #0d0b09;
    --rail: #191511;
    --panel: #14110e;
    --panel-soft: #1e1914;
    --line: rgba(255, 185, 105, 0.16);
    --line-strong: rgba(255, 185, 105, 0.28);
    --text: #fff8ec;
    --text-soft: #e8d9c6;
    --text-muted: #887b6d;
    --accent: #ffb869;
    --accent-soft: #ffd29c;
    --accent-deep: #7a4c1f;
    --success: #20d291;
    --danger: #ff6961;
    --rail-width: 270px;
    --content-width: 920px;
  }

  * {
    box-sizing: border-box;
  }

  html {
    scroll-behavior: smooth;
  }

  body {
    margin: 0;
    background: var(--page);
    color: var(--text);
    font-family: 'Raleway', sans-serif;
    font-size: 15px;
    line-height: 1.72;
  }

  a {
    color: inherit;
  }

  .document-shell {
    min-height: 100vh;
    background:
      linear-gradient(90deg, rgba(255, 184, 105, 0.035) 0 1px, transparent 1px 100%),
      radial-gradient(circle at 72% 0%, rgba(255, 184, 105, 0.08), transparent 36%),
      var(--page);
    background-size: 72px 72px, auto, auto;
  }

  .document-rail {
    position: fixed;
    inset: 0 auto 0 0;
    width: var(--rail-width);
    padding: 32px 30px;
    background: linear-gradient(180deg, #1a1612 0%, #100e0c 100%);
    border-right: 6px solid rgba(255, 184, 105, 0.18);
    overflow-y: auto;
    z-index: 10;
  }

  .brand-lockup {
    display: grid;
    grid-template-columns: 38px 1fr;
    gap: 12px;
    align-items: center;
    color: var(--text);
    text-decoration: none;
  }

  .brand-mark {
    display: grid;
    place-items: center;
    width: 38px;
    height: 38px;
    border-radius: 9px;
    background: var(--accent);
    color: #160f08;
    font-family: 'Kanit', sans-serif;
    font-size: 18px;
    font-weight: 800;
  }

  .brand-name {
    display: block;
    font-family: 'Kanit', sans-serif;
    font-size: 15px;
    font-weight: 800;
    letter-spacing: 0.06em;
    line-height: 1;
    text-transform: uppercase;
  }

  .brand-sub {
    display: block;
    margin-top: 4px;
    color: var(--accent);
    font-family: 'Roboto Flex', sans-serif;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.16em;
    text-transform: uppercase;
  }

  .rail-rule {
    height: 1px;
    margin: 26px 0;
    background: var(--line);
  }

  .rail-meta {
    display: grid;
    gap: 7px;
    color: var(--text-muted);
    font-family: 'Roboto Flex', sans-serif;
    font-size: 11px;
  }

  .rail-meta strong {
    color: var(--text-soft);
    font-weight: 700;
  }

  .rail-group {
    margin-top: 28px;
  }

  .rail-heading {
    margin-bottom: 10px;
    color: var(--accent-deep);
    font-family: 'Roboto Flex', sans-serif;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.14em;
    text-transform: uppercase;
  }

  .rail-link {
    display: block;
    padding: 5px 0 5px 13px;
    color: var(--text-muted);
    border-left: 1px solid transparent;
    font-family: 'Raleway', sans-serif;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
  }

  .rail-link:hover,
  .rail-link:focus-visible {
    color: var(--text);
    border-left-color: var(--accent);
    outline: none;
  }

  .rail-action {
    display: inline-flex;
    margin-top: 24px;
    padding: 8px 12px;
    color: var(--accent-soft);
    border: 1px solid var(--line-strong);
    border-radius: 999px;
    font-family: 'Roboto Flex', sans-serif;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-decoration: none;
  }

  .mobile-nav {
    display: none;
  }

  .manual-main {
    margin-left: var(--rail-width);
    padding: 58px 44px 72px 64px;
  }

  .manual-content {
    max-width: var(--content-width);
  }

  .eyebrow {
    color: var(--accent);
    font-family: 'Roboto Flex', sans-serif;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 0.18em;
    text-transform: uppercase;
  }

  h1,
  h2,
  h3 {
    margin: 0;
    color: var(--text);
    font-family: 'Kanit', sans-serif;
    letter-spacing: 0;
  }

  h1 {
    max-width: 800px;
    margin-top: 10px;
    font-size: clamp(46px, 7vw, 76px);
    font-weight: 800;
    line-height: 0.95;
  }

  .lede {
    max-width: 800px;
    margin: 22px 0 0;
    color: var(--text-soft);
    font-size: 18px;
    font-weight: 700;
    line-height: 1.55;
  }

  .spec-strip {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0;
    margin-top: 42px;
    border: 1px solid var(--line);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.025);
    overflow: hidden;
  }

  .spec-cell {
    min-height: 70px;
    padding: 18px 22px;
    border-right: 1px solid var(--line);
  }

  .spec-cell:last-child {
    border-right: 0;
  }

  .spec-label {
    display: block;
    color: var(--text-muted);
    font-family: 'Roboto Flex', sans-serif;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.15em;
    text-transform: uppercase;
  }

  .spec-value {
    display: block;
    margin-top: 6px;
    color: var(--text);
    font-family: 'Kanit', sans-serif;
    font-size: 14px;
    font-weight: 700;
    line-height: 1.25;
  }

  .quick-index {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
    margin-top: 28px;
  }

  .quick-index a {
    min-height: 98px;
    padding: 16px;
    color: var(--text-soft);
    border: 1px solid var(--line);
    border-radius: 7px;
    background: rgba(255, 184, 105, 0.035);
    text-decoration: none;
  }

  .quick-index a:hover,
  .quick-index a:focus-visible {
    border-color: var(--accent);
    outline: none;
  }

  .quick-index strong {
    display: block;
    color: var(--accent-soft);
    font-family: 'Kanit', sans-serif;
    font-size: 17px;
    line-height: 1.1;
  }

  .quick-index span {
    display: block;
    margin-top: 7px;
    color: var(--text-muted);
    font-size: 12px;
    line-height: 1.45;
  }

  .manual-section {
    padding-top: 68px;
  }

  .section-kicker {
    display: flex;
    align-items: center;
    gap: 12px;
    color: var(--accent);
    font-family: 'Roboto Flex', sans-serif;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 0.18em;
    text-transform: uppercase;
  }

  .section-kicker::before {
    content: '';
    width: 5px;
    height: 5px;
    background: var(--accent);
  }

  h2 {
    margin-top: 8px;
    padding-bottom: 14px;
    border-bottom: 1px solid var(--line);
    font-size: clamp(30px, 4vw, 40px);
    font-weight: 800;
    line-height: 1.05;
  }

  .section-copy {
    max-width: 800px;
    margin: 24px 0 0;
    color: var(--text-soft);
    font-size: 16px;
    font-weight: 600;
  }

  .prose-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
    margin-top: 24px;
  }

  .brief-panel {
    padding: 22px;
    border: 1px solid var(--line);
    border-radius: 7px;
    background: rgba(255, 255, 255, 0.025);
  }

  .brief-panel h3 {
    color: var(--accent-soft);
    font-size: 20px;
    font-weight: 700;
    line-height: 1.15;
  }

  .brief-panel p {
    margin: 12px 0 0;
    color: var(--text-soft);
  }

  .step-list {
    display: grid;
    gap: 9px;
    margin-top: 16px;
    counter-reset: manual-step;
  }

  .step-item {
    position: relative;
    padding: 14px 14px 14px 48px;
    border: 1px solid rgba(255, 184, 105, 0.12);
    background: rgba(255, 184, 105, 0.035);
  }

  .step-item::before {
    counter-increment: manual-step;
    content: counter(manual-step, decimal-leading-zero);
    position: absolute;
    left: 14px;
    top: 15px;
    color: var(--accent);
    font-family: 'Roboto Flex', sans-serif;
    font-size: 11px;
    font-weight: 800;
  }

  .step-title {
    display: block;
    color: var(--text);
    font-weight: 800;
  }

  .step-desc {
    display: block;
    margin-top: 2px;
    color: var(--text-muted);
    font-size: 13px;
    line-height: 1.55;
  }

  .tag-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 16px;
  }

  .tag {
    padding: 5px 9px;
    color: var(--text-soft);
    border: 1px solid rgba(255, 184, 105, 0.2);
    border-radius: 999px;
    background: rgba(255, 184, 105, 0.04);
    font-family: 'Roboto Flex', sans-serif;
    font-size: 11px;
    font-weight: 700;
  }

  code {
    padding: 1px 5px;
    color: var(--accent-soft);
    border: 1px solid rgba(255, 184, 105, 0.18);
    border-radius: 3px;
    background: rgba(255, 184, 105, 0.07);
    font-family: 'Roboto Flex', sans-serif;
    font-size: 12px;
  }

  .callout {
    margin-top: 24px;
    padding: 17px 19px;
    color: var(--text-soft);
    border-left: 3px solid var(--accent);
    background: linear-gradient(90deg, rgba(255, 184, 105, 0.12), rgba(255, 184, 105, 0.025));
  }

  .callout strong {
    color: var(--accent-soft);
  }

  .figure-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
    margin-top: 26px;
  }

  .manual-figure {
    margin: 0;
    border: 1px solid var(--line);
    border-radius: 7px;
    background: rgba(255, 255, 255, 0.018);
    overflow: hidden;
  }

  .manual-figure img {
    display: block;
    width: 100%;
    height: auto;
    background: #110e0c;
  }

  .manual-figure figcaption {
    padding: 14px 16px 17px;
    border-top: 1px solid rgba(255, 184, 105, 0.12);
  }

  .manual-figure figcaption strong {
    display: block;
    color: var(--accent-soft);
    font-family: 'Kanit', sans-serif;
    font-size: 16px;
    line-height: 1.15;
  }

  .manual-figure figcaption span {
    display: block;
    margin-top: 5px;
    color: var(--text-muted);
    font-size: 12px;
    line-height: 1.5;
  }

  .manual-footer {
    margin-top: 72px;
    padding-top: 18px;
    border-top: 1px solid var(--line);
    color: var(--text-muted);
    font-family: 'Roboto Flex', sans-serif;
    font-size: 11px;
    letter-spacing: 0.08em;
    text-transform: uppercase;
  }

  .manual-footer a {
    color: var(--accent-soft);
    text-decoration: none;
  }

  @media (max-width: 1020px) {
    .document-rail {
      display: none;
    }

    .mobile-nav {
      position: sticky;
      top: 0;
      z-index: 20;
      display: block;
      padding: 14px 18px;
      background: rgba(13, 11, 9, 0.96);
      border-bottom: 1px solid var(--line);
      backdrop-filter: blur(14px);
    }

    .mobile-nav-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
    }

    .mobile-links {
      display: flex;
      gap: 12px;
      margin-top: 10px;
      overflow-x: auto;
      padding-bottom: 4px;
    }

    .mobile-links a {
      flex: 0 0 auto;
      color: var(--text-soft);
      font-family: 'Roboto Flex', sans-serif;
      font-size: 11px;
      font-weight: 800;
      letter-spacing: 0.08em;
      text-decoration: none;
      text-transform: uppercase;
    }

    .manual-main {
      margin-left: 0;
      padding: 38px 22px 56px;
    }
  }

  @media (max-width: 760px) {
    body {
      font-size: 14px;
    }

    h1 {
      font-size: 44px;
    }

    .lede {
      font-size: 16px;
    }

    .spec-strip,
    .quick-index,
    .prose-grid,
    .figure-grid {
      grid-template-columns: 1fr;
    }

    .spec-cell {
      border-right: 0;
      border-bottom: 1px solid var(--line);
    }

    .spec-cell:last-child {
      border-bottom: 0;
    }

    .manual-section {
      padding-top: 52px;
    }
  }
</style>
</head>
<body>
<div class="document-shell">
  <aside class="document-rail" aria-label="User manual navigation">
    <a href="/" class="brand-lockup">
      <span class="brand-mark">W</span>
      <span>
        <span class="brand-name">Woosoo</span>
        <span class="brand-sub">User Manual</span>
      </span>
    </a>

    <div class="rail-rule"></div>

    <div class="rail-meta">
      <span><strong>v1.1</strong> - Public Build</span>
      <span>27 May 2026</span>
      <span>Owner: Restaurant Ops</span>
    </div>

    <nav class="rail-group" aria-label="Manual sections">
      <div class="rail-heading">Document</div>
      <a href="#context" class="rail-link">1 - Context</a>
      <a href="#getting-started" class="rail-link">2 - Getting Started</a>
      <a href="#nexus" class="rail-link">3 - Nexus Admin</a>
      <a href="#devices" class="rail-link">4 - Device Workflows</a>
      <a href="#tablet" class="rail-link">5 - Tablet Ordering</a>
    </nav>

    <nav class="rail-group" aria-label="Support links">
      <div class="rail-heading">Actions</div>
      <a href="/" class="rail-link">Welcome Page</a>
      @auth
      <a href="/dashboard" class="rail-link">Open Dashboard</a>
      @else
      <a href="/login" class="rail-link">Login</a>
      @endauth
    </nav>

    <a href="#context" class="rail-action">Back to top</a>
  </aside>

  <header class="mobile-nav">
    <div class="mobile-nav-row">
      <a href="/" class="brand-lockup">
        <span class="brand-mark">W</span>
        <span>
          <span class="brand-name">Woosoo</span>
          <span class="brand-sub">User Manual</span>
        </span>
      </a>
      @auth
      <a href="/dashboard" class="rail-action">Dashboard</a>
      @else
      <a href="/login" class="rail-action">Login</a>
      @endauth
    </div>
    <nav class="mobile-links" aria-label="Mobile manual sections">
      <a href="#getting-started">Start</a>
      <a href="#nexus">Nexus</a>
      <a href="#devices">Devices</a>
      <a href="#tablet">Tablet</a>
    </nav>
  </header>

  <main class="manual-main">
    <div class="manual-content">
      <section id="context" aria-labelledby="manual-title">
        <div class="eyebrow">Woosoo Platform - Public User Guide</div>
        <h1 id="manual-title">How to Use Woosoo</h1>
        <p class="lede">
          A staff-facing navigation manual for opening the platform, using Woosoo Nexus,
          registering table devices, and guiding guests through the Tablet Ordering PWA.
        </p>

        <div class="spec-strip" aria-label="Manual metadata">
          <div class="spec-cell">
            <span class="spec-label">Module</span>
            <span class="spec-value">Woosoo Nexus + Tablet</span>
          </div>
          <div class="spec-cell">
            <span class="spec-label">Surface</span>
            <span class="spec-value">Public user manual</span>
          </div>
          <div class="spec-cell">
            <span class="spec-label">Audience</span>
            <span class="spec-value">Restaurant staff</span>
          </div>
          <div class="spec-cell">
            <span class="spec-label">Version</span>
            <span class="spec-value">1.1 - 2026-05-27</span>
          </div>
        </div>

        <div class="quick-index">
          <a href="#getting-started">
            <strong>Start Here</strong>
            <span>Open the platform, trust the device, and choose the correct app surface.</span>
          </a>
          <a href="#nexus">
            <strong>Nexus Admin</strong>
            <span>Review orders, devices, menus, packages, and daily operating pages.</span>
          </a>
          <a href="#tablet">
            <strong>Tablet Ordering</strong>
            <span>Guide guests from welcome screen through active dining session.</span>
          </a>
        </div>
      </section>

      <section id="getting-started" class="manual-section" aria-labelledby="getting-started-title">
        <div class="section-kicker">Section 01</div>
        <h2 id="getting-started-title">Getting Started</h2>
        <p class="section-copy">
          Use the welcome page as the starting point. Staff use the dashboard for operating work.
          Table devices use the tablet ordering app after the device is trusted and registered.
        </p>

        <div class="prose-grid">
          <article class="brief-panel">
            <h3>First visit on a device</h3>
            <div class="step-list">
              <div class="step-item">
                <span class="step-title">Open the welcome page</span>
                <span class="step-desc">Open the host URL in the device browser.</span>
              </div>
              <div class="step-item">
                <span class="step-title">Install the certificate if prompted</span>
                <span class="step-desc">Use the certificate download and mobile setup guide on the welcome page.</span>
              </div>
              <div class="step-item">
                <span class="step-title">Choose the correct destination</span>
                <span class="step-desc">Use the dashboard for staff work. Use the tablet app for guest ordering.</span>
              </div>
            </div>
          </article>

          <article class="brief-panel">
            <h3>Where to go</h3>
            <p><strong>Dashboard:</strong> Staff and admins use this for devices, orders, menus, packages, service requests, reports, and configuration.</p>
            <p><strong>Tablet app:</strong> Guests use this for starting a dining session, choosing a package, ordering items, and requesting staff help.</p>
            <p><strong>User manual:</strong> This page explains navigation only. Restricted server operations are intentionally not published here.</p>
          </article>
        </div>
      </section>

      <section id="nexus" class="manual-section" aria-labelledby="nexus-title">
        <div class="section-kicker">Section 02</div>
        <h2 id="nexus-title">Woosoo Nexus Admin</h2>
        <p class="section-copy">
          After login, use the left sidebar to move between dashboard areas. The sidebar groups
          daily restaurant work, analytics, and configuration so staff can find the right page quickly.
        </p>

        <div class="prose-grid">
          <article class="brief-panel">
            <h3>Main navigation</h3>
            <div class="tag-list">
              <span class="tag">Dashboard</span>
              <span class="tag">Orders</span>
              <span class="tag">POS</span>
              <span class="tag">Menus</span>
              <span class="tag">Packages</span>
              <span class="tag">User Management</span>
              <span class="tag">Devices</span>
              <span class="tag">Service Requests</span>
            </div>
            <p>Use these pages for daily operation: reviewing activity, handling orders, keeping menu availability current, and managing devices.</p>
          </article>

          <article class="brief-panel">
            <h3>Analytics and configuration</h3>
            <div class="tag-list">
              <span class="tag">Reports</span>
              <span class="tag">Branches</span>
              <span class="tag">Access Control</span>
              <span class="tag">Accessibility</span>
              <span class="tag">Event Logs</span>
              <span class="tag">Reverb Service</span>
              <span class="tag">Monitoring</span>
            </div>
            <p>Use these pages for reporting, permissions, operational visibility, and system configuration available to authorized staff.</p>
          </article>
        </div>

        <div class="figure-grid">
          <figure class="manual-figure">
            <img src="/docs/user-manual/screenshots/nexus-dashboard-redacted.png" alt="Woosoo Nexus dashboard overview showing sales, order, guest, and session summary cards with private metrics redacted">
            <figcaption>
              <strong>Dashboard overview</strong>
              <span>Start here to check sales, order count, guest count, current session, open tables, and high-level activity. Private metrics are hidden in this guide image.</span>
            </figcaption>
          </figure>
          <figure class="manual-figure">
            <img src="/docs/user-manual/screenshots/nexus-orders-live.png" alt="Woosoo Nexus orders page with live orders, order history, filters, export action, and table controls">
            <figcaption>
              <strong>Orders</strong>
              <span>Use Live Orders for pending or in-progress work. Use Order History, filters, search, and export when reviewing completed or voided orders.</span>
            </figcaption>
          </figure>
        </div>
      </section>

      <section id="devices" class="manual-section" aria-labelledby="devices-title">
        <div class="section-kicker">Section 03</div>
        <h2 id="devices-title">Device Workflows</h2>
        <p class="section-copy">
          Devices connect physical table hardware to the restaurant flow. Create, inspect, and update
          device records from Nexus before handing a tablet to a table.
        </p>

        <div class="prose-grid">
          <article class="brief-panel">
            <h3>Create or add a device</h3>
            <div class="step-list">
              <div class="step-item">
                <span class="step-title">Open Devices</span>
                <span class="step-desc">Log in, then select <code>Devices</code> from the left sidebar.</span>
              </div>
              <div class="step-item">
                <span class="step-title">Start a new record</span>
                <span class="step-desc">Click the new-device action shown on the Devices page.</span>
              </div>
              <div class="step-item">
                <span class="step-title">Fill the form</span>
                <span class="step-desc">Enter the device name, connection details if known, device type, and table assignment.</span>
              </div>
              <div class="step-item">
                <span class="step-title">Save the device</span>
                <span class="step-desc">Save changes. For a new device, the system prepares the security setup used by the physical tablet or print bridge.</span>
              </div>
            </div>
          </article>

          <article class="brief-panel">
            <h3>View and update devices</h3>
            <div class="step-list">
              <div class="step-item">
                <span class="step-title">Open the device list</span>
                <span class="step-desc">Use the Devices table to search by device name or connection label.</span>
              </div>
              <div class="step-item">
                <span class="step-title">Open details</span>
                <span class="step-desc">Click a device row to view table, last seen time, and security status.</span>
              </div>
              <div class="step-item">
                <span class="step-title">Edit the record</span>
                <span class="step-desc">Update device fields or table assignment, then save changes.</span>
              </div>
              <div class="step-item">
                <span class="step-title">Generate access when needed</span>
                <span class="step-desc">Use the token or security-code action when staff need to reconnect the physical device.</span>
              </div>
            </div>
          </article>
        </div>

        <div class="figure-grid">
          <figure class="manual-figure">
            <img src="/docs/user-manual/screenshots/nexus-devices-redacted.png" alt="Woosoo Nexus devices page with create device action, totals, device table, and redacted private connection values">
            <figcaption>
              <strong>Devices</strong>
              <span>Click Create Device to register a tablet or bridge. Use the list to review table assignment, last seen status, and security readiness. Private connection values are hidden in this guide image.</span>
            </figcaption>
          </figure>
          <figure class="manual-figure">
            <img src="/docs/user-manual/screenshots/nexus-menus-live.png" alt="Woosoo Nexus menu management page with menu counts, filters, item list, prices, image status, and availability status">
            <figcaption>
              <strong>Menus</strong>
              <span>Use filters and search to find menu items. Review availability, category, group, image status, and pricing before service.</span>
            </figcaption>
          </figure>
          <figure class="manual-figure">
            <img src="/docs/user-manual/screenshots/nexus-packages-live.png" alt="Woosoo Nexus packages page with new package form and configured packages table">
            <figcaption>
              <strong>Packages</strong>
              <span>Create packages by naming the offer, choosing the package menu, selecting modifier menus, setting display order, and saving it as active when ready for tablets.</span>
            </figcaption>
          </figure>
        </div>

        <div class="callout">
          <strong>Safe public guide:</strong> This page explains what to click and what each screen is for.
          It does not include private network values, server internals, or restricted operational procedures.
        </div>
      </section>

      <section id="tablet" class="manual-section" aria-labelledby="tablet-title">
        <div class="section-kicker">Section 04</div>
        <h2 id="tablet-title">Tablet Ordering PWA</h2>
        <p class="section-copy">
          The tablet app is the guest-facing ordering flow. Staff can use this guide to explain each
          screen and reset expectations when a guest is unsure what to tap next.
        </p>

        <div class="prose-grid">
          <article class="brief-panel">
            <h3>Start an order</h3>
            <div class="step-list">
              <div class="step-item">
                <span class="step-title">Welcome screen</span>
                <span class="step-desc">Tap <code>Begin the Feast</code>. This starts the table session and loads ordering.</span>
              </div>
              <div class="step-item">
                <span class="step-title">Guest count screen</span>
                <span class="step-desc">Enter or select how many guests are dining, then continue.</span>
              </div>
              <div class="step-item">
                <span class="step-title">Package screen</span>
                <span class="step-desc">Tap a package to preview the meats and included choices, then choose the package for the table.</span>
              </div>
              <div class="step-item">
                <span class="step-title">Menu screen</span>
                <span class="step-desc">Browse categories, tap items, adjust selections, and add items to the order.</span>
              </div>
            </div>
          </article>

          <article class="brief-panel">
            <h3>Review and continue dining</h3>
            <div class="step-list">
              <div class="step-item">
                <span class="step-title">Review screen</span>
                <span class="step-desc">Check the order summary. Go back to adjust items or confirm when ready.</span>
              </div>
              <div class="step-item">
                <span class="step-title">In-session screen</span>
                <span class="step-desc">View the active dining session, order refills or add-ons, call staff, and watch remaining session time.</span>
              </div>
              <div class="step-item">
                <span class="step-title">Session ended screen</span>
                <span class="step-desc">When the session ends, the tablet shows the end state and prepares for the next guests.</span>
              </div>
              <div class="step-item">
                <span class="step-title">Settings screen</span>
                <span class="step-desc">Staff can open settings with the protected staff flow to review device and connection setup.</span>
              </div>
            </div>
          </article>
        </div>

        <div class="figure-grid">
          <figure class="manual-figure">
            <img src="/docs/user-manual/screenshots/tablet-welcome.png" alt="Tablet ordering welcome screen with Begin the Feast button">
            <figcaption>
              <strong>Welcome screen</strong>
              <span>Tap Begin the Feast when the table is ready to start ordering.</span>
            </figcaption>
          </figure>
          <figure class="manual-figure">
            <img src="/docs/user-manual/screenshots/tablet-guest-count.png" alt="Tablet guest count screen with plus, minus, and ready to feast controls">
            <figcaption>
              <strong>Guest count</strong>
              <span>Set the number of guests, then continue to package selection.</span>
            </figcaption>
          </figure>
          <figure class="manual-figure">
            <img src="/docs/user-manual/screenshots/tablet-package-selection.png" alt="Tablet package selection screen with Classic Feast, Noble Selection, and Royal Banquet choices">
            <figcaption>
              <strong>Package selection</strong>
              <span>Choose a package and preview what meats or inclusions belong to that package.</span>
            </figcaption>
          </figure>
          <figure class="manual-figure">
            <img src="/docs/user-manual/screenshots/tablet-menu-browse.png" alt="Tablet menu screen with category tabs and menu item cards">
            <figcaption>
              <strong>Menu browsing</strong>
              <span>Use category tabs, item cards, and add controls to build the table order.</span>
            </figcaption>
          </figure>
          <figure class="manual-figure">
            <img src="/docs/user-manual/screenshots/tablet-menu-search.png" alt="Tablet refill menu screen with filters, search, and send to kitchen action">
            <figcaption>
              <strong>Refills and add-ons</strong>
              <span>During service, browse available refill items, adjust quantities, and send new requests to the kitchen.</span>
            </figcaption>
          </figure>
          <figure class="manual-figure">
            <img src="/docs/user-manual/screenshots/tablet-review-order.png" alt="Tablet review and send to kitchen screen with order items and package summary">
            <figcaption>
              <strong>Review order</strong>
              <span>Confirm the selected package and items before sending the order.</span>
            </figcaption>
          </figure>
          <figure class="manual-figure">
            <img src="/docs/user-manual/screenshots/tablet-order-submitted.png" alt="Tablet placing order screen shown after confirmation">
            <figcaption>
              <strong>Submitting order</strong>
              <span>The tablet shows progress while the order is being sent.</span>
            </figcaption>
          </figure>
          <figure class="manual-figure">
            <img src="/docs/user-manual/screenshots/tablet-in-session.png" alt="Tablet in-session order screen with current package, items, order summary, and staff actions">
            <figcaption>
              <strong>In session</strong>
              <span>Guests can view the active order, request refills or add-ons, and call staff when needed.</span>
            </figcaption>
          </figure>
          <figure class="manual-figure">
            <img src="/docs/user-manual/screenshots/tablet-session-ended.png" alt="Tablet session ended thank you screen">
            <figcaption>
              <strong>Session ended</strong>
              <span>The tablet thanks the guests and prepares to return to the welcome screen.</span>
            </figcaption>
          </figure>
          <figure class="manual-figure">
            <img src="/docs/user-manual/screenshots/tablet-settings-create-pin.png" alt="Tablet staff settings screen for creating a new PIN">
            <figcaption>
              <strong>Create staff PIN</strong>
              <span>Staff create a PIN before opening protected tablet settings.</span>
            </figcaption>
          </figure>
          <figure class="manual-figure">
            <img src="/docs/user-manual/screenshots/tablet-settings-enter-pin.png" alt="Tablet staff settings screen for entering a PIN">
            <figcaption>
              <strong>Enter staff PIN</strong>
              <span>Use the protected PIN flow to open settings without exposing setup controls to guests.</span>
            </figcaption>
          </figure>
          <figure class="manual-figure">
            <img src="/docs/user-manual/screenshots/tablet-settings-device-setup.png" alt="Tablet staff settings setup screen with private connection values redacted">
            <figcaption>
              <strong>Device setup</strong>
              <span>Register the tablet with the security code from Nexus and verify connection status. Private connection values are hidden in this guide image.</span>
            </figcaption>
          </figure>
        </div>

        <div class="callout">
          <strong>Guest-facing rule:</strong> Guests should use the tablet for ordering and service requests only.
          Admin changes, device records, and menu availability are handled in the Nexus dashboard.
        </div>
      </section>

      <footer class="manual-footer">
        <span>Woosoo Platform User Manual</span>
        <span> - </span>
        <a href="/">Back to welcome page</a>
        <span> - </span>
        @auth
        <a href="/dashboard">Open dashboard</a>
        @else
        <a href="/login">Login to dashboard</a>
        @endauth
      </footer>
    </div>
  </main>
</div>
</body>
</html>
