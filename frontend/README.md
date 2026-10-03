# Cattr GitLab integration — frontend

This is the Cattr GitLab integration frontend, with npm package name `@amazingcat/cattr-gitlab-integration`. It adds company GitLab settings and user API token settings. Install the [backend module](../README.md) as well for the API and synchronization commands.

## Install from source

Use Node.js 24 and the pnpm version pinned in `package.json`. From this `frontend/` directory, install its dependencies:

```sh
corepack enable
pnpm install --frozen-lockfile
```

From the Cattr server root, link this directory into the local frontend modules and add it to `resources/frontend/etc/modules.local.json`:

```sh
mkdir -p resources/frontend/vendor_modules/AmazingCat
ln -s /absolute/path/to/gitlab-integration/frontend resources/frontend/vendor_modules/AmazingCat/GitlabIntegration
```

```json
{
  "AmazingCat_GitlabIntegration": {
    "type": "local",
    "ref": "AmazingCat_GitlabIntegration",
    "enabled": true
  }
}
```

Merge the entry into an existing `modules.local.json` if the server already uses one. Then build the server frontend from the server root with `pnpm prod` (or use `pnpm dev` during development).

## Install the published npm package

When the package is available in your npm registry, run this from the Cattr server root:

```sh
pnpm add @amazingcat/cattr-gitlab-integration
```

Add the package to `resources/frontend/etc/modules.local.json` (or the server's shared module configuration):

```json
{
  "AmazingCat_GitlabIntegration": {
    "type": "package",
    "ref": "@amazingcat/cattr-gitlab-integration",
    "enabled": true
  }
}
```

Merge the entry into the existing JSON and rebuild the server frontend with `pnpm prod`. The package includes this README and the module's JavaScript and locale files.
