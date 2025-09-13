#https://stackoverflow.com/a/44061904/3929620
# 1. Minimal approach - direct entry points only
.PHONY: all setup check up install dev qa deploy-ci deploy crowdin-upload crowdin-download crowdin-build-mo changelog down mta help

# 2. Purist approach - all entry points (technically correct)
#.PHONY: (all entry points)

# Environment Variables Handling:
# This Makefile includes .env file which contains default values for all variables.
# This ensures variables are properly passed to Docker containers via docker-compose.
# The .env file takes precedence over system environment variables, so in CI workflows
# we must pass variables as Make parameters (e.g., VARIABLE=value make target)
# rather than using environment variables (env: VARIABLE=value).
# 
# Example:
# ✅ Correct:   run: make <target> VARIABLE=value
# ❌ Wrong:     run: make <target>
#			   env:
#					VARIABLE: value
include .env

CURRENT_BRANCH ?=

PLUGIN_NAME ?=
PLUGIN_VERSION ?=

MARIADB_TAG ?= latest
MARIADB_ALLOW_EMPTY_PASSWORD ?= yes
MARIADB_USER ?= user
MARIADB_PASSWORD ?=
MARIADB_DATABASE ?= wordpress

WORDPRESS_TAG ?= latest
WORDPRESS_ALLOW_EMPTY_PASSWORD ?= yes
WORDPRESS_DATABASE_HOST ?= mariadb
WORDPRESS_DATABASE_PORT_NUMBER ?= 3306
WORDPRESS_DATABASE_NAME ?= wordpress
WORDPRESS_DATABASE_USER ?= user
WORDPRESS_DATABASE_PASSWORD ?=
WORDPRESS_USERNAME ?= admin
WORDPRESS_PASSWORD ?= password
WORDPRESS_PLUGINS ?=
WORDPRESS_SMTP_HOST ?= mailpit
WORDPRESS_SMTP_PORT_NUMBER ?= 1025
WORDPRESS_SMTP_USER ?=
WORDPRESS_SMTP_PASSWORD ?=
WORDPRESS_SMTP_PROTOCOL ?= tls
WORDPRESS_MTA ?=

NODE_TAG ?= latest
NODE_PORT ?= 1337
NODE_ENV ?= development
NODE_DEBUG ?=
NODE_LOG_LEVEL ?=

PHPMYADMIN_TAG ?= latest
PHPMYADMIN_HTTP_PORT ?= 8080
PHPMYADMIN_HTTPS_PORT ?= 8443
PHPMYADMIN_ALLOW_NO_PASSWORD ?= yes
PHPMYADMIN_DATABASE_HOST ?= mariadb
PHPMYADMIN_DATABASE_USER ?= user
PHPMYADMIN_DATABASE_PASSWORD ?=
PHPMYADMIN_DATABASE_PORT_NUMBER ?= 3306
PHPMYADMIN_DATABASE_ENABLE_SSL ?= no
PHPMYADMIN_DATABASE_SSL_KEY ?=
PHPMYADMIN_DATABASE_SSL_CERT ?=
PHPMYADMIN_DATABASE_SSL_CA ?=
PHPMYADMIN_DATABASE_SSL_CA_PATH ?=
PHPMYADMIN_DATABASE_SSL_CIPHERS ?=
PHPMYADMIN_DATABASE_SSL_VERIFY ?= yes

MAILPIT_TAG ?= latest
MAILPIT_HTTP_PORT ?= 8025
MAILPIT_MAX_MESSAGES ?= 5000

OPENAI_KEY ?=

GITHUB_TOKEN ?=

CROWDIN_PROJECT_ID ?=
CROWDIN_PERSONAL_TOKEN ?=

PHPSTAN_PRO_WEB_PORT ?=

WPSPAGHETTI_UFTYFACF_GOOGLE_OAUTH_CLIENT_ID ?=
WPSPAGHETTI_UFTYFACF_GOOGLE_OAUTH_CLIENT_SECRET ?=
WPSPAGHETTI_UFTYFACF_SERVER_UPLOAD_ENABLED ?= false
WPSPAGHETTI_UFTYFACF_VITE_CACHE_BUSTING_ENABLED ?= false

MODE ?= develop

DOCKER_COMPOSE=docker compose
WORDPRESS_CONTAINER_NAME=wordpress
WORDPRESS_CONTAINER_USER=root
NODE_CONTAINER_NAME=node
NODE_CONTAINER_USER=root
NODE_CONTAINER_WORKSPACE_DIR=/app
TMP_DIR=tmp
DIST_DIR=dist
SVN_DIR=svn
SVN_ASSETS_DIR=.wordpress-org

SVN_USERNAME ?=
SVN_PASSWORD ?=
SVN_AUTH := $(if $(and $(SVN_USERNAME),$(SVN_PASSWORD)),--username $(SVN_USERNAME) --password $(SVN_PASSWORD),)

# Capture script/action argument
SCRIPT_ARG := $(word 2,$(MAKECMDGOALS))
COMPOSER_CMD := $(if $(SCRIPT_ARG),composer $(SCRIPT_ARG),composer check)
MTA_CMD := $(if $(SCRIPT_ARG),$(SCRIPT_ARG),status)

all: setup up

setup: check .gitconfig docker-compose.override.yml $(TMP_DIR)/certs $(TMP_DIR)/wait-for-it.sh set-env

install: all wait install-node install-wordpress

dev: setup dev-node

qa: setup qa-node qa-wordpress

deploy-ci: crowdin-download crowdin-build-mo deploy-zip deploy-svn

deploy: install deploy-ci

check:
	@echo "Checking requirements"
	@command -v awk >/dev/null 2>&1 || { echo >&2 "❌ awk is required but not installed. Aborting."; exit 1; }
	@command -v mkcert >/dev/null 2>&1 || { echo >&2 "❌ mkcert is required but not installed. Aborting."; exit 1; }
	@command -v curl >/dev/null 2>&1 || { echo >&2 "❌ curl is required but not installed. Aborting."; exit 1; }
	@command -v git >/dev/null 2>&1 || { echo >&2 "❌ git is required but not installed. Aborting."; exit 1; }
	@command -v rsync >/dev/null 2>&1 || { echo >&2 "❌ rsync is required but not installed. Aborting."; exit 1; }
	@command -v zip >/dev/null 2>&1 || { echo >&2 "❌ zip is required but not installed. Aborting."; exit 1; }
	@echo "✅ All requirements are met"

.gitconfig: 
	@echo "Setting up .gitconfig"
	@cp -a .gitconfig.dist .gitconfig
	@git config --local include.path ../.gitconfig

docker-compose.override.yml: 
	@echo "Setting up docker-compose.override.yml"
	@cp -a docker-compose.override.yml.dist docker-compose.override.yml

$(TMP_DIR)/certs:
	@echo "Generating SSL certificates"
	@mkdir -p $(TMP_DIR)/certs
	@mkcert -cert-file "$(TMP_DIR)/certs/server.crt" -key-file "$(TMP_DIR)/certs/server.key" localhost 127.0.0.1 ::1 bs-local.com "*.bs-local.com"
	@chmod +r $(TMP_DIR)/certs/server.*
	@cp -a $(TMP_DIR)/certs/server.crt $(TMP_DIR)/certs/tls.crt
	@cp -a $(TMP_DIR)/certs/server.key $(TMP_DIR)/certs/tls.key

$(TMP_DIR)/wait-for-it.sh:
	@echo "Downloading wait-for-it.sh"
	@mkdir -p $(TMP_DIR)
	@curl -o $(TMP_DIR)/wait-for-it.sh https://raw.githubusercontent.com/vishnubob/wait-for-it/master/wait-for-it.sh
	@chmod +x $(TMP_DIR)/wait-for-it.sh

set-env:
	@echo "Setting environment variables"
ifeq ($(CURRENT_BRANCH),)
	@$(eval CURRENT_BRANCH := $(shell git rev-parse --abbrev-ref HEAD 2>/dev/null || echo "$${GITHUB_REF##*/}"))
	@echo "CURRENT_BRANCH set to: $(CURRENT_BRANCH)"
endif
ifeq ($(PLUGIN_NAME),)
	@$(eval PLUGIN_NAME := $(shell basename `git rev-parse --show-toplevel`))
	@if [ -n "$(PLUGIN_NAME)" ]; then \
		echo "PLUGIN_NAME set to: $(PLUGIN_NAME)"; \
	else \
		echo "❌ PLUGIN_NAME is not set and could not be determined."; \
		exit 1; \
	fi
endif
ifeq ($(PLUGIN_VERSION),)
	@$(eval PLUGIN_VERSION := $(shell git describe --tags --abbrev=0 2>/dev/null | sed 's/^v//' || echo "dev"))
	@echo "PLUGIN_VERSION set to: $(PLUGIN_VERSION)"
endif

wait:
	@echo "Waiting for services to be ready"
	@$(TMP_DIR)/wait-for-it.sh localhost:$(NODE_PORT) --timeout=300 --strict -- echo "Node is up"
	@$(TMP_DIR)/wait-for-it.sh localhost:80 --timeout=300 --strict -- echo "WordPress is up"

	@echo "Waiting for WordPress to complete setup"
#https://cardinalby.github.io/blog/post/github-actions/implementing-deferred-steps/
# method #1
#	@$(DOCKER_COMPOSE) exec -u$(WORDPRESS_CONTAINER_USER) $(WORDPRESS_CONTAINER_NAME) sh -c 'timeout=300; while [ $$timeout -gt 0 ]; do \
#		[ -f $${WORDPRESS_CONF_FILE:-/bitnami/wordpress/wp-config.php} ] && break; \
#		echo "[wordpress] Waiting for wp-config.php ($$timeout seconds left)..."; \
#		sleep 5; timeout=$$((timeout - 5)); \
#	done; \
#	[ $$timeout -gt 0 ] || { echo "❌ Error: Timeout reached, wp-config.php not found"; exit 1; }'

# method #2
	@./docker/logs-catcher.sh $(WORDPRESS_CONTAINER_NAME) "WordPress setup finished" 300

up:
	@echo "Starting docker compose services"
	@MARIADB_TAG=${MARIADB_TAG} WORDPRESS_TAG=${WORDPRESS_TAG} NODE_TAG=${NODE_TAG} $(DOCKER_COMPOSE) up -d --build

install-node: clean-node
	@echo "[node] Installing dependencies"
	@$(DOCKER_COMPOSE) exec -u$(NODE_CONTAINER_USER) $(NODE_CONTAINER_NAME) sh -c 'cd $(NODE_CONTAINER_WORKSPACE_DIR)/$(PLUGIN_NAME) && npm install'
ifneq ($(or $(filter true,$(GITHUB_ACTIONS)),$(filter production,$(MODE))),)
	@$(DOCKER_COMPOSE) exec -u$(NODE_CONTAINER_USER) $(NODE_CONTAINER_NAME) sh -c 'cd $(NODE_CONTAINER_WORKSPACE_DIR)/$(PLUGIN_NAME) && npm run build:prod'
else
	@$(DOCKER_COMPOSE) exec -u$(NODE_CONTAINER_USER) $(NODE_CONTAINER_NAME) sh -c 'cd $(NODE_CONTAINER_WORKSPACE_DIR)/$(PLUGIN_NAME) && npm run build'
endif

install-wordpress: clean-wordpress
ifneq ($(GITHUB_TOKEN),)
	@echo "[wordpress] Updating composer config"
	@$(DOCKER_COMPOSE) exec -u$(WORDPRESS_CONTAINER_USER) $(WORDPRESS_CONTAINER_NAME) sh -c 'composer config -g github-oauth.github.com $(GITHUB_TOKEN)'
endif

	@echo "[wordpress] Initializing git repository"
#FIXED: safe.directory avoids Github fatal error: detected dubious ownership in repository
	@$(DOCKER_COMPOSE) exec -u$(WORDPRESS_CONTAINER_USER) $(WORDPRESS_CONTAINER_NAME) sh -c 'cd /tmp/$(PLUGIN_NAME)-plugin && { \
		git init; \
		git config --global user.email "you@example.com"; \
		git config --global user.name "Your Name"; \
		git config --global --add safe.directory /tmp/$(PLUGIN_NAME)-plugin; \
	}'

	@echo "[wordpress] Creating mu-plugins directory"
	@$(DOCKER_COMPOSE) exec -u$(WORDPRESS_CONTAINER_USER) $(WORDPRESS_CONTAINER_NAME) sh -c 'mkdir -p $${WORDPRESS_BASE_DIR:-/bitnami/wordpress}/wp-content/mu-plugins'

	@echo "[wordpress] Creating symbolic links"
	@$(DOCKER_COMPOSE) exec -u$(WORDPRESS_CONTAINER_USER) $(WORDPRESS_CONTAINER_NAME) sh -c 'ln -sfn /tmp/$(PLUGIN_NAME)-plugin $${WORDPRESS_BASE_DIR:-/bitnami/wordpress}/wp-content/plugins/$(PLUGIN_NAME)'
	@$(DOCKER_COMPOSE) exec -u$(WORDPRESS_CONTAINER_USER) $(WORDPRESS_CONTAINER_NAME) sh -c 'ln -sfn /tmp/wonolog.php $${WORDPRESS_BASE_DIR:-/bitnami/wordpress}/wp-content/mu-plugins/wonolog.php'
	@$(DOCKER_COMPOSE) exec -u$(WORDPRESS_CONTAINER_USER) $(WORDPRESS_CONTAINER_NAME) sh -c 'ln -sfn /tmp/$(PLUGIN_NAME)-plugin/tests/data/wp-cfm $${WORDPRESS_BASE_DIR:-/bitnami/wordpress}/wp-content/config'

	@echo "[wordpress] Updating wp-config.php"
	@$(DOCKER_COMPOSE) exec -u$(WORDPRESS_CONTAINER_USER) $(WORDPRESS_CONTAINER_NAME) sh -c "sed -i '/define('\''WPSPAGHETTI_UFTYFACF_GOOGLE_OAUTH_CLIENT_ID'\'',/d' $${WORDPRESS_BASE_DIR:-/bitnami/wordpress}/wp-config.php && sed -i '1a define('\''WPSPAGHETTI_UFTYFACF_GOOGLE_OAUTH_CLIENT_ID'\'', '\''${WPSPAGHETTI_UFTYFACF_GOOGLE_OAUTH_CLIENT_ID}'\'');' $${WORDPRESS_BASE_DIR:-/bitnami/wordpress}/wp-config.php"
	@$(DOCKER_COMPOSE) exec -u$(WORDPRESS_CONTAINER_USER) $(WORDPRESS_CONTAINER_NAME) sh -c "sed -i '/define('\''WPSPAGHETTI_UFTYFACF_GOOGLE_OAUTH_CLIENT_SECRET'\'',/d' $${WORDPRESS_BASE_DIR:-/bitnami/wordpress}/wp-config.php && sed -i '2a define('\''WPSPAGHETTI_UFTYFACF_GOOGLE_OAUTH_CLIENT_SECRET'\'', '\''${WPSPAGHETTI_UFTYFACF_GOOGLE_OAUTH_CLIENT_SECRET}'\'');' $${WORDPRESS_BASE_DIR:-/bitnami/wordpress}/wp-config.php"
	@$(DOCKER_COMPOSE) exec -u$(WORDPRESS_CONTAINER_USER) $(WORDPRESS_CONTAINER_NAME) sh -c "sed -i '/define('\''WPSPAGHETTI_UFTYFACF_SERVER_UPLOAD_ENABLED'\'',/d' $${WORDPRESS_BASE_DIR:-/bitnami/wordpress}/wp-config.php && sed -i '3a define('\''WPSPAGHETTI_UFTYFACF_SERVER_UPLOAD_ENABLED'\'', ${WPSPAGHETTI_UFTYFACF_SERVER_UPLOAD_ENABLED});' $${WORDPRESS_BASE_DIR:-/bitnami/wordpress}/wp-config.php"
	@$(DOCKER_COMPOSE) exec -u$(WORDPRESS_CONTAINER_USER) $(WORDPRESS_CONTAINER_NAME) sh -c "sed -i '/define('\''WPSPAGHETTI_UFTYFACF_VITE_CACHE_BUSTING_ENABLED'\'',/d' $${WORDPRESS_BASE_DIR:-/bitnami/wordpress}/wp-config.php && sed -i '3a define('\''WPSPAGHETTI_UFTYFACF_VITE_CACHE_BUSTING_ENABLED'\'', ${WPSPAGHETTI_UFTYFACF_VITE_CACHE_BUSTING_ENABLED});' $${WORDPRESS_BASE_DIR:-/bitnami/wordpress}/wp-config.php"
	
	@echo "[wordpress] Installing dependencies"
# PHP 7.x and 8.x interpret composer.json's `extra.installer-paths` differently, perhaps due to different versions of Composer.
# With PHP 7.x `cd $${WORDPRESS_BASE_DIR:-/bitnami/wordpress}/wp-content/plugins/$(PLUGIN_NAME)` and
# `extra.installer-paths."../{$name}/"` in the composer.json seems to be sufficient, while with PHP 8.x it is not.
# Adding Composer's `--working-dir` option with PHP 8.x doesn't work.
# For this reason, the absolute path `extra.installer-paths` had to be specified in the composer.json.
ifeq ($(MODE),production)
# To force a certain version of php you can use:
# composer config platform.php 8.0 && <composer command> && composer config --unset platform.php
	@$(DOCKER_COMPOSE) exec -u$(WORDPRESS_CONTAINER_USER) $(WORDPRESS_CONTAINER_NAME) sh -c 'cd $${WORDPRESS_BASE_DIR:-/bitnami/wordpress}/wp-content/plugins/$(PLUGIN_NAME) && composer install --optimize-autoloader --classmap-authoritative --no-dev --no-interaction'
else
	@$(DOCKER_COMPOSE) exec -u$(WORDPRESS_CONTAINER_USER) $(WORDPRESS_CONTAINER_NAME) sh -c 'cd $${WORDPRESS_BASE_DIR:-/bitnami/wordpress}/wp-content/plugins/$(PLUGIN_NAME) && composer update --optimize-autoloader --no-interaction'

	@echo "[wordpress] Activate WP-CFM plugin"
	@$(DOCKER_COMPOSE) exec -u$(WORDPRESS_CONTAINER_USER) $(WORDPRESS_CONTAINER_NAME) sh -c 'wp plugin activate wp-cfm --allow-root'
	
	@echo "[wordpress] Pulling WP-CFM bundles"
	@$(DOCKER_COMPOSE) exec -u$(WORDPRESS_CONTAINER_USER) $(WORDPRESS_CONTAINER_NAME) sh -c 'for file in $${WORDPRESS_BASE_DIR:-/bitnami/wordpress}/wp-content/config/*.json; do wp config pull $$(basename $$file .json) --allow-root; done'
	
	@echo "[wordpress] Cleaning ACF data"
	@$(DOCKER_COMPOSE) exec -u$(WORDPRESS_CONTAINER_USER) $(WORDPRESS_CONTAINER_NAME) sh -c 'wp acf clean --allow-root'
	
	@echo "[wordpress] Importing ACF JSON files"
	@$(DOCKER_COMPOSE) exec -u$(WORDPRESS_CONTAINER_USER) $(WORDPRESS_CONTAINER_NAME) sh -c 'for file in $${WORDPRESS_BASE_DIR:-/bitnami/wordpress}/wp-content/plugins/$(PLUGIN_NAME)/tests/data/acf/*.json; do wp acf import --json_file=$${file} --allow-root; done'
endif

	@echo "[wordpress] Flushing rewrite rules"
	@$(DOCKER_COMPOSE) exec -u$(WORDPRESS_CONTAINER_USER) $(WORDPRESS_CONTAINER_NAME) sh -c 'wp rewrite flush --allow-root'

	@echo "[wordpress] Changing data folder ownership"
# Avoids write permission errors when PHP writes w/ 1001 user
	@$(DOCKER_COMPOSE) exec -u$(WORDPRESS_CONTAINER_USER) $(WORDPRESS_CONTAINER_NAME) sh -c 'chmod -Rf o+w /tmp/$(PLUGIN_NAME)-plugin/tests/data/wp-cfm || true'

	@echo "[wordpress] Changing other plugins folders ownership"
	@$(DOCKER_COMPOSE) exec -u $(WORDPRESS_CONTAINER_USER) $(WORDPRESS_CONTAINER_NAME) sh -c 'for dir in $${WORDPRESS_BASE_DIR:-/bitnami/wordpress}/wp-content/plugins/*; do if [ "$$(basename $$dir)" != "$(PLUGIN_NAME)" ]; then chown -R 1001 $$dir || true; fi; done'
	
	@echo "[wordpress] Changing wp-config.php permissions"
	@$(DOCKER_COMPOSE) exec -u$(WORDPRESS_CONTAINER_USER) $(WORDPRESS_CONTAINER_NAME) sh -c 'chmod 666 $${WORDPRESS_BASE_DIR:-/bitnami/wordpress}/wp-config.php || true'

	@echo "[wordpress] Redirecting debug.log to stderr"
	@$(DOCKER_COMPOSE) exec -u$(WORDPRESS_CONTAINER_USER) $(WORDPRESS_CONTAINER_NAME) sh -c 'rm -f $${WORDPRESS_BASE_DIR:-/bitnami/wordpress}/wp-content/debug.log && ln -sfn /dev/stderr $${WORDPRESS_BASE_DIR:-/bitnami/wordpress}/wp-content/debug.log || true'

	@echo "[wordpress] Starting MTA daemon"
	@$(DOCKER_COMPOSE) exec -u$(WORDPRESS_CONTAINER_USER) $(WORDPRESS_CONTAINER_NAME) /usr/local/bin/mta-manager.sh start

dev-node:
	@echo "[node] Starting development server"
	@$(DOCKER_COMPOSE) exec -u$(NODE_CONTAINER_USER) $(NODE_CONTAINER_NAME) sh -c 'cd $(NODE_CONTAINER_WORKSPACE_DIR)/$(PLUGIN_NAME) && npm run dev'

qa-node:
	@echo "[node] Running tests"
	@$(DOCKER_COMPOSE) exec -u$(NODE_CONTAINER_USER) $(NODE_CONTAINER_NAME) sh -c 'cd $(NODE_CONTAINER_WORKSPACE_DIR)/$(PLUGIN_NAME) && npm run test'

qa-wordpress:
	@echo "[wordpress] Updating git repository"
	@$(DOCKER_COMPOSE) exec -u$(WORDPRESS_CONTAINER_USER) $(WORDPRESS_CONTAINER_NAME) sh -c 'cd $${WORDPRESS_BASE_DIR:-/bitnami/wordpress}/wp-content/plugins/$(PLUGIN_NAME) && git add .'
	
	@echo "[wordpress] Running $(if $(COMPOSER_SCRIPT),$(COMPOSER_SCRIPT),check)"
	@$(DOCKER_COMPOSE) exec -u$(WORDPRESS_CONTAINER_USER) $(WORDPRESS_CONTAINER_NAME) sh -c 'cd $${WORDPRESS_BASE_DIR:-/bitnami/wordpress}/wp-content/plugins/$(PLUGIN_NAME) && $(COMPOSER_CMD)'

deploy-zip:
	@echo "Deploying to zip file"

# Fix DIST_DIR permissions, because it is mounted as a volume by docker-compose
	@echo "Debug: Current user/group: $(shell id -u):$(shell id -g) ($(shell whoami))"
	@echo "Debug: Working directory: $(shell pwd)"
	@echo "Debug: Before fix - $(DIST_DIR) permissions: $(shell ls -ld $(DIST_DIR) 2>/dev/null || echo 'Cannot read permissions')"
	@sudo chmod 755 $(DIST_DIR) 2>/dev/null || chmod 755 $(DIST_DIR) 2>/dev/null || true
	@sudo chown -R $(shell id -u):$(shell id -g) $(DIST_DIR) 2>/dev/null || true
	@echo "Debug: After fix - $(DIST_DIR) permissions: $(shell ls -ld $(DIST_DIR) 2>/dev/null || echo 'Cannot read permissions')"

	@mkdir -p $(DIST_DIR)/$(PLUGIN_NAME)
	@cd $(PLUGIN_NAME) && rsync -a --delete --exclude-from=exclude_from.txt --include-from=include_from.txt . ../$(DIST_DIR)/$(PLUGIN_NAME)/

	@echo "Creating version WITH Git Updater Lite for Composer installations"
	@cd $(DIST_DIR)/$(PLUGIN_NAME) && zip -qr ../$(PLUGIN_NAME)--with-git-updater.zip .

	@echo "[wordpress] Removing git-updater-lite dependency for WordPress compliance"
# To force a certain version of php you can use:
# composer config platform.php 8.0 && <composer command> && composer config --unset platform.php
	@$(DOCKER_COMPOSE) exec -u$(WORDPRESS_CONTAINER_USER) $(WORDPRESS_CONTAINER_NAME) sh -c 'cd /tmp/dist/$(PLUGIN_NAME) && composer remove afragen/git-updater-lite --update-no-dev --optimize-autoloader --classmap-authoritative --no-interaction'

	@echo "Creating clean copy and reappling exclusion rules"
	@cp -r $(DIST_DIR)/$(PLUGIN_NAME) $(DIST_DIR)/$(PLUGIN_NAME)_clean
	@rsync -a --delete --exclude-from=exclude_from.txt --include-from=include_from.txt $(DIST_DIR)/$(PLUGIN_NAME)_clean/ $(DIST_DIR)/$(PLUGIN_NAME)/
	@rm -rf $(DIST_DIR)/$(PLUGIN_NAME)_clean

	@echo "Removing Update URI header for WordPress compliance"
	@sed -i '/\* Update URI:/d' $(DIST_DIR)/$(PLUGIN_NAME)/$(PLUGIN_NAME).php

	@echo "Creating standard version WITHOUT Git Updater Lite for WordPress.org"
	@cd $(DIST_DIR)/$(PLUGIN_NAME) && zip -qr ../$(PLUGIN_NAME).zip .

deploy-svn:
ifeq ($(GITHUB_ACTIONS),true)
	@command -v svn >/dev/null 2>&1 || { echo >&2 "❌ svn is required but not installed. Aborting."; exit 1; }
	@echo "Checking WordPress SVN repository"
	@if svn ls https://plugins.svn.wordpress.org/$(PLUGIN_NAME)/ >/dev/null 2>&1; then \
		echo "SVN repository exists, checking credentials"; \
		if [ -n "$(SVN_USERNAME)" ] && [ -n "$(SVN_PASSWORD)" ]; then \
			echo "Deploying to WordPress SVN"; \
			svn $(SVN_AUTH) checkout https://plugins.svn.wordpress.org/$(PLUGIN_NAME)/ $(TMP_DIR)/$(SVN_DIR); \
			if [ "$(CURRENT_BRANCH)" != support/* ]; then \
				echo "Deploying to trunk and assets"; \
				rsync -a --delete $(DIST_DIR)/$(PLUGIN_NAME)/ $(TMP_DIR)/$(SVN_DIR)/trunk/; \
				rsync -a --delete $(SVN_ASSETS_DIR)/ $(TMP_DIR)/$(SVN_DIR)/assets/; \
			else \
				echo "❌ Support branch detected, skipping..."; \
			fi; \
			if [ ! -d "$(TMP_DIR)/$(SVN_DIR)/tags/$(PLUGIN_VERSION)" ]; then \
				echo "Creating tag v$(PLUGIN_VERSION)"; \
				mkdir -p $(TMP_DIR)/$(SVN_DIR)/tags/$(PLUGIN_VERSION); \
				rsync -a --delete $(DIST_DIR)/$(PLUGIN_NAME)/ $(TMP_DIR)/$(SVN_DIR)/tags/$(PLUGIN_VERSION)/; \
			fi; \
			echo "Committing to SVN repository"; \
			cd $(TMP_DIR)/$(SVN_DIR) && svn add --force .; \
			# Removes files that have been deleted from the project \
			cd $(TMP_DIR)/$(SVN_DIR) && svn status | grep '^!' | awk '{print $$2}' | xargs -r svn delete; \
			cd $(TMP_DIR)/$(SVN_DIR) && svn $(SVN_AUTH) commit -m "Release version $(PLUGIN_VERSION)"; \
			# Do not delete DIST_DIR completely, because it is mounted as a volume by docker-compose \
			rm -rf $(TMP_DIR)/$(SVN_DIR) $(DIST_DIR)/$(PLUGIN_NAME); \
			echo "✅ SVN deployment completed successfully"; \
		else \
			echo "❌ SVN credentials (SVN_USERNAME and SVN_PASSWORD) are required, skipping..."; \
		fi; \
	else \
		echo "❌ SVN repository does not exist, skipping..."; \
	fi
else
	@echo "❌ SVN deployment only available in CI, skipping.."
endif

crowdin-upload: setup
ifneq ($(and $(CROWDIN_PROJECT_ID),$(CROWDIN_PERSONAL_TOKEN)),)
	@if [ "$(CURRENT_BRANCH)" != "support/*" ]; then \
		echo "[node] Uploading sources to Crowdin"; \
		$(DOCKER_COMPOSE) exec -u$(NODE_CONTAINER_USER) $(NODE_CONTAINER_NAME) sh -c 'cd $(NODE_CONTAINER_WORKSPACE_DIR)/$(PLUGIN_NAME) && npm run crowdin:upload'; \
		echo "✅ Sources uploaded to Crowdin"; \
	else \
		echo "❌ Support branch detected, skipping..."; \
	fi
else
	@echo "❌ CROWDIN_PROJECT_ID or CROWDIN_PERSONAL_TOKEN not set, skipping..."
endif

crowdin-download: setup
ifneq ($(and $(CROWDIN_PROJECT_ID),$(CROWDIN_PERSONAL_TOKEN)),)
	@if [ "$(CURRENT_BRANCH)" != "support/*" ]; then \
		echo "[node] Downloading translations from Crowdin"; \
		$(DOCKER_COMPOSE) exec -u$(NODE_CONTAINER_USER) $(NODE_CONTAINER_NAME) sh -c 'cd $(NODE_CONTAINER_WORKSPACE_DIR)/$(PLUGIN_NAME) && npm run crowdin:download'; \
		echo "✅ Translations downloaded from Crowdin"; \
	else \
		echo "❌ Current branch is a support branch, skipping..."; \
	fi
else
	@echo "❌ CROWDIN_PROJECT_ID or CROWDIN_PERSONAL_TOKEN not set, skipping..."
endif

crowdin-build-mo: setup
	@command -v msgfmt >/dev/null 2>&1 || { echo >&2 "❌ msgfmt is required but not installed. Aborting."; exit 1; }
	@echo "Converting PO to MO files"
	@find $(PLUGIN_NAME)/languages/ -name "*.po" -exec sh -c 'msgfmt "$$0" -o "$${0%.po}.mo"' {} \;
	@echo "✅ PO files converted to MO format"

changelog:
	@echo "[node] Initializing changelog with historical releases"
	@$(DOCKER_COMPOSE) exec -u$(NODE_CONTAINER_USER) $(NODE_CONTAINER_NAME) sh -c 'cd $(NODE_CONTAINER_WORKSPACE_DIR) && \
		if [ ! -f CHANGELOG.md ]; then \
			echo "CHANGELOG.md not found, generating from git history..."; \
			mkdir -p /tmp/changelog && cd /tmp/changelog && \
			npm init -y >/dev/null 2>&1 && \
			npm install --no-save conventional-changelog-cli >/dev/null 2>&1 && \
			cd $(NODE_CONTAINER_WORKSPACE_DIR) && \
			HEADER=$$(node -e "const config = require(\"./.releaserc.json\"); console.log(config.plugins.find(p => p[0] === \"@semantic-release/changelog\")[1].changelogTitle)") && \
			/tmp/changelog/node_modules/.bin/conventional-changelog -p conventionalcommits -r 0 > /tmp/changelog/body.md && \
			echo "$$HEADER" > CHANGELOG.md && \
			echo "" >> CHANGELOG.md && \
			grep -v "^## \[\]" /tmp/changelog/body.md | awk "/^## / && NR>1 {print \"\"} {print}" >> CHANGELOG.md && \
			rm -rf /tmp/changelog && \
			echo "✅ CHANGELOG.md populated with historical releases"; \
		else \
			echo "❌ CHANGELOG.md already exists, skipping..."; \
		fi'

clean-node: 
	@echo "[node] Cleaning artifacts"
	@$(DOCKER_COMPOSE) exec -u$(NODE_CONTAINER_USER) $(NODE_CONTAINER_NAME) sh -c 'cd $(NODE_CONTAINER_WORKSPACE_DIR)/$(PLUGIN_NAME) && rm -rf node_modules package-lock.json assets'

clean-wordpress: 
	@echo "[wordpress] Cleaning artifacts"
	@$(DOCKER_COMPOSE) exec -u$(WORDPRESS_CONTAINER_USER) $(WORDPRESS_CONTAINER_NAME) sh -c 'if [ -d "$${WORDPRESS_BASE_DIR:-/bitnami/wordpress}/wp-content/plugins/$(PLUGIN_NAME)" ]; then cd $${WORDPRESS_BASE_DIR:-/bitnami/wordpress}/wp-content/plugins/$(PLUGIN_NAME) && rm -rf .git vendor composer.lock; fi'
# Do not delete DIST_DIR completely, because it is mounted as a volume by docker-compose
	@rm -rf $(DIST_DIR)/*

down: 
	@echo "Stopping docker compose services"
	@$(DOCKER_COMPOSE) down

mta:
	@echo "[wordpress] Running MTA $(MTA_CMD)"
	@$(DOCKER_COMPOSE) exec -u$(WORDPRESS_CONTAINER_USER) $(WORDPRESS_CONTAINER_NAME) /usr/local/bin/mta-manager.sh $(MTA_CMD)

help:
	@echo "Makefile targets:"
	@echo "  all               - Start environment"
	@echo "  install           - Start environment and install dependencies"
	@echo "  dev               - Start development server with HMR"
	@echo "  qa [script]       - Run quality assurance (qa-node + qa-php)"
	@echo "  deploy            - Start environment, install dependencies and deploy to zip and SVN"
	@echo "  changelog    	   - Generate CHANGELOG.md with historical releases from git tags
	@echo "  down              - Stop environment"
	@echo ""
	@echo "Translation Management:"
	@echo "  crowdin-upload    - Upload source files (.pot) to Crowdin"
	@echo "  crowdin-download  - Download translations (.po) from Crowdin"
	@echo "  crowdin-build-mo  - Convert PO files to MO format"
	@echo ""
	@echo "Quality Assurance:"
	@echo "  qa                - Run all checks (default: composer check)"
	@echo "  qa analysis       - Run static analysis (PHPStan, Psalm, etc.)"
	@echo "  qa lint           - Run code linting (PHP CS Fixer, Rector, etc.)"
	@echo "  qa security       - Run security checks"
	@echo "  qa test           - Run unit tests (PHPUnit, etc.)"
	@echo "  qa test:coverage  - Run unit tests with coverage report"
	@echo ""
	@echo "MTA management:"	
	@echo "  mta [action]      - Manage MTA daemon (default: status)"
	@echo "                      Available actions: start, stop, status, restart, queue, test, test-sendmail"

# Prevent make from trying to build targets for additional arguments
%:
	@:
