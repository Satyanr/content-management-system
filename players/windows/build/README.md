# Packaging resources

NSIS x64 packaging is configured in package.json. No signing certificate is bundled.
The current build uses the standard Electron icon. Supply your own licensed .ico and set build.win.icon before a branded release.

For signed releases, configure a valid signing identity through electron-builder's supported environment/configuration on your release machine. Never commit certificate files, signing passwords or Device credentials. Automatic publishing/updating is not configured.
