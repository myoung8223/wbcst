# Web Stress Test

A browser-based hardware stress test and health check for laptops, desktops and Chromebooks. It's a single HTML file with no install, no server, no database and no external libraries. Open it in a browser, run the tests, and get a health report you can save as PDF, PNG or text.

It was built for checking school Chromebooks, but it works on any computer with a modern browser.

*Coded by Claude Opus 5.5 (Medium). See [Credits](#credits).*

## Features

- **Stress tests** for CPU, GPU, RAM, storage and network, with live readings and graphs on a single screen
- **Run until stopped or timed**: run until you press Stop, or pick 1–60 minutes
- **Health report** at the end: an overall score, plus PASS / WARN / FAIL for each component with plain-language findings
- **Peripherals check**: webcam, microphone, speakers, keyboard, and trackpad/touch
- **Screen test**: fullscreen patterns for dead/stuck pixels, backlight bleed, impact damage and banding, plus a touchscreen dead-zone test
- **Battery check**: drain rate under full load, estimated runtime, charger check
- **Report export**: one-page PDF, PNG image, plain text, or copy to clipboard
- **Private**: everything runs in the browser, and no results are uploaded anywhere

## Quick start

1. Download `index.html`.
2. Open it in Chrome or Edge, either by double-clicking or from the Chromebook's Files app. It can also be hosted on any static web host, such as GitHub Pages.
3. Close other tabs, choose a duration, and press **Start test**.
4. Press **Stop** (or let the timer finish) to see the health report.

For meaningful results, **run for at least 10 minutes** so the device heats up and any thermal throttling shows. To test the battery, unplug the device and don't start at 100%.

## What each test does

| Component | How it's tested | What it can catch |
|---|---|---|
| **CPU** | One worker thread per CPU thread runs integer and floating-point math. Every result is checked against a known answer. | Calculation errors (instability), thermal throttling, UI freezes. In Chrome it also reports thermal pressure. |
| **GPU** | A heavy WebGL shader renders continuously. Every 10 s a fixed frame is rendered and its pixels are compared to the first one. | Frame-rate drop-off (throttling), GPU/driver resets, inconsistent output |
| **RAM** | Allocates a share of memory (25% by default) and repeatedly writes and verifies patterns, like a mini memtest. | Memory errors, allocation failures, bandwidth drop-off |
| **Storage** | Writes, flushes, reads back and verifies a test file (256 MB by default) using the browser's private file system (OPFS), falling back to IndexedDB. The file is deleted afterward. | Data corruption, slow or stalling writes, slowdown over time |
| **Network** | Times latency, download and upload against a public test server (see below). | Failed requests, high latency/jitter, connection drops, inconsistent speeds |

Throttling is judged against the device's own first minute. Fanless devices throttle more by design, so the best comparison is a known-good unit of the same model.

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

Webcam and microphone access need the page to be opened from `https://` or as a local file. The browser asks for permission the first time.

## Limitations

A web page is sandboxed, so some things are out of reach:

- **No direct sensor access**: no temperatures, fan speeds, voltages or SMART disk health. Throttling is inferred from performance falling off over time.
- **No battery wear or cycle count**: browsers only expose a rounded charge level. The report points to the right place for each OS:
  - ChromeOS: `chrome://diagnostics`
  - Windows: `powercfg /batteryreport`
  - macOS: System Settings › Battery
  - Linux: `/sys/class/power_supply/BAT0`
- **Storage reads may be cached** and look faster than the drive really is.
- **Network results include the Wi-Fi and building network**, not just the device.
- On high-end desktops a browser can't fully saturate a discrete GPU or NVMe drive. There it works best as a stability and throttling check.

## Network test server

By default the network test uses Cloudflare's public speed-test endpoints (`speed.cloudflare.com/__down` and `/__up`). These are the same endpoints Cloudflare's open-source [`@cloudflare/speedtest`](https://github.com/cloudflare/speedtest) library uses. If Cloudflare can't be reached, the page falls back to timing downloads from the jsDelivr CDN, which has no upload test.

A **custom URL** can be set in ⚙ Settings, for latency and download testing against your own server. The file must be served with `Access-Control-Allow-Origin: *`, and preferably `Cache-Control: no-store`. If the page is opened over `https://`, the test server must use `https://` too.

**Classrooms and labs:** each device downloads about 5 MB every few seconds during the network test. For a full cart, consider turning the network test off, testing a few devices at a time, or pointing it at a server on your local network. That also keeps the traffic off your internet connection.

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
