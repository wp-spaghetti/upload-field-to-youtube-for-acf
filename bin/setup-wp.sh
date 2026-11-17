#!/usr/bin/env bash
#
# Provision the local WordPress environment for development.
#
# Runs INSIDE the wp-env "cli" container, from the project directory:
#
#   wokenv run cli --env-cwd=wp-content/plugins/<project-dir> -- bash bin/setup-wp.sh
#
# (`make setup` does it for you.) The script is idempotent: run it again after
# `composer update` or whenever the fixtures in tests/data change.
#
# Project-specific steps live in the "Project fixtures" section at the bottom;
# everything above it is generic and can be reused as is by other plugins or
# themes.

set -euo pipefail

PROJECT_DIR="$(pwd -P)"
WP_ROOT="$(wp eval 'echo untrailingslashit(ABSPATH);')"
WP_CONTENT_DIR="$(wp eval 'echo WP_CONTENT_DIR;')"

# Development plugins are installed by Composer inside the project
# (extra.installer-paths in composer.json), so that static analysis can read
# them on any machine. WordPress only sees them once they are linked into its
# own plugins directory.
DEV_PLUGINS_DIR="${PROJECT_DIR}/wp-content/plugins"

# Space-separated list of plugins to activate. Defaults to every development
# plugin installed by Composer.
DEV_PLUGINS="${DEV_PLUGINS:-}"

log() {
	printf '\n==> %s\n' "$*"
}

if [ ! -d "${DEV_PLUGINS_DIR}" ]; then
	echo "No development plugins found in ${DEV_PLUGINS_DIR}: run 'composer install' first." >&2
	exit 1
fi

log "Linking development plugins into ${WP_CONTENT_DIR}/plugins"
installed=()
for plugin_dir in "${DEV_PLUGINS_DIR}"/*/; do
	[ -d "${plugin_dir}" ] || continue
	name="$(basename "${plugin_dir}")"
	ln -sfn "${plugin_dir%/}" "${WP_CONTENT_DIR}/plugins/${name}"
	installed+=("${name}")
	echo "  ${name}"
done

if [ -z "${DEV_PLUGINS}" ]; then
	DEV_PLUGINS="${installed[*]}"
fi

log "Activating development plugins"
# shellcheck disable=SC2086
wp plugin activate ${DEV_PLUGINS}

# ------------------------------------------------------------------------------
# Project fixtures
# ------------------------------------------------------------------------------

# WP-CFM reads its bundles from wp-content/config.
if [ -d "${PROJECT_DIR}/tests/data/wp-cfm" ] && wp plugin is-active wp-cfm; then
	log "Pulling WP-CFM bundles"
	ln -sfn "${PROJECT_DIR}/tests/data/wp-cfm" "${WP_CONTENT_DIR}/config"
	for file in "${PROJECT_DIR}"/tests/data/wp-cfm/*.json; do
		[ -f "${file}" ] || continue
		wp config pull "$(basename "${file}" .json)"
	done
fi

# The `wp acf` command is provided by hoppinger/advanced-custom-fields-wpcli.
if [ -d "${PROJECT_DIR}/tests/data/acf" ] && wp plugin is-active advanced-custom-fields-wpcli; then
	log "Importing ACF field groups"
	wp acf clean
	for file in "${PROJECT_DIR}"/tests/data/acf/*.json; do
		[ -f "${file}" ] || continue
		wp acf import --json_file="${file}"
	done
fi

log "Flushing rewrite rules"
wp rewrite flush

log "Done: WordPress is ready in ${WP_ROOT}"
