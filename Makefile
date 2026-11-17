# Development, build and release tasks.
#
# The WordPress environment is managed by Wokenv (https://github.com/wokenv/wokenv):
# this Makefile only chains its commands and adds the tasks Wokenv does not
# cover (distribution archives, WordPress.org SVN deploy, translations).
#
# Locally every tool runs through Wokenv, so Docker is the only requirement.
# On CI (CI=true) the tools installed on the runner are used instead. Any tool
# can be overridden, e.g. `make qa COMPOSER=composer`.
#
# To reuse this file in another plugin or theme, edit the "Project" section only.

# ------------------------------------------------------------------------------
# Project
# ------------------------------------------------------------------------------

SLUG      := upload-field-to-youtube-for-acf
MAIN_FILE := $(SLUG).php
SVN_URL   := https://plugins.svn.wordpress.org/$(SLUG)

# Composer package that provides self-updates from GitHub. It is removed from
# the archive published on WordPress.org, where custom updaters are not allowed.
UPDATER_PACKAGE := afragen/git-updater-lite
UPDATER_SUFFIX  := with-git-updater

# ------------------------------------------------------------------------------
# Tools
# ------------------------------------------------------------------------------

WOKENV ?= wokenv

# Path of the project inside the wp-env containers (Wokenv mounts the project
# under the name of its directory).
ENV_CWD ?= wp-content/plugins/$(notdir $(CURDIR))

ifeq ($(CI),true)
COMPOSER ?= composer
NPM      ?= npm
WP       ?= wp
else
COMPOSER ?= $(WOKENV) composer
NPM      ?= $(WOKENV) npm
WP       ?= $(WOKENV) run cli --env-cwd=$(ENV_CWD) -- wp
endif

# ------------------------------------------------------------------------------
# Settings
# ------------------------------------------------------------------------------

DIST_DIR       := dist
TMP_DIR        := tmp
SVN_DIR        := $(TMP_DIR)/svn
SVN_ASSETS_DIR := .wordpress-org

VERSION ?= $(shell git describe --tags --abbrev=0 2>/dev/null | sed 's/^v//')

# Credentials are read from the environment, never from a file in the project.
SVN_USERNAME ?=
SVN_PASSWORD ?=
SVN_TIMEOUT  ?= 600
SVN_AUTH := $(if $(and $(SVN_USERNAME),$(SVN_PASSWORD)),--username $(SVN_USERNAME) --password $(SVN_PASSWORD),)

CROWDIN_PROJECT_ID     ?=
CROWDIN_PERSONAL_TOKEN ?=

# Optional argument for `make qa`, e.g. `make qa analysis`.
QA_SCRIPT := $(if $(filter qa,$(firstword $(MAKECMDGOALS))),$(word 2,$(MAKECMDGOALS)),)

.DEFAULT_GOAL := help

.PHONY: help start stop destroy setup install provision build dev qa test-js \
	crowdin-upload crowdin-download mo dist deploy-svn deploy clean

help: ## Show this help
	@awk 'BEGIN {FS = ":.*## "} /^[a-zA-Z_-]+:.*## / {printf "  \033[36m%-18s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

# ------------------------------------------------------------------------------
# Environment
# ------------------------------------------------------------------------------

start: ## Start WordPress (wokenv start)
	@$(WOKENV) start

stop: ## Stop WordPress, data is kept (wokenv stop)
	@$(WOKENV) stop

destroy: ## Delete containers, volumes and database (wokenv destroy)
	@$(WOKENV) destroy

setup: start install provision ## Start WordPress, install dependencies and load the fixtures

install: ## Install Composer and npm dependencies, build the assets
	@echo "Installing Composer dependencies"
	@$(COMPOSER) install --no-interaction
	@echo "Installing npm dependencies"
	@$(NPM) install
	@$(MAKE) --no-print-directory build

provision: ## Link and activate the development plugins, import the fixtures
	@$(WOKENV) run cli --env-cwd=$(ENV_CWD) -- bash bin/setup-wp.sh

# ------------------------------------------------------------------------------
# Assets
# ------------------------------------------------------------------------------

build: ## Build the assets (production build on CI)
ifeq ($(CI),true)
	@$(NPM) run build:prod
else
	@$(NPM) run build
endif

dev: ## Rebuild the assets when a source file changes
	@$(NPM) run build:watch

# ------------------------------------------------------------------------------
# Quality assurance
# ------------------------------------------------------------------------------

qa: ## Run a Composer QA script: make qa [ci|analysis|lint|quality|rector|security|test|test:coverage]
	@$(COMPOSER) $(if $(QA_SCRIPT),$(QA_SCRIPT),ci)

test-js: ## Run the JavaScript tests
	@$(NPM) run test

# ------------------------------------------------------------------------------
# Translations
# ------------------------------------------------------------------------------
# The Crowdin CLI reads CROWDIN_PROJECT_ID and CROWDIN_PERSONAL_TOKEN from the
# environment (see crowdin.yml).

crowdin-upload: ## Upload the source strings (.pot) to Crowdin
ifneq ($(and $(CROWDIN_PROJECT_ID),$(CROWDIN_PERSONAL_TOKEN)),)
	@$(NPM) run crowdin:upload
else
	@echo "CROWDIN_PROJECT_ID or CROWDIN_PERSONAL_TOKEN not set, skipping"
endif

crowdin-download: ## Download the translations (.po) from Crowdin
ifneq ($(and $(CROWDIN_PROJECT_ID),$(CROWDIN_PERSONAL_TOKEN)),)
	@$(NPM) run crowdin:download
else
	@echo "CROWDIN_PROJECT_ID or CROWDIN_PERSONAL_TOKEN not set, skipping"
endif

mo: ## Compile the .po files into .mo
	@$(WP) i18n make-mo languages

# ------------------------------------------------------------------------------
# Release
# ------------------------------------------------------------------------------

dist: ## Build the distribution archives in dist/ (replaces vendor/ with a --no-dev install)
	@command -v rsync >/dev/null 2>&1 || { echo >&2 "rsync is required but not installed. Aborting."; exit 1; }
	@command -v zip >/dev/null 2>&1 || { echo >&2 "zip is required but not installed. Aborting."; exit 1; }
	@rm -rf $(DIST_DIR)
	@mkdir -p $(DIST_DIR)/$(SLUG)

	@echo "Installing production dependencies"
	@$(COMPOSER) install --no-dev --optimize-autoloader --classmap-authoritative --no-interaction

	@echo "Creating the archive WITH $(UPDATER_PACKAGE)"
	@rsync -a --delete --exclude-from=exclude.txt --include-from=include.txt ./ $(DIST_DIR)/$(SLUG)/
	@cd $(DIST_DIR)/$(SLUG) && zip -qr ../$(SLUG)--$(UPDATER_SUFFIX).zip .

	@echo "Removing $(UPDATER_PACKAGE) for WordPress.org compliance"
	@$(COMPOSER) --working-dir=$(DIST_DIR)/$(SLUG) remove $(UPDATER_PACKAGE) --update-no-dev --optimize-autoloader --classmap-authoritative --no-interaction

	@echo "Reapplying the exclusion rules"
	@mv $(DIST_DIR)/$(SLUG) $(DIST_DIR)/$(SLUG)_old
	@mkdir -p $(DIST_DIR)/$(SLUG)
	@rsync -a --delete --exclude-from=exclude.txt --include-from=include.txt $(DIST_DIR)/$(SLUG)_old/ $(DIST_DIR)/$(SLUG)/
	@rm -rf $(DIST_DIR)/$(SLUG)_old

	@echo "Removing the Update URI header for WordPress.org compliance"
	@sed -i '/\* Update URI:/d' $(DIST_DIR)/$(SLUG)/$(MAIN_FILE)

	@echo "Creating the archive WITHOUT $(UPDATER_PACKAGE)"
	@cd $(DIST_DIR)/$(SLUG) && zip -qr ../$(SLUG).zip .

deploy-svn: ## Publish dist/ on WordPress.org: make deploy-svn VERSION=x.y.z
	@command -v svn >/dev/null 2>&1 || { echo >&2 "svn is required but not installed. Aborting."; exit 1; }
	@[ -n "$(VERSION)" ] || { echo >&2 "VERSION is not set and no git tag was found. Aborting."; exit 1; }
	@[ -d "$(DIST_DIR)/$(SLUG)" ] || { echo >&2 "$(DIST_DIR)/$(SLUG) not found: run 'make dist' first. Aborting."; exit 1; }
	@[ -n "$(SVN_USERNAME)" ] && [ -n "$(SVN_PASSWORD)" ] || { echo >&2 "SVN_USERNAME and SVN_PASSWORD are required. Aborting."; exit 1; }
	@echo "Checking out $(SVN_URL)"
	@rm -rf $(SVN_DIR)
	@svn $(SVN_AUTH) checkout $(SVN_URL)/ $(SVN_DIR)
	@echo "Updating trunk and assets"
	@rsync -a --delete $(DIST_DIR)/$(SLUG)/ $(SVN_DIR)/trunk/
	@rsync -a --delete $(SVN_ASSETS_DIR)/ $(SVN_DIR)/assets/
	@if [ ! -d "$(SVN_DIR)/tags/$(VERSION)" ]; then \
		echo "Creating tag $(VERSION)"; \
		mkdir -p $(SVN_DIR)/tags/$(VERSION); \
		rsync -a --delete $(DIST_DIR)/$(SLUG)/ $(SVN_DIR)/tags/$(VERSION)/; \
	fi
	@echo "Committing to the SVN repository"
# The commands are grouped because every recipe line runs in its own shell,
# so a separate `cd` would not carry over to the next line.
	@cd $(SVN_DIR) && { \
		svn add --force .; \
		svn status | grep '^!' | awk '{print $$2}' | xargs -r svn delete; \
		svn $(SVN_AUTH) --config-option=servers:global:http-timeout=$(SVN_TIMEOUT) commit -m "Release version $(VERSION)"; \
	}
	@rm -rf $(SVN_DIR)
	@echo "SVN deployment completed"

deploy: crowdin-download mo dist deploy-svn ## Download translations, build the archives and publish on WordPress.org

clean: ## Remove build output and installed dependencies
	@rm -rf $(DIST_DIR) $(TMP_DIR) assets node_modules vendor wp-content

# Swallow the optional argument of `make qa <script>` (a pattern rule is needed
# because script names such as test:coverage are not valid explicit targets).
%:
	@:
