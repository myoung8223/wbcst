# Raspberry Pi test server setup

This guide turns a Raspberry Pi into a dedicated LAN server for Web-Based Computer Stress Test. The Pi serves the test page itself and runs the network test, using `down.php` and `up.php`. Devices on your network then open `http://wbcst.local/`, or the Pi's IP address, and get the full tester, including the network test. A few browser features need extra setup over plain `http://`; see [Plain `http://` limitations](#plain-http-limitations-and-how-to-lift-them).

The setup uses Apache with PHP-FPM, without a database, and is hardened for this single job.

## Before you start

You need:

- A Raspberry Pi with a **fresh install of Raspberry Pi OS Lite**. **64-bit** is recommended; see step 1.
- **SSH enabled** and an SSH session open to the Pi.
- The Pi connected by **wired Ethernet**. Its Wi-Fi is much slower, and would become part of what you're measuring.

Every command below is run in that SSH session.

### What speed to expect

The Pi's Ethernet port sets the ceiling for network test results:

| Model | Approximate max |
|---|---|
| Pi 3 B | ~95 Mbps (100 Mbps Ethernet) |
| Pi 3 B+ | ~300 Mbps (gigabit port, limited by the USB 2 bus) |
| Pi 4 / Pi 5 | ~940 Mbps (full gigabit) |

Several devices testing at once share this bandwidth.

---

## 1. Check the OS version

```sh
grep VERSION_CODENAME /etc/os-release
dpkg --print-architecture
```

| Codename | Architecture | What to do |
|---|---|---|
| `trixie` (Debian 13) | `arm64` | ✅ Recommended. Continue. |
| `trixie` | `armhf` (32-bit) | ✅ Works, but packages come from Raspbian's rebuild, which can lag behind Debian on security fixes. Step 3 checks this. |
| `bookworm` (Debian 12) or older | either | ⚠️ Re-image with the current **Raspberry Pi OS Lite (64-bit)** before continuing. |

## 2. Update everything

```sh
sudo apt update && sudo apt full-upgrade -y
sudo reboot
```

Reconnect over SSH after the reboot.

## 3. Install Apache and PHP-FPM

PHP runs through **PHP-FPM**, not Apache's built-in PHP module (`mod_php`). PHP's security advisories have rated `mod_php` the highest-risk way to run PHP.

```sh
sudo apt install -y apache2 php-fpm
PHPV=$(ls /etc/php | sort -V | tail -1); echo "PHP $PHPV"

sudo a2dismod php$PHPV 2>/dev/null              # make sure mod_php isn't loaded
sudo a2dismod mpm_prefork 2>/dev/null
sudo a2enmod mpm_event proxy_fcgi setenvif
sudo a2enconf php$PHPV-fpm
sudo systemctl restart php$PHPV-fpm apache2
```

### Confirm PHP is patched

```sh
dpkg -l "php$PHPV-fpm" | tail -1
```

In October 2026 PHP fixed two critical remote-code-execution flaws, known as "PHP2Shell" (CVE-2026-109618 and CVE-2026-109619). Make sure the installed version is **at least**:

| Release | Package | Minimum fixed version |
|---|---|---|
| trixie | `php8.4-fpm` | `8.4.27-1~deb13u1` |
| bookworm | `php8.2-fpm` | `8.2.35-1~deb12u1` |

If it's older even after `full-upgrade`, **don't put the server into use**. That usually means a 32-bit image whose repository hasn't caught up yet; re-image with the 64-bit OS.

> `PHPV` only lasts for the current SSH session. If you reconnect partway through, run the `PHPV=...` line again.

## 4. Harden PHP

The test scripts never use sessions, form parsing or file uploads, so turn them off. `up.php` reads the raw request body, which still works with form parsing disabled.

```sh
sudo tee /etc/php/$PHPV/fpm/conf.d/99-wbcst.ini >/dev/null <<'EOF'
; Hardening for a dedicated Web-Based Computer Stress Test server
expose_php = Off
display_errors = Off
allow_url_fopen = Off
enable_post_data_reading = Off   ; no form/multipart parsing; up.php reads php://input
file_uploads = Off
session.upload_progress.enabled = 0
post_max_size = 8M
max_execution_time = 60
EOF

# Recycle PHP worker processes regularly
sudo sed -i 's/^;\?pm.max_requests\s*=.*/pm.max_requests = 200/' /etc/php/$PHPV/fpm/pool.d/www.conf
sudo systemctl restart php$PHPV-fpm
```

> These settings apply to every PHP site on the Pi. That's fine for a dedicated test server, but remove `enable_post_data_reading = Off` if you later host a PHP app that uses forms.

## 5. Harden Apache

```sh
sudo tee /etc/apache2/conf-available/wbcst-security.conf >/dev/null <<'EOF'
ServerTokens Prod
ServerSignature Off
TraceEnable Off
LimitRequestBody 10485760
<Directory /var/www/html>
    Options -Indexes
    AllowOverride None
</Directory>
EOF
sudo a2enconf wbcst-security
sudo apache2ctl configtest && sudo systemctl reload apache2
```

`LimitRequestBody` caps requests at 10 MB. The test never sends more than 6 MB at a time.

## 6. Rename the Pi (optional)

This gives the server the friendly address `http://wbcst.local/`:

```sh
sudo raspi-config nonint do_hostname wbcst
```

The new name takes effect after the reboot in step 8.

## 7. Install the test files

This puts the tester at the root of the web server, replacing Apache's default page:

```sh
for f in index.html down.php up.php; do
  sudo curl -fsSL -o /var/www/html/$f "https://raw.githubusercontent.com/myoung8223/wbcst/main/$f"
done
ls -l /var/www/html
```

To update the tester later, run the same commands again.

## 8. Reboot and test

```sh
sudo reboot
```

Then check from the Pi itself, after reconnecting over SSH:

```sh
curl -s "http://localhost/down.php?bytes=10" | wc -c                                # prints 10
head -c 3000000 /dev/urandom | curl -s --data-binary @- http://localhost/up.php; echo   # prints 3000000
curl -sI http://localhost/down.php | grep -i x-powered                              # prints nothing
sudo apache2ctl -M | grep -E 'mpm|php|proxy_fcgi'                                  # mpm_event + proxy_fcgi, no php module
```

Then from another computer on the network, open **`http://wbcst.local/`**, or use the GitHub Pages copy pointed at the Pi: `https://myoung8223.github.io/wbcst/?nts=wbcst`. The Network tile should show `wbcst.local` as its server. If you skipped step 6, use `http://raspberrypi.local/` instead.

## 9. Keep it patched automatically

```sh
sudo apt install -y unattended-upgrades
sudo dpkg-reconfigure -plow unattended-upgrades       # answer "Yes"
```

On **64-bit** Raspberry Pi OS, Debian security updates are included by default.

On **32-bit** (`armhf`), add the Raspbian repository so its updates are installed too:

```sh
echo 'Unattended-Upgrade::Origins-Pattern:: "origin=Raspbian,codename=${distro_codename},label=Raspbian";' \
  | sudo tee /etc/apt/apt.conf.d/51unattended-upgrades-raspbian
```

Check what will be upgraded:

```sh
sudo unattended-upgrade --dry-run --debug 2>&1 | grep -i 'allowed origins'
```

The PHP-FPM package restarts its own service when it's updated, so fixes take effect without a manual restart.

## 10. Good practice

- **Keep it on the LAN.** Don't forward any router ports to it: `down.php` will send anyone who can reach it up to 100 MB per request.
- **SSH:** use a strong password, or better, SSH keys.
- **Optional firewall.** Allow SSH *first*, so you don't lock yourself out:
  ```sh
  sudo apt install -y ufw
  sudo ufw allow OpenSSH && sudo ufw allow 80/tcp && sudo ufw enable
  ```

---

## Using it on a school or work network

`.local` names rely on **mDNS**, which works on most home networks but is often blocked on larger ones. If devices can't open `http://wbcst.local/`:

- Ask your network admin for a **DHCP reservation**, so the Pi always gets the same IP address, then use `http://<that-ip>/`.
- Or ask for a regular DNS name pointing at the Pi.

Find the Pi's current IP and MAC address (the MAC is needed for a reservation) with:

```sh
hostname -I; ip -br link show eth0
```

## Plain `http://` limitations (and how to lift them)

This setup serves the tester over plain **`http://`**. Browsers only allow some features on secure pages (`https://`, local files or `localhost`), so over `http://` these are **unavailable**:

- **Screen wake lock:** the device may dim and lock on its normal idle timer during long tests.
- **Webcam and microphone tests**
- **Battery and thermal readings**

Everything else works: the CPU, GPU, RAM, storage and network tests, the screen, keyboard, touch and speaker tests, and reports. The page shows a notice explaining this when it's opened over `http://`.

Ways around it:

1. **Open the tester from GitHub Pages and point it at the Pi (recommended).** Use an address like this:
   ```
   https://myoung8223.github.io/wbcst/?nts=wbcst&t=JQD
   ```
   The page comes over `https://`, so every feature works, and the network test runs against the Pi. Chrome asks once to allow *local network access*; choose **Allow**. On managed Chromebooks, an admin can skip the prompt with the Chrome policy **LocalNetworkAccessAllowedForUrls** set to `https://myoung8223.github.io`. Requires Chrome or Edge 142+, and the Pi needs no changes. See the README's *URL parameters* section for details.
2. **Managed Chromebooks, opening the Pi directly.** In the Google Admin console, set the Chrome policy **OverrideSecurityRestrictionsOnInsecureOrigin**. Search the settings for "insecure origin". List the server's address(es), for example:
   ```
   http://wbcst.local
   http://192.168.1.50
   ```
   Chrome then treats those addresses like `https` sites, and every feature works. Nothing changes on the Pi.
3. **A single unmanaged device.** Open `chrome://flags/#unsafely-treat-insecure-origin-as-secure`, enter the server address, enable it and relaunch Chrome. Managed devices usually block flags.
4. **Serve over `https://`.** The simplest way is a self-signed certificate; see [Optional: HTTPS with a self-signed certificate](#optional-https-with-a-self-signed-certificate). Each device clicks past a warning once. A warning-free setup needs a certificate the devices already trust: one from an internal certificate authority, or a real domain name with a DNS-validated certificate.

## Watching network use live

```sh
sudo apt install -y nload iftop htop
nload eth0                    # total in/out speed with graphs
sudo iftop -i eth0 -n -P      # traffic per connected device
htop                          # CPU load: if PHP-FPM maxes out the CPU, the Pi itself is the bottleneck
```

## Troubleshooting

| Symptom | Likely cause / fix |
|---|---|
| `down.php?bytes=10` shows PHP source code instead of downloading | PHP isn't connected to Apache. Re-run the `a2enmod` / `a2enconf` lines in step 3 and restart Apache. |
| The Network tile says "No test server here" | Open the page from the Pi's address, not a saved copy or GitHub Pages. Check that `down.php` and `up.php` are in `/var/www/html`. |
| `wbcst.local` doesn't resolve | mDNS is blocked on that network. Use the IP address instead (see above). |
| Yellow "Opened over plain http://" notice; webcam/mic disabled; device locks during tests | Expected over `http://`. See [Plain `http://` limitations](#plain-http-limitations-and-how-to-lift-them). |
| Results far below the table above | Make sure the Pi is on wired Ethernet, check `htop` during a test, and test with fewer devices at once. |
| "Some uploads were cut short" in the report | `post_max_size` is below 8M. Check `/etc/php/$PHPV/fpm/conf.d/99-wbcst.ini`. |

---

## Optional: HTTPS with a self-signed certificate

This adds an `https://` address alongside the existing `http://` one. Pages opened over `https://` count as secure, so **screen wake lock, webcam, microphone, battery and thermal readings** all work.

The trade-off: the certificate isn't from an authority browsers know, so each device shows a **"Your connection is not private"** warning the first time. Click **Advanced → Proceed to wbcst.local**. Chrome remembers that choice for a while.

> **Managed Chromebooks** may not be allowed to click past certificate warnings. For them, the admin policy in [Plain `http://` limitations](#plain-http-limitations-and-how-to-lift-them) is simpler, and needs no HTTPS at all.

### 1. Create the certificate and enable HTTPS

```sh
sudo a2enmod ssl
sudo openssl req -x509 -newkey rsa:2048 -nodes -days 825 \
  -keyout /etc/ssl/private/wbcst.key -out /etc/ssl/certs/wbcst.crt \
  -subj "/CN=wbcst.local" -addext "subjectAltName=DNS:wbcst.local"
sudo chmod 600 /etc/ssl/private/wbcst.key

# Point Apache's default HTTPS site at the new certificate and enable it
sudo sed -i 's|^\(\s*SSLCertificateFile\s\).*|\1/etc/ssl/certs/wbcst.crt|;s|^\(\s*SSLCertificateKeyFile\s\).*|\1/etc/ssl/private/wbcst.key|' \
  /etc/apache2/sites-available/default-ssl.conf
sudo a2ensite default-ssl
sudo apache2ctl configtest && sudo systemctl reload apache2
```

If you enabled the firewall, also allow HTTPS:

```sh
sudo ufw allow 443/tcp
```

**Names in the certificate:**

- The certificate must match the address devices use. If you skipped renaming the Pi in step 6, replace `wbcst.local` with `raspberrypi.local` in both places: `CN=` and `DNS:`.
- To also reach it by IP address (once the IP is fixed with a DHCP reservation), list the IP too, for example:
  ```
  -addext "subjectAltName=DNS:wbcst.local,IP:192.168.1.50"
  ```

### 2. Check it

```sh
openssl x509 -in /etc/ssl/certs/wbcst.crt -noout -subject -enddate
curl -sk "https://localhost/down.php?bytes=10" | wc -c      # prints 10
```

Then open **`https://wbcst.local/`** on a device, click through the warning, and confirm the yellow "Opened over plain http://" notice is **gone**.

### Network speed over HTTPS

The Pi 3 and Pi 4 lack hardware encryption support, so HTTPS uses noticeably more CPU on them. The network test may top out lower over `https://` than over `http://`. Compare both. If HTTPS is slower, use `http://` for network tests and `https://` for the peripheral tests. The Pi 5 doesn't have this limitation.

### Renewing

The certificate is valid for 825 days. To renew, run the `openssl req …` and `chmod` commands from step 1 again, then `sudo systemctl reload apache2`. Devices will show the warning once more.

### Turning HTTPS off again

```sh
sudo a2dissite default-ssl
sudo systemctl reload apache2
```

`http://` keeps working either way.
