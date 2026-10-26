# Web Stress Test

A browser-based hardware stress test and health check for laptops, desktops and Chromebooks. It's a single HTML file with no install, no database and no external libraries. Everything except the network test runs without a server. Open it in a browser, run the tests, and get a health report you can save as PDF, PNG or text.

It was built for checking school Chromebooks, but it works on any computer with a modern browser.

*Coded by Claude Opus 5.5 (Medium). See [Credits](#credits).*

## Features

- **Stress tests** for CPU, GPU, RAM and storage, plus an optional network test against your own server (a Raspberry Pi works well; see [RPI-SETUP.md](RPI-SETUP.md)), with live readings and graphs on a single screen
- **Run until stopped or timed**: run until you press Stop, or pick 1–60 minutes
- **Health report** at the end: an overall score, plus PASS / WARN / FAIL for each component with plain-language findings
- **Peripherals check**: webcam, microphone, speakers, keyboard, and trackpad/touch
- **Screen test**: fullscreen patterns for dead/stuck pixels, backlight bleed, impact damage and banding, plus a touchscreen dead-zone test
- **Power & thermal monitor** (always on): battery drain and charger check, thermal pressure, and UI freezes, tracked during every run
- **Report export**: one-page PDF, PNG image, plain text, or copy to clipboard
- **Private**: everything runs in the browser, results are never uploaded, and no third-party services are contacted

## Quick start

1. Open the hosted copy at **https://myoung8223.github.io/wbcst/**, or download `index.html` and open it in Chrome or Edge, either by double-clicking or from the Chromebook's Files app.
2. For the network test, point it at your own test server with `?nts=` (see [URL parameters](#url-parameters)), for example `https://myoung8223.github.io/wbcst/?nts=wbcst`. A Raspberry Pi set up with **[RPI-SETUP.md](RPI-SETUP.md)** works well as the server.
3. Close other tabs, choose a duration, and press **Start test**.
4. Press **Stop** (or let the timer finish) to see the health report.

For meaningful results, **run for at least 10 minutes** so the device heats up and any thermal throttling shows. To test the battery, unplug the device, don't start at 100%, and run for at least 10 minutes.

## URL parameters

Settings can be passed in the page address, which is handy for bookmarks, or a link you hand to a technician:

| Parameter | Meaning | Examples |
|---|---|---|
| `nts` | **Network test server**, the server running `down.php` and `up.php`. A bare name gets `.local` added. Plain `http://` is assumed unless you write `https://`. A port and folder may be included. | `nts=wbcst` → `http://wbcst.local/`<br>`nts=192.168.1.50`<br>`nts=wbcst:8080/tester/` |
| `t` | **Tester**: the technician's name or initials. Shown in the header, on every report, and in report file names. | `t=Mike`, `t=MGY` |

Combined: `https://myoung8223.github.io/wbcst/?nts=wbcst&t=MGY`

Both can also be set in **⚙ Settings**. Values from the address take priority for that visit.

### Using the GitHub Pages copy with a LAN server (recommended)

Opening the tester from GitHub Pages with `?nts=` gives you the best of both:

- the page is served over `https://`, so wake lock, webcam, microphone, battery and thermal readings all work, with no certificates or warnings
- the network test runs against your LAN server, so the traffic stays on your network

**How it works:** browsers normally block an `https://` page from calling a plain `http://` server ("mixed content"). Chrome makes an exception for addresses on the **local network** (`.local` names and private IPs such as `192.168.x.x` and `10.x.x.x`) through its **Local Network Access** permission.

- **First use:** Chrome asks whether the site may *"look for and connect to any device on your local network"*. Choose **Allow**.
- **Managed Chromebooks:** an admin can pre-approve it with the Chrome policy **LocalNetworkAccessAllowedForUrls**, set to `https://myoung8223.github.io`. It may need to be added as a custom policy in the Admin console. With it set, no prompt appears.
- **Browser support:** this needs Chrome or Edge 142 or newer. Firefox and Safari block it, so use the LAN server's own address there.
- **Server names:** use a `.local` name or a private IP for `nts`. A regular domain name that happens to point at a LAN address is treated the same way, as long as it really resolves to a private address.

If the permission was denied, re-enable it from the lock icon next to the address → **Site settings** → *Local network access*.

## What each test does

| Component | How it's tested | What it can catch |
|---|---|---|
| **CPU** | One worker thread per CPU thread runs integer and floating-point math. Every result is checked against a known answer. | Calculation errors (instability), thermal throttling, UI freezes. In Chrome it also reports thermal pressure. |
| **GPU** | A heavy WebGL shader renders continuously. Every 10 s a fixed frame is rendered and its pixels are compared to the first one. | Frame-rate drop-off (throttling), GPU/driver resets, inconsistent output |
| **RAM** | Allocates a share of memory (25% by default) and repeatedly writes and verifies patterns, like a mini memtest. | Memory errors, allocation failures, bandwidth drop-off |
| **Storage** | Writes, flushes, reads back and verifies a test file (256 MB by default) using the browser's private file system (OPFS), falling back to IndexedDB. The file is deleted afterward. | Data corruption, slow or stalling writes, slowdown over time |
| **Network** | Times latency, download and upload against your own server's `down.php` and `up.php`, and verifies every transfer arrived complete. Only available when a test server is found (see below). | Failed requests, high latency/jitter, connection drops, inconsistent speeds |

Throttling is judged against the device's own first minute. Fanless devices throttle more by design, so the best comparison is a known-good unit of the same model.

### Power & thermal monitor

The **Power & thermal** tile isn't a test that can be switched off. It's a passive monitor that adds no load, and it runs during every test, even a network-only run, to give context:

- **Battery**: level, charging state, drain rate under load, estimated runtime, and a charger check. The report gets a **Battery** card only when there's something to show: a measured drain or charge rate, or a warning. Otherwise it's a one-line note.
- **Thermal pressure** (Chromium only) and **UI freezes**: added to the **CPU** card, since both reflect how hard the device is working. A long freeze during a light test points to the device itself struggling.
- **Background time**: if the tab was hidden for part of the run, the report notes that the results are less reliable.

### Scoring

Each component starts at 100. A warning costs 15 points and a failure costs 45. The overall score is the average of the components that were tested.

- **NOT TESTED**: components switched off, and peripherals never checked. Not counted against the score.
- **UNDETERMINED**: peripherals that were exercised but not marked Pass or Fail. Not counted against the score.

## Peripherals and screen

**Peripherals** opens an overlay. Each item is marked Pass or Fail by you, and the results are added to the report.

- **Webcam**: live preview with resolution and frame rate. It warns if the image is nearly black, which usually means a privacy shutter.
- **Microphone**: level meter, plus a 3-second record and playback.
- **Speakers**: left / both / right tones and a 100 Hz–10 kHz sweep.
- **Keyboard**: an on-screen layout that lights up as keys are pressed.
- **Trackpad / touch**: a coverage grid, plus click, scroll and multi-touch detection.

**Screen** runs fullscreen patterns: white, black, red, green, blue, 1-pixel vertical and horizontal lines, 50% gray, and gradients. On touchscreens it ends with a touch grid.

- Click or **→** for the next pattern; **←** or right-click goes back.
- **F** flags a defect on the current pattern.
- **Esc** exits.

## Browser support

The stress tests work in any current browser. **Chrome, Edge or ChromeOS give the most complete results**, because some information is only exposed by Chromium-based browsers:

| Feature | Chromium (Chrome, Edge, ChromeOS) | Firefox / Safari |
|---|---|---|
| CPU, GPU, RAM, storage, network | ✅ | ✅ |
| Battery level / charging | ✅ | ❌ |
| Thermal pressure | ✅ | ❌ |
| Installed RAM size | ✅ (reported as up to "8+ GB") | ❌ (assumes 4 GB) |

### Secure page address required for some features

Browsers only allow the following on **secure** pages: `https://`, a local file, or `localhost`.

- **Screen wake lock**, which keeps the device from dimming and locking during a test
- **Webcam and microphone**
- **Battery and thermal readings**

The GitHub Pages copy (`https`) and a downloaded local copy have everything. A server reached over **plain `http://`**, such as a Raspberry Pi on the LAN, runs every other test, but these features are unavailable, and the page shows a notice saying so.

The simplest way to get everything is the GitHub Pages copy with `?nts=` pointing at your LAN server (see [URL parameters](#url-parameters)). Other options, such as the **OverrideSecurityRestrictionsOnInsecureOrigin** policy or HTTPS on the Pi, are in [RPI-SETUP.md](RPI-SETUP.md#plain-http-limitations-and-how-to-lift-them).

The browser asks permission before using the webcam or microphone the first time.

## Limitations

A web page is sandboxed, so some things are out of reach:

- **No direct sensor access**: no temperatures, fan speeds, voltages or SMART disk health. Throttling is inferred from performance falling off over time.
- **No battery wear or cycle count**: browsers only expose a rounded charge level. The report points to the right place for each OS:
  - ChromeOS: `chrome://diagnostics`
  - Windows: `powercfg /batteryreport`
  - macOS: System Settings › Battery
  - Linux: `/sys/class/power_supply/BAT0`
- **Storage reads may be cached** and look faster than the drive really is.
- **Network results include the Wi-Fi and the network path to the test server**, not just the device.
- **The network test needs a server you host** (see below). It's switched off when the page is opened as a local file or from a static host such as GitHub Pages.
- On high-end desktops a browser can't fully saturate a discrete GPU or NVMe drive. There it works best as a stability and throttling check.

## Network test server

The network test uses **only a server you host**. It contacts no public speed-test services, so it can't put load on anyone else's infrastructure.

### What you need

Any web server that runs PHP, with `index.html`, `down.php` and `up.php` in the same folder. A Raspberry Pi 4 or 5 (or similar board) on wired gigabit Ethernet with Apache and PHP is plenty. No database is needed.

| File | What it does |
|---|---|
| `down.php` | `GET down.php?bytes=N` returns N bytes of random data. `bytes=0` is used to measure latency. |
| `up.php` | Accepts a POST, discards it (nothing is written to disk) and replies with the number of bytes received. |

### Setup

**Raspberry Pi:** see **[RPI-SETUP.md](RPI-SETUP.md)** for a complete, security-hardened walkthrough. It starts from a fresh Raspberry Pi OS Lite install and ends with the tester at `http://wbcst.local/`.

**Any other server:** copy `index.html`, `down.php` and `up.php` into a folder served by PHP, then open that folder's address on the device being tested. The page finds `down.php` and `up.php` automatically. To confirm PHP is running, open `down.php?bytes=10` in that folder: it should download a 10-byte file, not show PHP source code. Make sure the server's PHP is up to date. In particular, it needs the October 2026 fixes for CVE-2026-109618 and CVE-2026-109619.

**Tips**

- Connect the server with **wired Ethernet**, and give it a fixed IP (static or DHCP reservation).
- The page sends at most 6 MB per upload, which fits PHP's default `post_max_size` of 8 MB.
- Transfer sizes adapt so each one takes about a second, so fast networks are measured accurately.
- Don't expose the server to the internet without access control: `down.php` will send anyone who can reach it up to 100 MB per request.

### Using a server from elsewhere

To use a test server from a page opened somewhere else, such as GitHub Pages or a local file, add `?nts=<server>` to the address (see [URL parameters](#url-parameters)), or set it in **⚙ Settings → Network server → Another server…**. The scripts send the CORS header needed for this.

From an `https://` page, a plain-`http://` server works only in Chrome or Edge, and only for local-network addresses, after the *Local network access* permission is allowed. Other browsers block it as mixed content.

### When no server is found

The Network tile shows why and switches itself off, and the report lists Network as *not tested (no test server)*. Everything else works normally. This is the expected behavior for the page on GitHub Pages: it can't run PHP, so it can't host the network test.

**Classrooms and labs:** on a local server, network testing never touches your internet connection. One gigabit server is shared by every device testing at the same time, though. For clean per-device speeds, run the network test on a few devices at a time.

## Tips and cautions

- Stress tests make devices **hot** and drain batteries fast. Keep vents clear and don't run them on soft surfaces.
- If the tab crashes during the RAM test, lower the RAM percentage in ⚙ Settings.
- Keep the test tab in front. Background tabs are throttled by the browser, and the report notes it if that happened.
- The page keeps the screen awake during a test where the browser allows it.

## License

[MIT](LICENSE)

## Credits

- **Code:** written by **Claude Opus 5.5 (Medium)**, Anthropic's AI model
- **Concept, direction and testing:** [Mike Young](https://github.com/myoung8223)
