# CMS Signage Player — Windows v1

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

Installer: `dist/CMS-Signage-Player-1.0.1-x64-Setup.exe`. Build is unsigned unless your own valid signing certificate is configured. Installer/uninstaller, Start Menu entry and optional desktop shortcut settings are configured via electron-builder. Desktop shortcut is off by default. User data remains across upgrades and uninstall.

Version 1.0.1 supports the optional Player API v1 item `mediaTransform` field (zoom, pan, fit/crop/stretch). The adapter and shared Studio renderer apply the same transform during online and cached playback. Older manifests without this field retain their zone fit mode. Upgrade the Player to apply newly configured media transforms; the protocol remains schemaVersion 1. See [UI and transform guide](../../docs/ui-simplification-beginner-guide.md).

`prepare:shared` copies the existing Studio playback modules and manifest schema without changing CMS sources. Run scripts from this repository so those sources are available; generated copies are included in the packaged app. Lockfile is committed as source; node_modules/dist/test-results are ignored.

Full architecture, test results, storage paths, offline/revocation policy, clean uninstall, manual acceptance checks and Android preparation: [docs/windows-player-step8.md](../../docs/windows-player-step8.md).
