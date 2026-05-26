<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Woosoo User Manual</title>
<link href="https://fonts.googleapis.com/css2?family=Raleway:wght@300;400;500;600;700;800&family=Kanit:wght@500;600;700;800&family=Roboto+Flex:opsz,wght@8..144,400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root {
    --gold: #F6B56D;
    --gold-light: #F9D0A1;
    --gold-dim: #C78B45;
    --charcoal: #1A1A1A;
    --surface: #252525;
    --surface2: #1C1C1C;
    --surface3: #333333;
    --border: rgba(249,208,161,0.18);
    --text: #FFFFFF;
    --text-dim: #E5E7EB;
    --text-muted: #9CA3AF;
    --blue: #60A5FA;
    --green: #10B981;
    --radius: 6px;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  html { scroll-behavior: smooth; }
  body {
    background: var(--charcoal);
    color: var(--text);
    font-family: 'Raleway', sans-serif;
    font-size: 14px;
    line-height: 1.65;
  }
  nav {
    position: fixed; top: 0; left: 0; right: 0;
    background: rgba(26,26,26,0.95);
    backdrop-filter: blur(12px);
    border-bottom: 1px solid var(--border);
    z-index: 100;
    display: flex; align-items: center; gap: 0;
    padding: 0 24px;
    min-height: 52px;
  }
  .nav-brand {
    font-family: 'Kanit', sans-serif;
    font-weight: 800; font-size: 15px;
    color: var(--gold);
    letter-spacing: 0.08em;
    margin-right: 32px;
    flex-shrink: 0;
    text-decoration: none;
  }
  .nav-links { display: flex; gap: 0; overflow-x: auto; flex: 1; }
  .nav-links a {
    color: var(--text-dim);
    text-decoration: none;
    padding: 0 14px;
    min-height: 52px;
    display: flex; align-items: center;
    font-size: 12px; font-weight: 500;
    letter-spacing: 0.04em;
    border-bottom: 2px solid transparent;
    transition: all 0.15s;
    white-space: nowrap;
  }
  .nav-links a:hover { color: var(--text); border-bottom-color: var(--gold); }
  .nav-action {
    margin-left: auto;
    font-family: 'Roboto Flex', sans-serif;
    font-size: 11px; color: var(--text-dim);
    background: var(--surface2);
    padding: 5px 12px; border-radius: 3px;
    border: 1px solid var(--border);
    flex-shrink: 0;
    text-decoration: none;
    transition: all 0.15s;
    letter-spacing: 0.03em;
  }
  .nav-action:hover { color: var(--gold); border-color: rgba(249,208,161,0.4); }
  .main { padding-top: 52px; }
  .container { max-width: 1040px; margin: 0 auto; padding: 0 32px; }
  .hero {
    background: linear-gradient(135deg, #1A1A1A 0%, #252525 45%, #1C1C1C 100%);
    border-bottom: 1px solid var(--border);
    padding: 72px 0 48px;
    position: relative;
    overflow: hidden;
  }
  .hero::before {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse 50% 70% at 75% 50%, rgba(246,181,109,0.08) 0%, transparent 70%);
  }
  .hero .container { position: relative; }
  .hero-badge {
    display: inline-flex; align-items: center; gap: 8px;
    background: rgba(96,165,250,0.12);
    border: 1px solid rgba(96,165,250,0.28);
    padding: 4px 12px; border-radius: 20px;
    font-size: 11px; color: #BFDBFE;
    font-family: 'Roboto Flex', sans-serif;
    margin-bottom: 20px;
  }
  h1 {
    font-family: 'Kanit', sans-serif;
    font-size: clamp(30px, 4.5vw, 52px);
    font-weight: 800;
    line-height: 1.05;
    margin-bottom: 14px;
  }
  h1 span, h2 span { color: var(--gold); }
  .hero-sub {
    color: var(--text-dim);
    font-size: 15px;
    max-width: 680px;
    font-weight: 300;
  }
  .quick-links {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 14px;
    margin-top: 28px;
  }
  .quick-links a {
    color: var(--text);
    text-decoration: none;
    background: rgba(255,255,255,0.04);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 14px;
    transition: all 0.15s;
  }
  .quick-links a:hover { border-color: rgba(249,208,161,0.46); background: rgba(246,181,109,0.09); }
  .quick-title { color: var(--gold); font-family: 'Kanit', sans-serif; font-weight: 700; margin-bottom: 4px; }
  .quick-desc { color: var(--text-muted); font-size: 12px; }
  section { padding: 56px 0; border-bottom: 1px solid var(--border); }
  .section-tag {
    font-family: 'Roboto Flex', sans-serif;
    font-size: 10px; letter-spacing: 0.12em;
    color: var(--gold-dim); text-transform: uppercase;
    margin-bottom: 8px;
  }
  h2 {
    font-family: 'Kanit', sans-serif;
    font-size: 26px;
    line-height: 1.2;
    margin-bottom: 8px;
  }
  .section-desc { color: var(--text-dim); max-width: 760px; margin-bottom: 28px; }
  .grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
  }
  .card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 20px;
  }
  .card h3 {
    font-family: 'Kanit', sans-serif;
    font-size: 18px;
    margin-bottom: 10px;
    color: var(--gold-light);
  }
  .card p { color: var(--text-dim); margin-bottom: 12px; }
  .steps { display: flex; flex-direction: column; gap: 8px; }
  .step {
    display: grid;
    grid-template-columns: 30px 1fr;
    gap: 12px;
    align-items: flex-start;
    padding: 12px;
    background: var(--surface2);
    border: 1px solid rgba(249,208,161,0.12);
    border-radius: 4px;
  }
  .num {
    width: 28px; height: 28px;
    border-radius: 4px;
    display: flex; align-items: center; justify-content: center;
    background: rgba(246,181,109,0.12);
    color: var(--gold);
    font-family: 'Roboto Flex', sans-serif;
    font-size: 12px;
    font-weight: 700;
  }
  .step-title { color: var(--text); font-weight: 700; margin-bottom: 2px; }
  .step-desc { color: var(--text-dim); font-size: 13px; }
  .pill-row { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
  .pill {
    font-family: 'Roboto Flex', sans-serif;
    font-size: 11px;
    color: #BFDBFE;
    background: rgba(96,165,250,0.12);
    border: 1px solid rgba(96,165,250,0.28);
    border-radius: 20px;
    padding: 4px 9px;
  }
  code {
    font-family: 'Roboto Flex', sans-serif;
    font-size: 12px;
    color: var(--gold);
    background: rgba(246,181,109,0.1);
    padding: 1px 5px;
    border-radius: 3px;
  }
  .note {
    background: rgba(16,185,129,0.08);
    border: 1px solid rgba(16,185,129,0.26);
    border-radius: var(--radius);
    padding: 16px 18px;
    color: var(--text-dim);
    margin-top: 18px;
  }
  .note strong { color: var(--text); }
  .screenshot-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
    margin-top: 18px;
  }
  .screenshot-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
  }
  .screenshot-card img {
    display: block;
    width: 100%;
    height: auto;
    background: var(--surface2);
  }
  .screenshot-caption {
    border-top: 1px solid rgba(249,208,161,0.12);
    padding: 14px 16px 16px;
  }
  .screenshot-caption strong {
    display: block;
    color: var(--gold-light);
    font-family: 'Kanit', sans-serif;
    font-size: 16px;
    margin-bottom: 4px;
  }
  .screenshot-caption span {
    color: var(--text-dim);
    font-size: 12px;
  }
  footer {
    padding: 20px 32px;
    display: flex;
    justify-content: space-between;
    gap: 16px;
    background: var(--surface);
    color: var(--text-muted);
    font-family: 'Roboto Flex', sans-serif;
    font-size: 11px;
  }
  footer a { color: var(--text-muted); text-decoration: none; }
  footer a:hover { color: var(--gold); }
  @media (max-width: 780px) {
    nav { align-items: stretch; padding: 0 14px; }
    .nav-brand { margin-right: 14px; }
    .container { padding: 0 20px; }
    .quick-links, .grid, .screenshot-grid { grid-template-columns: 1fr; }
    footer { flex-direction: column; }
  }
</style>
</head>
<body>

<nav>
  <a href="/" class="nav-brand">WOOSOO</a>
  <div class="nav-links">
    <a href="#getting-started">Getting Started</a>
    <a href="#nexus">Nexus Admin</a>
    <a href="#tablet">Tablet App</a>
  </div>
  @auth
  <a href="/dashboard" class="nav-action">Open Dashboard</a>
  @else
  <a href="/login" class="nav-action">Login to Dashboard</a>
  @endauth
</nav>

<main class="main">
  <div class="hero">
    <div class="container">
      <div class="hero-badge">Public user guide</div>
      <h1>How to Use <span>Woosoo</span></h1>
      <p class="hero-sub">
        A staff-friendly guide for opening the platform, navigating the admin dashboard,
        registering devices, and guiding guests through the tablet ordering screens.
      </p>
      <div class="quick-links">
        <a href="#getting-started">
          <div class="quick-title">Start Here</div>
          <div class="quick-desc">Open the platform, install the certificate, and choose the right app.</div>
        </a>
        <a href="#nexus">
          <div class="quick-title">Admin Dashboard</div>
          <div class="quick-desc">Manage devices, orders, menus, packages, users, and reports.</div>
        </a>
        <a href="#tablet">
          <div class="quick-title">Tablet Ordering</div>
          <div class="quick-desc">Walk through the guest ordering flow from welcome to session end.</div>
        </a>
      </div>
    </div>
  </div>

  <section id="getting-started">
    <div class="container">
      <div class="section-tag">01 - Getting Started</div>
      <h2>Open the Platform</h2>
      <p class="section-desc">
        Use the welcome page as the starting point for setup and documentation. Staff who need
        to manage the restaurant system should log in to the dashboard. Tablets used by guests
        should open the tablet ordering app after the device is trusted and registered.
      </p>
      <div class="grid">
        <div class="card">
          <h3>First visit on a device</h3>
          <div class="steps">
            <div class="step">
              <div class="num">1</div>
              <div>
                <div class="step-title">Open the welcome page</div>
                <div class="step-desc">Open the host URL on the device browser.</div>
              </div>
            </div>
            <div class="step">
              <div class="num">2</div>
              <div>
                <div class="step-title">Install the certificate if prompted</div>
                <div class="step-desc">Use the certificate download and Android/iOS guide on the welcome page.</div>
              </div>
            </div>
            <div class="step">
              <div class="num">3</div>
              <div>
                <div class="step-title">Choose the correct destination</div>
                <div class="step-desc">Use the dashboard for staff/admin work. Use the tablet ordering app for guest ordering.</div>
              </div>
            </div>
          </div>
        </div>
        <div class="card">
          <h3>Where to go</h3>
          <p><strong>Dashboard:</strong> Staff and admins use this for devices, orders, menus, packages, service requests, reports, and configuration.</p>
          <p><strong>Tablet app:</strong> Guests use this for starting a dining session, choosing a package, ordering items, and requesting staff help.</p>
          <p><strong>User manual:</strong> This page explains navigation only. Restricted server operations are intentionally not published here.</p>
        </div>
      </div>
    </div>
  </section>

  <section id="nexus">
    <div class="container">
      <div class="section-tag">02 - Woosoo Nexus Admin</div>
      <h2>Navigate the <span>Dashboard</span></h2>
      <p class="section-desc">
        After login, use the left sidebar to move between dashboard areas. The sidebar groups
        daily restaurant work, analytics, and configuration so staff can find the right page quickly.
      </p>
      <div class="grid">
        <div class="card">
          <h3>Main navigation</h3>
          <div class="pill-row">
            <span class="pill">Dashboard</span>
            <span class="pill">Orders</span>
            <span class="pill">POS</span>
            <span class="pill">Menus</span>
            <span class="pill">Packages</span>
            <span class="pill">User Management</span>
            <span class="pill">Devices</span>
            <span class="pill">Service Requests</span>
          </div>
          <p style="margin-top: 14px;">Use these pages for the daily operating workflow: reviewing activity, handling orders, keeping menu availability current, and managing registered devices.</p>
        </div>
        <div class="card">
          <h3>Analytics and configuration</h3>
          <div class="pill-row">
            <span class="pill">Reports</span>
            <span class="pill">Branches</span>
            <span class="pill">Access Control</span>
            <span class="pill">Accessibility</span>
            <span class="pill">Event Logs</span>
            <span class="pill">Reverb Service</span>
            <span class="pill">Monitoring</span>
          </div>
          <p style="margin-top: 14px;">Use these pages for reporting, permissions, operational visibility, and system configuration that is only available to authorized staff.</p>
        </div>
      </div>

      <div class="screenshot-grid">
        <figure class="screenshot-card">
          <img src="/docs/user-manual/screenshots/nexus-dashboard-redacted.png" alt="Woosoo Nexus dashboard overview showing sales, order, guest, and session summary cards with private metrics redacted">
          <figcaption class="screenshot-caption">
            <strong>Dashboard overview</strong>
            <span>Start here to check today&apos;s sales, order count, guest count, current session, open tables, and high-level activity. Private metrics are hidden in this guide image.</span>
          </figcaption>
        </figure>
        <figure class="screenshot-card">
          <img src="/docs/user-manual/screenshots/nexus-orders-live.png" alt="Woosoo Nexus orders page with live orders, order history, filters, export action, and table controls">
          <figcaption class="screenshot-caption">
            <strong>Orders</strong>
            <span>Use Live Orders for pending or in-progress work. Use Order History, filters, search, and export when reviewing completed or voided orders.</span>
          </figcaption>
        </figure>
      </div>

      <div class="grid" style="margin-top: 18px;">
        <div class="card">
          <h3>Create or add a device</h3>
          <div class="steps">
            <div class="step">
              <div class="num">1</div>
              <div>
                <div class="step-title">Open Devices</div>
                <div class="step-desc">Log in, then select <code>Devices</code> from the left sidebar.</div>
              </div>
            </div>
            <div class="step">
              <div class="num">2</div>
              <div>
                <div class="step-title">Start a new record</div>
                <div class="step-desc">Click the new-device action shown on the Devices page.</div>
              </div>
            </div>
            <div class="step">
              <div class="num">3</div>
              <div>
                <div class="step-title">Fill the form</div>
                <div class="step-desc">Enter the device name, IP address if known, optional port, device type, and table assignment.</div>
              </div>
            </div>
            <div class="step">
              <div class="num">4</div>
              <div>
                <div class="step-title">Save the device</div>
                <div class="step-desc">Save changes. For a new device, the system prepares the security setup used by the physical tablet or print bridge.</div>
              </div>
            </div>
          </div>
        </div>
        <div class="card">
          <h3>View and update devices</h3>
          <div class="steps">
            <div class="step">
              <div class="num">1</div>
              <div>
                <div class="step-title">Open the device list</div>
                <div class="step-desc">Use the Devices table to search by device name or IP address.</div>
              </div>
            </div>
            <div class="step">
              <div class="num">2</div>
              <div>
                <div class="step-title">Open details</div>
                <div class="step-desc">Click a device row to view details such as table, IP, last seen time, and security status.</div>
              </div>
            </div>
            <div class="step">
              <div class="num">3</div>
              <div>
                <div class="step-title">Edit the record</div>
                <div class="step-desc">Update name, IP address, optional port, device type, or table assignment, then save changes.</div>
              </div>
            </div>
            <div class="step">
              <div class="num">4</div>
              <div>
                <div class="step-title">Generate access when needed</div>
                <div class="step-desc">On an existing device, use the generate-token or security-code action when staff need to reconnect the physical device.</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="screenshot-grid">
        <figure class="screenshot-card">
          <img src="/docs/user-manual/screenshots/nexus-devices-redacted.png" alt="Woosoo Nexus devices page with create device action, totals, device table, and redacted private connection values">
          <figcaption class="screenshot-caption">
            <strong>Devices</strong>
            <span>Click Create Device to register a tablet or bridge. Use the list to review table assignment, last seen status, and security readiness. Private connection values are hidden in this guide image.</span>
          </figcaption>
        </figure>
        <figure class="screenshot-card">
          <img src="/docs/user-manual/screenshots/nexus-menus-live.png" alt="Woosoo Nexus menu management page with menu counts, filters, item list, prices, image status, and availability status">
          <figcaption class="screenshot-caption">
            <strong>Menus</strong>
            <span>Use filters and search to find menu items. Review availability, category, group, image status, and pricing before service.</span>
          </figcaption>
        </figure>
        <figure class="screenshot-card">
          <img src="/docs/user-manual/screenshots/nexus-packages-live.png" alt="Woosoo Nexus packages page with new package form and configured packages table">
          <figcaption class="screenshot-caption">
            <strong>Packages</strong>
            <span>Create packages by naming the offer, choosing the package menu, selecting modifier menus, setting display order, and saving it as active when ready for tablets.</span>
          </figcaption>
        </figure>
      </div>

      <div class="note">
        <strong>Safe public guide:</strong> This page explains what to click and what each screen is for.
        It does not include private network values, server internals, or restricted operational procedures.
      </div>
    </div>
  </section>

  <section id="tablet">
    <div class="container">
      <div class="section-tag">03 - Tablet Ordering PWA</div>
      <h2>Use the <span>Tablet App</span></h2>
      <p class="section-desc">
        The tablet app is the guest-facing ordering flow. Staff can use this guide to explain each
        screen and to reset expectations when a guest is unsure what to tap next.
      </p>
      <div class="grid">
        <div class="card">
          <h3>Start an order</h3>
          <div class="steps">
            <div class="step">
              <div class="num">1</div>
              <div>
                <div class="step-title">Welcome screen</div>
                <div class="step-desc">Tap <code>Begin the Feast</code>. This starts the table session and loads the ordering flow.</div>
              </div>
            </div>
            <div class="step">
              <div class="num">2</div>
              <div>
                <div class="step-title">Guest count screen</div>
                <div class="step-desc">Enter or select how many guests are dining, then continue.</div>
              </div>
            </div>
            <div class="step">
              <div class="num">3</div>
              <div>
                <div class="step-title">Package screen</div>
                <div class="step-desc">Tap a package to preview the meats and included choices, then choose the package for the table.</div>
              </div>
            </div>
            <div class="step">
              <div class="num">4</div>
              <div>
                <div class="step-title">Menu screen</div>
                <div class="step-desc">Browse categories, tap items, adjust selections, and add items to the order.</div>
              </div>
            </div>
          </div>
        </div>
        <div class="card">
          <h3>Review and continue dining</h3>
          <div class="steps">
            <div class="step">
              <div class="num">5</div>
              <div>
                <div class="step-title">Review screen</div>
                <div class="step-desc">Check the order summary. Go back to adjust items or confirm when ready.</div>
              </div>
            </div>
            <div class="step">
              <div class="num">6</div>
              <div>
                <div class="step-title">In-session screen</div>
                <div class="step-desc">View the active dining session, order refills or add-ons, call staff, and watch the remaining session time.</div>
              </div>
            </div>
            <div class="step">
              <div class="num">7</div>
              <div>
                <div class="step-title">Session ended screen</div>
                <div class="step-desc">When the session ends, the tablet shows the end state and becomes ready for the next guests.</div>
              </div>
            </div>
            <div class="step">
              <div class="num">8</div>
              <div>
                <div class="step-title">Settings screen</div>
                <div class="step-desc">Staff can open settings with the protected staff flow to review device and connection setup.</div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="screenshot-grid">
        <figure class="screenshot-card">
          <img src="/docs/user-manual/screenshots/tablet-welcome.png" alt="Tablet ordering welcome screen with Begin the Feast button">
          <figcaption class="screenshot-caption">
            <strong>Welcome screen</strong>
            <span>Tap Begin the Feast when the table is ready to start ordering.</span>
          </figcaption>
        </figure>
        <figure class="screenshot-card">
          <img src="/docs/user-manual/screenshots/tablet-guest-count.png" alt="Tablet guest count screen with plus, minus, and ready to feast controls">
          <figcaption class="screenshot-caption">
            <strong>Guest count</strong>
            <span>Set the number of guests, then continue to package selection.</span>
          </figcaption>
        </figure>
        <figure class="screenshot-card">
          <img src="/docs/user-manual/screenshots/tablet-package-selection.png" alt="Tablet package selection screen with Classic Feast, Noble Selection, and Royal Banquet choices">
          <figcaption class="screenshot-caption">
            <strong>Package selection</strong>
            <span>Choose a package and preview what meats or inclusions belong to that package.</span>
          </figcaption>
        </figure>
        <figure class="screenshot-card">
          <img src="/docs/user-manual/screenshots/tablet-menu-browse.png" alt="Tablet menu screen with category tabs and menu item cards">
          <figcaption class="screenshot-caption">
            <strong>Menu browsing</strong>
            <span>Use category tabs, item cards, and add controls to build the table order.</span>
          </figcaption>
        </figure>
        <figure class="screenshot-card">
          <img src="/docs/user-manual/screenshots/tablet-menu-search.png" alt="Tablet refill menu screen with filters, search, and send to kitchen action">
          <figcaption class="screenshot-caption">
            <strong>Refills and add-ons</strong>
            <span>During service, browse available refill items, adjust quantities, and send new requests to the kitchen.</span>
          </figcaption>
        </figure>
        <figure class="screenshot-card">
          <img src="/docs/user-manual/screenshots/tablet-review-order.png" alt="Tablet review and send to kitchen screen with order items and package summary">
          <figcaption class="screenshot-caption">
            <strong>Review order</strong>
            <span>Confirm the selected package and items before sending the order.</span>
          </figcaption>
        </figure>
        <figure class="screenshot-card">
          <img src="/docs/user-manual/screenshots/tablet-order-submitted.png" alt="Tablet placing order screen shown after confirmation">
          <figcaption class="screenshot-caption">
            <strong>Submitting order</strong>
            <span>The tablet shows progress while the order is being sent.</span>
          </figcaption>
        </figure>
        <figure class="screenshot-card">
          <img src="/docs/user-manual/screenshots/tablet-in-session.png" alt="Tablet in-session order screen with current package, items, order summary, and staff actions">
          <figcaption class="screenshot-caption">
            <strong>In session</strong>
            <span>Guests can view the active order, request refills or add-ons, and call staff when needed.</span>
          </figcaption>
        </figure>
        <figure class="screenshot-card">
          <img src="/docs/user-manual/screenshots/tablet-session-ended.png" alt="Tablet session ended thank you screen">
          <figcaption class="screenshot-caption">
            <strong>Session ended</strong>
            <span>The tablet thanks the guests and prepares to return to the welcome screen.</span>
          </figcaption>
        </figure>
        <figure class="screenshot-card">
          <img src="/docs/user-manual/screenshots/tablet-settings-create-pin.png" alt="Tablet staff settings screen for creating a new PIN">
          <figcaption class="screenshot-caption">
            <strong>Create staff PIN</strong>
            <span>Staff create a PIN before opening protected tablet settings.</span>
          </figcaption>
        </figure>
        <figure class="screenshot-card">
          <img src="/docs/user-manual/screenshots/tablet-settings-enter-pin.png" alt="Tablet staff settings screen for entering a PIN">
          <figcaption class="screenshot-caption">
            <strong>Enter staff PIN</strong>
            <span>Use the protected PIN flow to open settings without exposing setup controls to guests.</span>
          </figcaption>
        </figure>
        <figure class="screenshot-card">
          <img src="/docs/user-manual/screenshots/tablet-settings-device-setup.png" alt="Tablet staff settings setup screen with private connection values redacted">
          <figcaption class="screenshot-caption">
            <strong>Device setup</strong>
            <span>Register the tablet with the security code from Nexus and verify connection status. Private connection values are hidden in this guide image.</span>
          </figcaption>
        </figure>
      </div>
      <div class="note">
        <strong>Guest-facing rule:</strong> Guests should use the tablet for ordering and service requests only.
        Admin changes, device records, and menu availability are handled in the Nexus dashboard.
      </div>
    </div>
  </section>
</main>

<footer>
  <div>WOOSOO PLATFORM USER MANUAL</div>
  <div>
    <a href="/">Back to welcome page</a> |
    @auth
    <a href="/dashboard">Open dashboard</a>
    @else
    <a href="/login">Login to dashboard</a>
    @endauth
  </div>
</footer>

</body>
</html>
