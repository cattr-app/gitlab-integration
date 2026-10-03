# Cattr GitLab integration — backend

This repository contains the Cattr GitLab integration backend and frontend. The backend is a Laravel module with Composer package name `cattr/gitlab_integration-module`. The frontend is the npm package `@amazingcat/cattr-gitlab-integration`; see the [frontend README](frontend/README.md) for its installation.

The backend provides GitLab project and issue synchronization, time synchronization, and the settings API. It requires a Cattr server with module support (minimum core version 4.0).

## Install from source

From the Cattr server root, clone this repository into the module directory, then install the server's Composer dependencies so its module Composer definitions are merged:

```sh
git clone https://github.com/cattr-app/gitlab-integration.git modules/CattrGitlabIntegration
composer install
php artisan migrate
```

Keep the repository at `modules/CattrGitlabIntegration`: its `module.json` and `backend/` directory are both used by the module loader. Install the [frontend from source](frontend/README.md#install-from-source) separately if you are developing the UI.

## Install the published Composer package

When `cattr/gitlab_integration-module` is available from your configured Composer repository, run these commands from the Cattr server root:

```sh
composer require cattr/gitlab_integration-module
php artisan migrate
```

The link makes `module.json` discoverable under the server's `modules/` directory. Install the [published frontend package](frontend/README.md#install-the-published-npm-package) separately.

Enable and configure the integration in Cattr's company and user settings. The server scheduler runs `gitlab:sync` every five minutes and runs `gitlab:sync-time` at the configured interval; ensure the Cattr scheduler is running.
