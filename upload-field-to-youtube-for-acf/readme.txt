=== Upload Field to YouTube for ACF ===
Contributors: Frugan
Tags: acf, fields, upload, video, youtube
Stable tag: 0.6.2
Requires Plugins: advanced-custom-fields
Requires at least: 5.6
Tested up to: 6.8
Requires PHP: 8.0
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html
Donate link: https://buymeacoff.ee/frugan

Upload Field to YouTube for ACF

== Description ==

**Upload Field to YouTube for ACF** is a WordPress plugin that allows you to upload videos directly to YouTube via API from the WordPress admin area and/or select existing videos on your YouTube channel based on playlists. It is particularly useful for managing "unlisted" videos that may be associated with Custom Post Types (CPT).

To use this plugin, you need to configure Google oAuth credentials so the plugin can authenticate with the user's YouTube channel.

= Requirements =

@see https://github.com/wp-spaghetti/upload-field-to-youtube-for-acf?tab=readme-ov-file#requirements

== Installation ==

@see https://github.com/wp-spaghetti/upload-field-to-youtube-for-acf?tab=readme-ov-file#installation

== External Services ==

This plugin connects to the **YouTube Data API v3** provided by Google LLC to upload and manage videos on YouTube.

= What data is sent =

* Video files selected by the user through the ACF field
* Video metadata (title, description, tags, privacy settings, category)
* OAuth authentication tokens for YouTube channel access
* User's YouTube channel information

= When data is sent =

* During video upload operations initiated by the user
* When retrieving video information from YouTube playlists
* During OAuth authentication and token refresh processes
* When fetching playlist data from the user's YouTube channel

= Service Information =

* **Service Provider:** Google LLC
* **API Endpoint:** https://www.googleapis.com/upload/youtube/v3/
* **Terms of Service:** https://developers.google.com/youtube/terms/api-services-terms-of-service
* **Privacy Policy:** https://policies.google.com/privacy

This information is provided for legal compliance and transparency. Users should review Google's terms and privacy policy before using this plugin to upload content to YouTube.

== Changelog ==

@see https://github.com/wp-spaghetti/upload-field-to-youtube-for-acf/blob/main/CHANGELOG.md

= Links =
* [Github](https://github.com/wp-spaghetti/upload-field-to-youtube-for-acf)
* [Support](https://github.com/wp-spaghetti/upload-field-to-youtube-for-acf/issues)
