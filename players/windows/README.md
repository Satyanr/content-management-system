# CMS Signage Player — Windows Player v2

Electron app for Windows 10/11 x64. Dependencies and builds are isolated from Laravel/Vite.

```powershell
cd D:\Program\content-management-system\players\windows
npm.cmd ci
npm.cmd start
```

Enter your **HTTPS CMS origin** and the 10-minute activation code generated from Devices in the selected company workspace. Credential stays encrypted in Windows userData and never enters the renderer.

For local development only:

```powershell
# Run the CMS separately with php artisan serve --host=127.0.0.1 --port=8000
npm.cmd start -- --allow-localhost-http
```

Enter `http://127.0.0.1:8000`. The packaged application rejects HTTP even with this flag. Production requires valid HTTPS; no certificate verification bypass is implemented.

Press **Ctrl+Alt+O** for operator settings. Set a 6–12 digit PIN, choose a display, enable auto-start after Windows login, and configure kiosk/topmost/audio. PIN unlock expires after five minutes; five failed attempts block for one minute. Restart, re-pair and manual sync are protected when PIN is configured. Kiosk/PIN are convenience controls, not OS security boundaries.

```powershell
npm.cmd test          # unit + integration/mock API
npm.cmd run test:e2e  # hidden Electron tests on Windows
npm.cmd run pack      # unpacked executable
npm.cmd run dist      # NSIS x64 installer, no publishing
```

Installer: `dist/CMS-Signage-Player-2.0.0-x64-Setup.exe`. Build is unsigned unless your own valid signing certificate is configured. Installer/uninstaller, Start Menu entry and optional desktop shortcut settings are configured via electron-builder. Desktop shortcut is off by default. User data remains across upgrades and uninstall.

Version 2.0.0 requests Player API v2 for always-on primary Playlists, persistent/temporary schedule switching, per-item play limits, durable counters and Proof of Play uploads. Old v1 cached manifests remain supported; v1 API semantics stay unchanged. Update CMS and Player together. Media zoom/pan/fit uses the same shared Studio renderer. See [always-on and Proof of Play guide](../../docs/always-on-playlist-proof-of-play.md) and [UI transform guide](../../docs/ui-simplification-beginner-guide.md).

`prepare:shared` copies the existing Studio playback modules and manifest schema without changing CMS sources. Run scripts from this repository so those sources are available; generated copies are included in the packaged app. Lockfile is committed as source; node_modules/dist/test-results are ignored.

Full architecture, test results, storage paths, offline/revocation policy, clean uninstall, manual acceptance checks and Android preparation: [docs/windows-player-step8.md](../../docs/windows-player-step8.md).

Real Laravel + Electron acceptance (creates and cleans tagged temporary local fixtures; local MySQL must be available):

```powershell
$env:CMS_REAL_ACCEPTANCE = '1'
npx.cmd playwright test tests/e2e/always-on-localhost.spec.js
```

Playback counters and unsent events live in userData/playback/playback.journal, outside media cache GC. Do not manually delete this journal to clean media cache. It contains no device credential. A reset through CMS uses a new counter epoch and preserves history.
