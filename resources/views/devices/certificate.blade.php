<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Woosoo Platform</title>
<link href="https://fonts.googleapis.com/css2?family=Raleway:ital,wght@0,100..900;1,100..900&family=Kanit:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Roboto+Flex:opsz,wght@8..144,100..1000&display=swap" rel="stylesheet">
<style>
  :root {
    --gold: #F6B56D;
    --gold-light: #F9D0A1;
    --gold-dim: #C78B45;
    --charcoal: #1A1A1A;
    --surface: #252525;
    --surface2: #1C1C1C;
    --surface3: #4A4A4A;
    --border: rgba(249,208,161,0.18);
    --text: #FFFFFF;
    --text-dim: #E5E7EB;
    --text-muted: #9CA3AF;
    --nexus: #60A5FA;
    --bridge: #10B981;
    --danger: #EF4444;
    --warning: #F59E0B;
    --radius: 6px;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  html { scroll-behavior: smooth; }
  body {
    background: var(--charcoal);
    color: var(--text);
    font-family: 'Raleway', sans-serif;
    font-size: 14px;
    line-height: 1.6;
  }

  /* NAV */
  nav {
    position: fixed; top: 0; left: 0; right: 0;
    background: rgba(26,26,26,0.95);
    backdrop-filter: blur(12px);
    border-bottom: 1px solid var(--border);
    z-index: 100;
    display: flex; align-items: center; gap: 0;
    padding: 0 24px;
    height: 52px;
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
    height: 52px;
    display: flex; align-items: center;
    font-size: 12px; font-weight: 500;
    letter-spacing: 0.04em;
    border-bottom: 2px solid transparent;
    transition: all 0.15s;
    white-space: nowrap;
  }
  .nav-links a:hover { color: var(--text); border-bottom-color: var(--gold); }
  .nav-links a.active { color: var(--gold); border-bottom-color: var(--gold); }
  .nav-login {
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
  .nav-login:hover { color: var(--gold); border-color: rgba(249,208,161,0.4); }

  /* LAYOUT */
  .main { padding-top: 52px; }
  section { padding: 64px 0; }
  .container { max-width: 960px; margin: 0 auto; padding: 0 32px; }

  /* HERO */
  .hero {
    background: linear-gradient(135deg, #1A1A1A 0%, #252525 40%, #1C1C1C 100%);
    border-bottom: 1px solid var(--border);
    position: relative; overflow: hidden;
    padding: 72px 0 56px;
  }
  .hero::before {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse 50% 70% at 65% 50%, rgba(246,181,109,0.08) 0%, transparent 70%);
  }
  .hero-badge {
    display: inline-flex; align-items: center; gap: 6px;
    background: rgba(246,181,109,0.12);
    border: 1px solid rgba(249,208,161,0.28);
    padding: 4px 12px; border-radius: 20px;
    font-size: 11px; color: var(--gold);
    font-family: 'Roboto Flex', sans-serif;
    margin-bottom: 20px;
  }
  .hero h1 {
    font-family: 'Kanit', sans-serif;
    font-size: clamp(26px, 4vw, 48px);
    font-weight: 800; color: var(--text);
    line-height: 1.1;
    margin-bottom: 12px;
  }
  .hero h1 span { color: var(--gold); }
  .hero-sub {
    color: var(--text-dim); font-size: 15px;
    max-width: 560px; margin-bottom: 28px; font-weight: 300;
  }
  .host-pill {
    display: inline-flex; align-items: center; gap: 8px;
    background: var(--surface2);
    border: 1px solid var(--border);
    padding: 6px 14px; border-radius: 4px;
  }
  .host-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--bridge); }
  .host-label {
    font-family: 'Roboto Flex', sans-serif;
    font-size: 12px; color: var(--text-dim);
  }
  .host-val {
    font-family: 'Roboto Flex', sans-serif;
    font-size: 12px; color: var(--gold);
  }

  /* SECTION HEADERS */
  .section-tag {
    font-family: 'Roboto Flex', sans-serif;
    font-size: 10px; letter-spacing: 0.12em;
    color: var(--gold-dim); text-transform: uppercase;
    margin-bottom: 8px;
  }
  h2 {
    font-family: 'Kanit', sans-serif;
    font-size: 24px; font-weight: 700;
    color: var(--text); margin-bottom: 6px;
  }
  .section-desc { color: var(--text-dim); font-size: 14px; margin-bottom: 36px; max-width: 600px; }

  /* DIVIDER */
  .divider { height: 1px; background: var(--border); margin: 0; }

  /* DOWNLOAD BUTTON */
  .download-wrap { margin-bottom: 20px; }
  .btn-download {
    display: inline-flex; align-items: center; gap: 10px;
    background: rgba(246,181,109,0.12);
    border: 1px solid rgba(249,208,161,0.4);
    color: var(--gold);
    text-decoration: none;
    padding: 14px 28px; border-radius: var(--radius);
    font-family: 'Kanit', sans-serif;
    font-size: 15px; font-weight: 700;
    letter-spacing: 0.04em;
    transition: all 0.15s;
  }
  .btn-download:hover {
    background: rgba(246,181,109,0.2);
    border-color: rgba(249,208,161,0.65);
  }
  .btn-download svg { flex-shrink: 0; }
  .download-hint {
    font-size: 11px; color: var(--text-muted);
    font-family: 'Roboto Flex', sans-serif;
    margin-top: 8px;
  }

  /* UNAVAILABLE CARD */
  .unavailable-card {
    background: rgba(239,68,68,0.08);
    border: 1px solid rgba(239,68,68,0.28);
    border-radius: var(--radius);
    padding: 20px 24px;
    display: flex; gap: 14px; align-items: flex-start;
  }
  .unavail-icon { font-size: 20px; flex-shrink: 0; }
  .unavail-title { font-family: 'Kanit', sans-serif; font-weight: 700; font-size: 14px; color: var(--danger); margin-bottom: 4px; }
  .unavail-desc { font-size: 12px; color: var(--text-dim); }

  /* JOURNEY STEPS */
  .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
  .col-heading {
    font-family: 'Roboto Flex', sans-serif;
    font-size: 10px; font-weight: 700; letter-spacing: 0.08em;
    text-transform: uppercase; color: var(--gold-dim);
    margin-bottom: 12px;
  }
  .journey-steps { display: flex; flex-direction: column; gap: 2px; }
  .journey-step {
    display: flex; gap: 14px;
    padding: 12px 14px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 4px;
    align-items: flex-start;
    transition: background 0.15s;
  }
  .journey-step:hover { background: var(--surface2); }
  .step-num {
    font-family: 'Roboto Flex', sans-serif;
    font-size: 11px; color: var(--gold);
    background: rgba(246,181,109,0.12);
    border: 1px solid rgba(249,208,161,0.24);
    width: 26px; height: 26px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 4px; flex-shrink: 0; font-weight: 700;
  }
  .step-content { flex: 1; }
  .step-title { font-weight: 600; font-size: 13px; color: var(--text); margin-bottom: 2px; }
  .step-desc { font-size: 12px; color: var(--text-dim); }
  code {
    font-family: 'Roboto Flex', sans-serif;
    font-size: 11px; color: var(--gold);
    background: rgba(246,181,109,0.1);
    padding: 1px 5px; border-radius: 3px;
  }

  /* NOTE CARD */
  .note-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 16px 20px;
    margin-top: 24px;
    display: flex; gap: 12px; align-items: flex-start;
  }
  .note-icon { font-size: 16px; flex-shrink: 0; margin-top: 1px; }
  .note-text { font-size: 12px; color: var(--text-dim); line-height: 1.6; }
  .note-text strong { color: var(--text); }

  /* FOOTER */
  footer {
    border-top: 1px solid var(--border);
    padding: 20px 32px;
    display: flex; align-items: center; justify-content: space-between;
    background: var(--surface);
  }
  footer .brand { font-family: 'Kanit', sans-serif; font-size: 13px; color: var(--gold); font-weight: 700; }
  footer .meta { font-size: 11px; color: var(--text-muted); font-family: 'Roboto Flex', sans-serif; }

  @media (max-width: 680px) {
    .two-col { grid-template-columns: 1fr; }
  }
</style>
</head>
<body>

<nav>
  <a href="/" class="nav-brand">WOOSOO</a>
  <div class="nav-links">
    <a href="#download">Download</a>
    <a href="#android">Android</a>
    <a href="#ios">iOS</a>
  </div>
  @auth
  <a href="/dashboard" class="nav-login">Dashboard →</a>
  @else
  <a href="/login" class="nav-login">Login to Dashboard →</a>
  @endauth
</nav>

<main class="main">

<!-- HERO -->
<div class="hero">
  <div class="container">
    <div class="hero-badge">🔐 Device Trust Setup · No Login Required</div>
    <h1>Install <span>CA Certificate</span></h1>
    <p class="hero-sub">
      Your device must trust the local server certificate before connecting to the tablet ordering system.
      Download and install the certificate file below.
    </p>
    <div class="host-pill">
      <div class="host-dot"></div>
      <span class="host-label">Server —</span>
      <span class="host-val">{{ $serverHost }}</span>
    </div>
  </div>
</div>

<!-- DOWNLOAD -->
<section id="download">
  <div class="container">
    <div class="section-tag">§01 — Certificate File</div>
    <h2>Download <span style="color:var(--gold)">woosoo-ca.crt</span></h2>
    <p class="section-desc">
      This is the server's self-signed CA certificate. Installing it tells your device to trust
      HTTPS connections to <code>{{ $serverHost }}</code>.
    </p>

    @if($available)
    <div class="download-wrap">
      <a href="{{ $downloadUrl }}" class="btn-download">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
          <path d="M8 1v9M4 7l4 4 4-4M2 13h12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        Download woosoo-ca.crt
      </a>
      <div class="download-hint">application/x-x509-ca-cert · Tap to install on Android or iOS</div>
    </div>
    @else
    <div class="unavailable-card">
      <div class="unavail-icon">⚠️</div>
      <div>
        <div class="unavail-title">Certificate Not Found</div>
        <div class="unavail-desc">
          The CA certificate file is not present on this server.
          Contact your system administrator to place the certificate at
          <code>docker/certs/fullchain.pem</code> or <code>storage/app/public/certificates/woosoo-ca.der</code>.
        </div>
      </div>
    </div>
    @endif
  </div>
</section>

<div class="divider"></div>

<!-- ANDROID STEPS -->
<section id="android">
  <div class="container">
    <div class="section-tag">§02 — Install Guide</div>
    <h2>Install on Your Device</h2>
    <p class="section-desc">
      Follow the steps for your device type. After installation, open the tablet ordering app
      and it will connect without certificate warnings.
    </p>

    <div class="two-col">
      <div>
        <div class="col-heading">Android</div>
        <div class="journey-steps">
          <div class="journey-step">
            <div class="step-num">1</div>
            <div class="step-content">
              <div class="step-title">Download the certificate</div>
              <div class="step-desc">Tap the download button above. The file <code>woosoo-ca.crt</code> will save to your Downloads folder.</div>
            </div>
          </div>
          <div class="journey-step">
            <div class="step-num">2</div>
            <div class="step-content">
              <div class="step-title">Open device Settings</div>
              <div class="step-desc">Go to <code>Settings → Security</code> (or <code>Biometrics and Security</code> on Samsung).</div>
            </div>
          </div>
          <div class="journey-step">
            <div class="step-num">3</div>
            <div class="step-content">
              <div class="step-title">Install from storage</div>
              <div class="step-desc">Tap <code>Install from device storage</code> → <code>CA Certificate</code>, then select <code>woosoo-ca.crt</code> from Downloads.</div>
            </div>
          </div>
          <div class="journey-step">
            <div class="step-num">4</div>
            <div class="step-content">
              <div class="step-title">Confirm installation</div>
              <div class="step-desc">Accept the security warning. The certificate will appear under <code>Trusted Credentials → User</code>.</div>
            </div>
          </div>
        </div>
      </div>

      <div id="ios">
        <div class="col-heading">iOS / iPadOS</div>
        <div class="journey-steps">
          <div class="journey-step">
            <div class="step-num">1</div>
            <div class="step-content">
              <div class="step-title">Download the certificate</div>
              <div class="step-desc">Tap the download button above in Safari. iOS will prompt to review the profile.</div>
            </div>
          </div>
          <div class="journey-step">
            <div class="step-num">2</div>
            <div class="step-content">
              <div class="step-title">Open Settings → General</div>
              <div class="step-desc">Navigate to <code>Settings → General → VPN &amp; Device Management</code>.</div>
            </div>
          </div>
          <div class="journey-step">
            <div class="step-num">3</div>
            <div class="step-content">
              <div class="step-title">Install the profile</div>
              <div class="step-desc">Tap the <code>woosoo-ca.crt</code> profile listed under Downloaded Profile, then tap <code>Install</code>.</div>
            </div>
          </div>
          <div class="journey-step">
            <div class="step-num">4</div>
            <div class="step-content">
              <div class="step-title">Enable full trust</div>
              <div class="step-desc">Go to <code>Settings → General → About → Certificate Trust Settings</code> and toggle on trust for the Woosoo CA.</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="note-card">
      <div class="note-icon">💡</div>
      <div class="note-text">
        <strong>Already installed?</strong> If the tablet app still shows a certificate warning,
        try clearing the browser cache or restarting the app after installation.
        The certificate is tied to <code>{{ $serverHost }}</code> — make sure the device is on the same local network.
      </div>
    </div>
  </div>
</section>

</main>

<footer>
  <div class="brand">WOOSOO PLATFORM</div>
  <div class="meta">Certificate Distribution · No authentication required · <a href="/login" style="color:var(--text-muted);text-decoration:none">Admin Login →</a></div>
</footer>

</body>
</html>
