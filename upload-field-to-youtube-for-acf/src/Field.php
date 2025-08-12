<?php

declare(strict_types=1);

/*
 * This file is part of the WordPress plugin "Upload Field to YouTube for ACF".
 *
 * (ɔ) Frugan <dev@frugan.it>
 *
 * This source file is subject to the GNU GPLv3 or later license that is bundled
 * with this source code in the file LICENSE.
 */

namespace FruganUFTYFACF;

use Google\Client;
use Google\Service\Oauth2;
use Google\Service\YouTube;
use Inpsyde\Wonolog\Configurator;

if (!\defined('ABSPATH')) {
    exit;
}

/**
 * Class Field.
 */
class Field extends \acf_field
{
    /**
     * Field type title.
     */
    public string $title;

    /**
     * Controls field type visibilty in REST requests.
     *
     * @var bool
     */
    public $show_in_rest = true;

    /**
     * Environment values relating to the theme or plugin.
     *
     * @var array plugin or theme context such as 'url' and 'version'
     */
    private array $env;

    private ?Client $client = null;

    private null|array|bool $access_token = null;

    /**
     * Constructor.
     */
    public function __construct()
    {
        /*
         * Field type reference used in PHP and JS code.
         *
         * No spaces. Underscores allowed.
         */
        $this->name = FRUGAN_UFTYFACF_NAME_UNDERSCORE;

        $this->title = __('Upload Field to YouTube for ACF', 'upload-field-to-youtube-for-acf');

        /*
         * Field type label.
         *
         * For public-facing UI. May contain spaces.
         */
        $this->label = __('YouTube Uploader', 'upload-field-to-youtube-for-acf');

        // The category the field appears within in the field type picker.
        $this->category = 'content'; // basic | content | choice | relational | jquery | layout | CUSTOM GROUP NAME

        /*
         * Field type Description.
         *
         * For field descriptions. May contain spaces.
         */
        $this->description = __('Upload Field to YouTube for ACF is a WordPress plugin that allows you to upload videos directly to YouTube via API from the WordPress admin area and/or select existing videos on your YouTube channel based on playlists.', 'upload-field-to-youtube-for-acf');

        /*
         * Field type Doc URL.
         *
         * For linking to a documentation page. Displayed in the field picker modal.
         */
        $this->doc_url = 'https://github.com/frugan-dev/upload-field-to-youtube-for-acf';

        /*
         * Field type Tutorial URL.
         *
         * For linking to a tutorial resource. Displayed in the field picker modal.
         */
        $this->tutorial_url = 'https://github.com/frugan-dev/upload-field-to-youtube-for-acf';

        // Defaults for your custom user-facing settings for this field type.
        $this->defaults = [
            'category_id' => 22, // People & Blogs
            'tags' => !empty($_SERVER['HTTP_HOST']) ? str_replace('www.', '', wp_unslash($_SERVER['HTTP_HOST'])) : '',
            'privacy_status' => 'unlisted',
            'made_for_kids' => false,
            'allow_upload' => true,
            'allow_select' => true,
            'api_update_on_post_update' => true,
            'api_delete_on_post_delete' => false,
        ];

        /*
         * Strings used in JavaScript code.
         *
         * Allows JS strings to be translated in PHP and loaded in JS via:
         *
         * ```js
         * const errorMessage = acf._e(this.field.data('type'), "error");
         * ```
         */
        $this->l10n = [
            'before_uploading' => __('Before uploading your video, make sure you:', 'upload-field-to-youtube-for-acf'),
            // translators: %s: Title
            'enter_title' => \sprintf(__('Enter a "%1$s"', 'upload-field-to-youtube-for-acf'), __('Title', 'upload-field-to-youtube-for-acf')),
            // translators: %s: Description
            'enter_description' => \sprintf(__('Enter a "%1$s"', 'upload-field-to-youtube-for-acf'), __('Description', 'upload-field-to-youtube-for-acf')),
            // translators: %s: Video file
            'select_video_file' => \sprintf(__('Select a "%1$s"', 'upload-field-to-youtube-for-acf'), __('Video file', 'upload-field-to-youtube-for-acf')),
            'preparing_upload' => __('Preparing to upload your file', 'upload-field-to-youtube-for-acf'),
            'loading' => __('Loading', 'upload-field-to-youtube-for-acf'),
            'wait_please' => __('Wait please', 'upload-field-to-youtube-for-acf'),
            'video_uploaded_successfully' => __('Video uploaded successfully.', 'upload-field-to-youtube-for-acf'),
            'error_while_uploading' => __('Error while uploading.', 'upload-field-to-youtube-for-acf'),
            'network_error_while_uploading' => __('Network error while uploading.', 'upload-field-to-youtube-for-acf'),
            'following_error' => __('The following error occurred:', 'upload-field-to-youtube-for-acf'),
            'recommended_save_post' => __('It is recommended to save the post by clicking the "Publish" button.', 'upload-field-to-youtube-for-acf'),
            'attention' => __('Attention', 'upload-field-to-youtube-for-acf'),
            'technical_problem' => __('There was a technical problem, please try again later.', 'upload-field-to-youtube-for-acf'),
            'select' => __('select', 'upload-field-to-youtube-for-acf'),
        ];

        $this->env = [
            'version' => FRUGAN_UFTYFACF_VERSION,
            'url' => FRUGAN_UFTYFACF_URL,
            'path' => FRUGAN_UFTYFACF_PATH,
            'debug' => WP_DEBUG,
            'locale' => get_locale(),
            'cache_busting' => \defined('FRUGAN_UFTYFACF_CACHE_BUSTING_ENABLED') && !empty(FRUGAN_UFTYFACF_CACHE_BUSTING_ENABLED) && !is_numeric(FRUGAN_UFTYFACF_CACHE_BUSTING_ENABLED) && filter_var(FRUGAN_UFTYFACF_CACHE_BUSTING_ENABLED, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ? true : false,
        ];

        /*
         * Field type preview image.
         *
         * A preview image for the field type in the picker modal.
         */
        // $this->preview_image = $this->env['url'] . '/asset/img/preview-custom.png';

        parent::__construct();

        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_init', [$this, 'admin_init']);
        add_action('admin_notices', [$this, 'admin_notices']);
        add_action('before_delete_post', [$this, 'before_delete_post']);
        add_action('wp_ajax_get_youtube_upload_url', [$this, 'wp_ajax_get_youtube_upload_url']);
        add_action('wp_ajax_save_youtube_video_id', [$this, 'wp_ajax_save_youtube_video_id']);
        add_action('wp_ajax_get_videos_by_playlist', [$this, 'wp_ajax_get_videos_by_playlist']);

        add_action($this->name.'__check_oauth_token', [$this, 'check_oauth_token']);

        // Migrate old options format if needed
        $this->migrate_options();
    }

    // https://developers.google.com/youtube/terms/branding-guidelines
    // https://www.youtube.com/yt/about/brand-resources/#logos-icons-colors
    // YouTube's ToS only allow custom icons; dashicons like `dashicons-video-alt3` are not allowed.
    public function admin_menu(): void
    {
        if (current_user_can('manage_options') || current_user_can('manage_'.$this->name)) {
            $capability = current_user_can('manage_options') ? 'manage_options' : 'manage_'.$this->name;

            add_options_page(
                $this->label,               // Page title
                $this->label,               // Menu title
                $capability,                // Capability
                $this->name,                // Menu slug
                [$this, 'settings_page'],   // Callback function
            );
        }
    }

    public function admin_init(): void
    {
        $role = get_role('administrator');
        if ($role) {
            $role->add_cap('manage_'.$this->name);
        }

        if (isset($_POST['action']) && 'logout' === $_POST['action']) {
            delete_option($this->name.'__access_token');

            add_action('admin_notices', static function (): void {
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p><strong>'.esc_html__('Successfully logged out from YouTube.', 'upload-field-to-youtube-for-acf').'</strong></p>';
                echo '</div>';
            });
        }

        // Handle settings save
        if (isset($_POST['action']) && $_POST['action'] === $this->name.'_save_settings') {
            if (!wp_verify_nonce($_POST[$this->name.'_settings_nonce'] ?? '', $this->name.'_save_settings')) {
                wp_die(__('Security check failed', 'upload-field-to-youtube-for-acf'));
            }

            if (!current_user_can('manage_options') && !current_user_can('manage_'.$this->name)) {
                wp_die(__('Insufficient permissions', 'upload-field-to-youtube-for-acf'));
            }

            // Allow extensions to save their settings
            do_action($this->name.'_save_settings', $_POST);

            $this->log('info', __('Plugin settings saved successfully', 'upload-field-to-youtube-for-acf'));

            add_action('admin_notices', static function (): void {
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p><strong>'.esc_html__('Settings saved successfully.', 'upload-field-to-youtube-for-acf').'</strong></p>';
                echo '</div>';
            });
        }
    }

    public function admin_notices(): void
    {
        if (isset($_GET['page']) && $this->name === $_GET['page']) {
            return;
        }

        $oauth = $this->handle_oauth();
        $status = $oauth['status'] ?? 'error';

        switch ($status) {
            case 'authorize':
                echo '<div class="notice notice-warning is-dismissible">';
                echo '<h3>'.esc_html($this->label).'</h3>';
                echo '<p><strong>'.esc_html($oauth['message']).'</strong></p>';
                echo '<p><a href="'.esc_url($oauth['auth_url']).'" class="button button-primary">'.esc_html__('Authorize App', 'upload-field-to-youtube-for-acf').'</a></p>';
                echo '</div>';

                break;

            case 'success':
                echo '<div class="notice notice-success is-dismissible">';
                echo '<h3>'.esc_html($this->label).'</h3>';
                echo '<p><strong>'.esc_html($oauth['message']).'</strong></p>';
                echo '</div>';

                break;

            case 'error':
                echo '<div class="notice notice-error">';
                echo '<h3>'.esc_html($this->label).'</h3>';
                echo '<p><strong>'.esc_html($oauth['message']).'</strong></p>';
                echo '</div>';

                break;
        }
    }

    /**
     * Settings to display when users configure a field of this type.
     *
     * These settings appear on the ACF "Edit Field Group" admin page when
     * setting up the field.
     *
     * @param array $field
     */
    public function render_field_settings($field): void
    {
        // Repeat for each setting you wish to display for this field type.
        acf_render_field_setting(
            $field,
            [
                'label' => __('Category ID', 'upload-field-to-youtube-for-acf'),
                'instructions' => implode(
                    '<br>'.PHP_EOL,
                    [
                        // translators: %s: category_id
                        \sprintf(__('Default: %1$s', 'upload-field-to-youtube-for-acf'), '<code>'.$this->defaults['category_id'].'</code>'),
                    ]
                ),
                'type' => 'number',
                'name' => 'category_id',
                'min' => 0,
                'step' => 1,
            ]
        );

        acf_render_field_setting(
            $field,
            [
                'label' => __('Tags', 'upload-field-to-youtube-for-acf'),
                'instructions' => implode(
                    '<br>'.PHP_EOL,
                    [
                        // translators: %s: tags
                        \sprintf(__('Default: %1$s', 'upload-field-to-youtube-for-acf'), '<code>'.$this->defaults['tags'].'</code>'),
                    ]
                ),
                'type' => 'text',
                'name' => 'tags',
            ]
        );

        acf_render_field_setting(
            $field,
            [
                'label' => __('Privacy status', 'upload-field-to-youtube-for-acf'),
                'instructions' => implode(
                    '<br>'.PHP_EOL,
                    [
                        // translators: %s: privacy_status
                        \sprintf(__('Default: %1$s', 'upload-field-to-youtube-for-acf'), '<code>'.$this->defaults['privacy_status'].'</code>'),
                    ]
                ),
                'type' => 'select',
                'name' => 'privacy_status',
                'choices' => [
                    'unlisted' => __('Unlisted', 'upload-field-to-youtube-for-acf'),
                    'private' => __('Private', 'upload-field-to-youtube-for-acf'),
                    'public' => __('Public', 'upload-field-to-youtube-for-acf'),
                ],
            ]
        );

        acf_render_field_setting(
            $field,
            [
                'label' => __('Made for kids', 'upload-field-to-youtube-for-acf'),
                'instructions' => implode(
                    '<br>'.PHP_EOL,
                    [
                        // translators: %s: made_for_kids
                        \sprintf(__('Default: %1$s', 'upload-field-to-youtube-for-acf'), '<code>'.($this->defaults['made_for_kids'] ? __('Yes', 'upload-field-to-youtube-for-acf') : __('No', 'upload-field-to-youtube-for-acf')).'</code>'),
                    ]
                ),
                'type' => 'true_false',
                'name' => 'made_for_kids',
            ]
        );

        acf_render_field_setting(
            $field,
            [
                'label' => __('Allow upload', 'upload-field-to-youtube-for-acf'),
                'instructions' => implode(
                    '<br>'.PHP_EOL,
                    [
                        // translators: %s: allow_upload
                        \sprintf(__('Default: %1$s', 'upload-field-to-youtube-for-acf'), '<code>'.($this->defaults['allow_upload'] ? __('Yes', 'upload-field-to-youtube-for-acf') : __('No', 'upload-field-to-youtube-for-acf')).'</code>'),
                    ]
                ),
                'type' => 'true_false',
                'name' => 'allow_upload',
            ]
        );

        acf_render_field_setting(
            $field,
            [
                'label' => __('Allow select', 'upload-field-to-youtube-for-acf'),
                'instructions' => implode(
                    '<br>'.PHP_EOL,
                    [
                        // translators: %s: allow_select
                        \sprintf(__('Default: %1$s', 'upload-field-to-youtube-for-acf'), '<code>'.($this->defaults['allow_select'] ? __('Yes', 'upload-field-to-youtube-for-acf') : __('No', 'upload-field-to-youtube-for-acf')).'</code>'),
                    ]
                ),
                'type' => 'true_false',
                'name' => 'allow_select',
            ]
        );

        acf_render_field_setting(
            $field,
            [
                'label' => __('Update YouTube video on post update', 'upload-field-to-youtube-for-acf'),
                'instructions' => implode(
                    '<br>'.PHP_EOL,
                    [
                        // translators: %s: api_update_on_post_update
                        \sprintf(__('Default: %1$s', 'upload-field-to-youtube-for-acf'), '<code>'.($this->defaults['api_update_on_post_update'] ? __('Yes', 'upload-field-to-youtube-for-acf') : __('No', 'upload-field-to-youtube-for-acf')).'</code>'),
                    ]
                ),
                'type' => 'true_false',
                'name' => 'api_update_on_post_update',
            ]
        );

        acf_render_field_setting(
            $field,
            [
                'label' => __('Delete YouTube video on post delete', 'upload-field-to-youtube-for-acf'),
                'instructions' => implode(
                    '<br>'.PHP_EOL,
                    [
                        // translators: %s: api_delete_on_post_delete
                        \sprintf(__('Default: %1$s', 'upload-field-to-youtube-for-acf'), '<code>'.($this->defaults['api_delete_on_post_delete'] ? __('Yes', 'upload-field-to-youtube-for-acf') : __('No', 'upload-field-to-youtube-for-acf')).'</code>'),
                    ]
                ),
                'type' => 'true_false',
                'name' => 'api_delete_on_post_delete',
            ]
        );

        // To render field settings on other tabs in ACF 6.0+:
        // https://www.advancedcustomfields.com/resources/adding-custom-settings-fields/#moving-field-setting
    }

    /**
     * HTML content to show when a publisher edits the field on the edit screen.
     *
     * @param array $field the field settings and values
     */
    public function render_field($field): void
    {
        ?>
		<div class="<?php echo esc_attr($field['key']); ?>__wrapper <?php echo esc_attr($field['type']); ?>__wrapper">
            <input type="hidden" name="<?php echo esc_attr($field['name']); ?>" value="<?php echo esc_attr($field['value']); ?>" class="<?php echo esc_attr($field['key']); ?>__hidden_value_input">

            <?php if (!empty($field['value'])) { ?>
        	    <p>
                    <a href="https://www.youtube.com/embed/<?php echo esc_attr($field['value']); ?>?rel=0&TB_iframe=true" class="thickbox">
                        <img src="https://img.youtube.com/vi/<?php echo esc_attr($field['value']); ?>/hqdefault.jpg" alt="">
                    </a>
                </p>
			<?php } elseif ($this->get_access_token()) { ?>
                <input type="hidden" name="mode" class="<?php echo esc_attr($field['key']); ?>__hidden_mode_input">

                <div class="<?php echo esc_attr($field['key']); ?>__tabs">
                    <ul>
                        <?php if (!empty($field['allow_upload'])) { ?>
                            <li>
                                <a href="#<?php echo esc_attr($field['key']); ?>__tab_1">
                                    <?php esc_html_e('Upload via API', 'upload-field-to-youtube-for-acf'); ?>
                                </a>
                            </li>
                        <?php }
                        ?>
                        <?php if (!empty($field['allow_select'])) { ?>
                            <li>
                                <a href="#<?php echo esc_attr($field['key']); ?>__tab_2">
                                    <?php esc_html_e('Select from channel', 'upload-field-to-youtube-for-acf'); ?>
                                </a>
                            </li>
                         <?php }
                        ?>
                    </ul>

                    <?php if (!empty($field['allow_upload'])) { ?>
                        <div id="<?php echo esc_attr($field['key']); ?>__tab_1">
                            <input type="file" class="<?php echo esc_attr($field['key']); ?>__file_input" name="<?php echo esc_attr($field['key']); ?>__file_input" lang="<?php echo esc_attr(get_locale()); ?>" accept="video/*">
                            <button type="button" class="<?php echo esc_attr($field['key']); ?>__button button button-secondary">
			                	<?php esc_html_e('Upload', 'upload-field-to-youtube-for-acf'); ?>
			                </button>
                        </div>
                    <?php }
                    ?>

                    <?php if (!empty($field['allow_select'])) { ?>
                        <div id="<?php echo esc_attr($field['key']); ?>__tab_2">
                            <?php
                                       $result = $this->get_playlists_by_privacy_status($field['privacy_status']);
                        if (!empty($result['items'])) { ?>
                                <p>
                                    <label for="<?php echo esc_attr($field['key']); ?>__playlist_select">
                                        <?php esc_html_e('Playlist', 'upload-field-to-youtube-for-acf'); ?>
                                    </label>
                                    <select class="<?php echo esc_attr($field['key']); ?>__playlist_select" name="<?php echo esc_attr($field['key']); ?>__playlist_select">
                                        <option value="">- <?php esc_html_e('select', 'upload-field-to-youtube-for-acf'); ?> -</option>
                                        <?php foreach ($result['items'] as $item) { ?>
                                            <option value="<?php echo esc_attr($item['id']); ?>">
                                                <?php echo esc_html($item['title']); ?> (<?php echo esc_html($item['id']); ?>)
                                            </option>
                                        <?php }
                                        ?>
                                    </select>
                                </p>

                                <p>
                                    <label for="<?php echo esc_attr($field['key']); ?>__video_select">
                                        <?php esc_html_e('Video', 'upload-field-to-youtube-for-acf'); ?>
                                    </label>
                                    <select class="<?php echo esc_attr($field['key']); ?>__video_select" name="<?php echo esc_attr($field['key']); ?>__video_select"></select>
                                </p>
                            <?php } else { ?>
                                <p><?php esc_html_e('No playlists available', 'upload-field-to-youtube-for-acf'); ?></p>
                            <?php }
                            ?>
                        </div>
<?php }
                    ?>
                </div>

                <p class="<?php echo esc_attr($field['key']); ?>__response <?php echo esc_attr($field['type']); ?>__response"></p>
			    <p class="<?php echo esc_attr($field['key']); ?>__spinner <?php echo esc_attr($field['type']); ?>__spinner">
			        <span class="spinner is-active"></span>
			    </p>
<?php } else { ?>
                <p><?php esc_html_e('You are not logged in', 'upload-field-to-youtube-for-acf'); ?></p>
            <?php }
?>
		</div>
<?php
    }

    /**
     * Enqueues CSS and JavaScript needed by HTML in the render_field() method.
     *
     * Callback for admin_enqueue_script.
     */
    public function input_admin_enqueue_scripts(): void
    {
        global $post;

        $version = $this->env['version'];
        $url = trailingslashit($this->env['url']);
        $path = trailingslashit($this->env['path']);
        $cache_busting = $this->env['cache_busting'];

        // https://wordpress.stackexchange.com/a/273996/99214
        // https://stackoverflow.com/a/59665364/3929620
        // No need to enqueue -core, because dependancies are set.
        wp_enqueue_script('jquery-ui-tabs');

        // WordPress does not register jQuery UI styles by default!
        // If you're going to submit your plugin to the wordpress.org repo, then you need to load the CSS locally
        // (see: https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/#8-plugins-may-not-send-executable-code-via-third-party-systems).
        // https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css
        wp_enqueue_style(
            'jquery-ui-css',
            $url.'asset/css/jquery-ui.min'.($cache_busting ? '.'.filemtime($path.'asset/css/jquery-ui.min.css') : '').'.css',
            ['acf-input'],
            $version
        );

        add_thickbox();

        wp_register_script(
            FRUGAN_UFTYFACF_NAME,
            $url.'asset/js/main'.($cache_busting ? '.'.filemtime($path.'asset/js/main.js') : '').'.js',
            ['acf-input'],
            $version,
            [
                'in_footer' => true,
            ]
        );

        // $object_name is the name of the variable which will contain the data.
        // Note that this should be unique to both the script and to the plugin or theme.
        // Thus, the value here should be properly prefixed with the slug or another unique value,
        // to prevent conflicts. However, as this is a JavaScript object name, it cannot contain dashes.
        // Use underscores or camelCasing.
        wp_localize_script(FRUGAN_UFTYFACF_NAME, $this->name.'_obj', [
            'postStatus' => $post ? $post->post_status : null,
        ]);

        wp_register_style(
            FRUGAN_UFTYFACF_NAME,
            $url.'asset/css/main'.($cache_busting ? '.'.filemtime($path.'asset/css/main.css') : '').'.css',
            ['acf-input'],
            $version
        );

        wp_enqueue_script(FRUGAN_UFTYFACF_NAME);
        wp_enqueue_style(FRUGAN_UFTYFACF_NAME);
    }

    public function validate_value($valid, mixed $value, $field, $input)
    {
        try {
            if (empty($value) && !empty($field['required'])) {
                // translators: %s: label
                throw new \InvalidArgumentException(\sprintf(__('Empty field "%1$s"', 'upload-field-to-youtube-for-acf'), $field['label']));
            }

            if (!empty($value)) {
                $api_update_on_post_update = (!empty($_POST['mode']) && 'upload' === $_POST['mode']) || !empty($field['api_update_on_post_update']);
                if ($api_update_on_post_update) {
                    $post_id = (int) ($_POST['post_ID'] ?? $_POST['post_id']);
                    $is_gutenberg = \function_exists('use_block_editor_for_post') && use_block_editor_for_post($post_id);
                    if ($is_gutenberg) {
                        $post = get_post($post_id);
                        $title = sanitize_text_field($post->post_title ?? '');
                        $excerpt = sanitize_text_field($post->post_excerpt ?? '');
                    } else {
                        $title = isset($_POST['post_title']) ? sanitize_text_field($_POST['post_title']) : '';
                        $excerpt = isset($_POST['excerpt']) ? sanitize_text_field($_POST['excerpt']) : '';
                    }

                    if (empty($title)) {
                        // translators: %s: Title
                        throw new \LengthException(\sprintf(__('Empty field "%1$s"', 'upload-field-to-youtube-for-acf'), __('Title', 'upload-field-to-youtube-for-acf')));
                    }

                    /*if (empty($excerpt)) {
                        // translators: %s: Excerpt
                        throw new \LengthException(\sprintf(__('Empty field "%1$s"', 'upload-field-to-youtube-for-acf'), __('Excerpt', 'upload-field-to-youtube-for-acf')));
                    }*/
                }

                $this->check_oauth_token();
                $googleServiceYouTube = new \Google_Service_YouTube($this->get_google_client());

                // Quota impact: A call to this method has a quota cost of 1 unit.
                $response = $googleServiceYouTube->videos->listVideos('snippet', ['id' => $value]);

                // translators: %s: value
                $this->log('debug', \sprintf(__('Video "%1$s" retrieved successfully', 'upload-field-to-youtube-for-acf'), $value), ['response' => $response]);

                if (empty($response->getItems())) {
                    throw new \Exception(__('This video is not associated with your authorized YouTube account', 'upload-field-to-youtube-for-acf'));
                }
            }
        } catch (\InvalidArgumentException|\LengthException $exception) {
            // FIXED - https://github.com/inpsyde/Wonolog/blob/2.x/src/HookLogFactory.php#L135
            // use `$exception->getMessage()` instead of `$exception`, because Wonolog
            // assigns the ERROR level to messages that are instances of Throwable
            $this->log('warning', $exception->getMessage());
            $valid = $exception->getMessage();
        } catch (\Google_Service_Exception $exception) {
            $this->log('error', $exception, ['response' => $response ?? null]);
            $error_data = json_decode($exception->getMessage(), true);
            $valid = $error_data['error']['message'] ?? $exception->getMessage();
        } catch (\Exception $exception) {
            $this->log('error', $exception);
            $valid = $exception->getMessage();
        }

        return $valid;
    }

    public function update_value(mixed $value, mixed $post_id, $field)
    {
        try {
            $api_update_on_post_update = (!empty($_POST['mode']) && 'upload' === $_POST['mode']) || !empty($field['api_update_on_post_update']);
            if (!$api_update_on_post_update) {
                return $value;
            }

            if (!empty($value)) {
                $this->check_oauth_token();
                $googleServiceYouTube = new \Google_Service_YouTube($this->get_google_client());

                // Quota impact: A call to this method has a quota cost of 1 unit.
                $response = $googleServiceYouTube->videos->listVideos('snippet', ['id' => $value]);
                $videoSnippet = $response->getItems()[0]->getSnippet();

                $googleServiceYouTubeVideoSnippet = new \Google_Service_YouTube_VideoSnippet();
                $googleServiceYouTubeVideoSnippet->setCategoryId($videoSnippet->getCategoryId() ?? $field['category_id']);
                $googleServiceYouTubeVideoSnippet->setTitle(get_the_title($post_id));

                if (!empty($excerpt = get_post_field('post_excerpt', $post_id))) {
                    $googleServiceYouTubeVideoSnippet->setDescription($excerpt);
                }

                $googleServiceYouTubeVideo = new \Google_Service_YouTube_Video();
                $googleServiceYouTubeVideo->setId($value);
                $googleServiceYouTubeVideo->setSnippet($googleServiceYouTubeVideoSnippet);

                // Quota impact: A call to this method has a quota cost of 50 units.
                $response = $googleServiceYouTube->videos->update('snippet', $googleServiceYouTubeVideo);

                // translators: %s: value
                $this->log('info', \sprintf(__('Video "%1$s" updated successfully', 'upload-field-to-youtube-for-acf'), $value), ['response' => $response]);
            }
        } catch (\Exception $exception) {
            $this->log('error', $exception, ['response' => $response ?? null]);
        }

        return $value;
    }

    public function before_delete_post(int $post_id): void
    {
        $fields = get_field_objects($post_id);

        if ($fields) {
            foreach ($fields as $field) {
                if (!empty($field['type']) && !empty($field['value']) && $field['type'] === $this->name) {
                    try {
                        if (empty($field['api_delete_on_post_delete'])) {
                            continue;
                        }

                        $this->check_oauth_token();
                        $googleServiceYouTube = new \Google_Service_YouTube($this->get_google_client());

                        // Quota impact: A call to this method has a quota cost of 50 units.
                        $response = $googleServiceYouTube->videos->delete($field['value']);

                        // translators: %s: value
                        $this->log('info', \sprintf(__('Video "%1$s" deleted successfully', 'upload-field-to-youtube-for-acf'), $field['value']), ['response' => $response]);
                    } catch (\Exception $exception) {
                        $this->log('error', $exception, ['response' => $response ?? null]);
                    }
                }
            }
        }
    }

    public function settings_page(): void
    {
        $oauth = $this->handle_oauth();
        $status = $oauth['status'] ?? 'error';

        echo '<div class="wrap">';
        echo '<h1>'.esc_html($this->label).'</h1>';

        switch ($status) {
            case 'authorize':
                echo '<div class="notice notice-warning is-dismissible">';
                echo '<h3>'.esc_html($this->label).'</h3>';
                echo '<p><strong>'.esc_html($oauth['message']).'</strong></p>';
                echo '<p><a href="'.esc_url($oauth['auth_url']).'" class="button button-primary">'.esc_html__('Authorize App', 'upload-field-to-youtube-for-acf').'</a></p>';
                echo '</div>';

                break;

            case 'authorized':
                echo '<div class="notice notice-success">';
                echo '<p><strong>'.esc_html($oauth['message']).'</strong></p>';
                echo '</div>';

                echo '<form method="post" action="">';
                echo '<input type="hidden" name="action" value="logout">';
                submit_button(__('Logout from YouTube', 'upload-field-to-youtube-for-acf'));
                echo '</form>';

                // Check if there are any extensions that want to add settings
                ob_start();
                do_action($this->name.'_'.__FUNCTION__.'_after', $oauth, $this);
                $output = ob_get_clean();

                if (!empty(trim($output))) {
                    echo '<hr>';
                    echo '<form method="post" action="">';
                    wp_nonce_field($this->name.'_save_settings', $this->name.'_settings_nonce');
                    echo '<input type="hidden" name="action" value="'.$this->name.'_save_settings">';

                    echo $output;

                    submit_button(__('Save Settings', 'upload-field-to-youtube-for-acf'));
                    echo '</form>';
                }

                break;

            case 'error':
                echo '<div class="notice notice-error">';
                echo '<p><strong>'.esc_html($oauth['message']).'</strong></p>';
                echo '</div>';

                break;
        }

        echo '</div>';
    }

    public function set_google_client(): void
    {
        if ($this->get_google_client() instanceof Client) {
            return;
        }

        $this->client = new Client();
        $this->client->setClientId(FRUGAN_UFTYFACF_GOOGLE_OAUTH_CLIENT_ID);
        $this->client->setClientSecret(FRUGAN_UFTYFACF_GOOGLE_OAUTH_CLIENT_SECRET);
        $this->client->setRedirectUri(admin_url());
        $this->client->addScope(Oauth2::USERINFO_EMAIL);
        $this->client->addScope(YouTube::YOUTUBE_FORCE_SSL);
        $this->client->addScope(YouTube::YOUTUBE_UPLOAD);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('select_account consent');

        if ($this->is_wonolog_active()) {
            // https://github.com/inpsyde/Wonolog/pull/55
            $this->client->setLogger(\Inpsyde\Wonolog\makeLogger());
        }
    }

    public function get_google_client()
    {
        return $this->client;
    }

    public function set_access_token(null|array|bool $token): void
    {
        $this->access_token = $token;
    }

    public function get_access_token(): null|array|bool
    {
        return $this->access_token;
    }

    public function check_oauth_token(): void
    {
        $this->set_access_token(get_option($this->name.'__access_token'));

        if (!empty($access_token = $this->get_access_token())) {
            $this->set_google_client();

            try {
                $this->client->setAccessToken($access_token);
            } catch (\Exception $exception) {
                // FIXED - https://github.com/inpsyde/Wonolog/blob/2.x/src/HookLogFactory.php#L135
                // use `$exception->getMessage()` instead of `$exception`, because Wonolog
                // assigns the ERROR level to messages that are instances of Throwable
                $this->log('warning', $exception->getMessage(), ['access_token' => $access_token]);
            }

            try {
                if ($this->client->isAccessTokenExpired()) {
                    if (!empty($refresh_token = $this->client->getRefreshToken())) {
                        $this->set_access_token($this->client->fetchAccessTokenWithRefreshToken($refresh_token));

                        $access_token = $this->get_access_token();
                        $this->client->setAccessToken($access_token);
                        update_option($this->name.'__access_token', $access_token);
                    } else {
                        throw new \UnexpectedValueException(\sprintf(__('Unable to retrieve "%1$s"', 'upload-field-to-youtube-for-acf'), 'refresh_token'));
                    }
                }
            } catch (\Exception $exception) {
                $this->log('error', $exception, ['access_token' => $access_token, 'refresh_token' => $refresh_token ?? null]);
                delete_option($this->name.'__access_token');
                $this->set_access_token(null);
            }
        }
    }

    public static function activate(): void
    {
        if (!wp_next_scheduled(FRUGAN_UFTYFACF_NAME_UNDERSCORE.'__check_oauth_token')) {
            wp_schedule_event(time(), 'hourly', FRUGAN_UFTYFACF_NAME_UNDERSCORE.'__check_oauth_token');
        }
    }

    public static function deactivate($network_deactivating = false): void
    {
        delete_option(FRUGAN_UFTYFACF_NAME_UNDERSCORE.'__access_token');

        $timestamp = wp_next_scheduled(FRUGAN_UFTYFACF_NAME_UNDERSCORE.'__check_oauth_token');
        wp_unschedule_event($timestamp, FRUGAN_UFTYFACF_NAME_UNDERSCORE.'__check_oauth_token');
    }

    public function handle_oauth(): array
    {
        // @phpstan-ignore-next-line
        if (!\defined('FRUGAN_UFTYFACF_GOOGLE_OAUTH_CLIENT_ID') || !\defined('FRUGAN_UFTYFACF_GOOGLE_OAUTH_CLIENT_SECRET') || empty(FRUGAN_UFTYFACF_GOOGLE_OAUTH_CLIENT_ID) || empty(FRUGAN_UFTYFACF_GOOGLE_OAUTH_CLIENT_SECRET)) {
            $data = [
                'status' => 'error',
                'message' => __('Missing or wrong OAuth credentials.', 'upload-field-to-youtube-for-acf'),
            ];

            $this->log('error', $data);

            return $data;
        }

        $this->check_oauth_token();

        if (empty($access_token = $this->get_access_token()) || !isset($access_token['access_token'])) {
            if (!current_user_can('manage_options') && !current_user_can('manage_'.$this->name)) {
                $data = [
                    'status' => 'error',
                    'message' => __('App not authorized, contact your system administrator.', 'upload-field-to-youtube-for-acf'),
                ];

                $this->log('error', $data);

                return $data;
            }

            $this->set_google_client();

            if (isset($_GET['code'])) {
                $this->client->authenticate(wp_unslash($_GET['code']));
                $this->set_access_token($this->client->getAccessToken());
                update_option($this->name.'__access_token', $this->get_access_token());

                $data = [
                    'status' => 'success',
                    'message' => __('App authorized! You can now upload videos to YouTube.', 'upload-field-to-youtube-for-acf'),
                ];

                $this->log('info', $data);

                return $data;
            }

            $auth_url = $this->client->createAuthUrl();

            return [
                'status' => 'authorize',
                'message' => __('Authorize the app to upload videos to YouTube:', 'upload-field-to-youtube-for-acf'),
                'auth_url' => $auth_url,
            ];
        }

        $googleServiceOauth2 = new \Google_Service_Oauth2($this->get_google_client());
        $user_info = $googleServiceOauth2->userinfo->get();

        return [
            'status' => 'authorized',
            // translators: %s: email
            'message' => \sprintf(__('App authorized! You are logged in as: %1$s', 'upload-field-to-youtube-for-acf'), $user_info->email),
        ];
    }

    public function get_playlists_by_privacy_status($privacy_status): array
    {
        $result = [];

        try {
            $this->check_oauth_token();
            $googleServiceYouTube = new \Google_Service_YouTube($this->get_google_client());
            $params = [
                'part' => 'snippet,status',
                'mine' => true,
                'maxResults' => 50,
            ];

            // Quota impact: A call to this method has a quota cost of 1 unit.
            $response = $googleServiceYouTube->playlists->listPlaylists('snippet,status', $params);

            $this->log('debug', __('Playlists retrieved successfully', 'upload-field-to-youtube-for-acf'), [
                'privacy_status' => $privacy_status,
                'response' => $response,
            ]);

            foreach ($response->getItems() as $item) {
                $playlistId = $item->getId();
                if (!isset($result[$playlistId])) {
                    $status = $item->getStatus();
                    if ($status && $status->getPrivacyStatus() === $privacy_status) {
                        $result[$playlistId] = [
                            'id' => $playlistId,
                            'title' => $item->getSnippet()->getTitle(),
                        ];
                    }
                }
            }

            if ($result) {
                $result = [
                    'items' => array_values($result),
                ];

                if (!empty($nextPageToken = $response->getNextPageToken())) {
                    $result['nextPageToken'] = $nextPageToken;
                }
            }
        } catch (\Exception $exception) {
            $this->log('error', $exception, ['response' => $response ?? null]);
        }

        return $result;
    }

    // https://stackoverflow.com/a/74402514/3929620
    // https://developers.google.com/youtube/v3/guides/using_resumable_upload_protocol
    // https://github.com/youtube/api-samples/blob/master/php/resumable_upload.php
    // https://github.com/googleapis/google-api-php-client
    // https://developers.google.com/youtube/v3/getting-started#quota
    // https://developers.google.com/youtube/v3/determine_quota_cost
    public function wp_ajax_get_youtube_upload_url(): void
    {
        try {
            $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
            if (empty($post_id)) {
                // translators: %s: post_id
                throw new \InvalidArgumentException(\sprintf(__('Empty field "%1$s"', 'upload-field-to-youtube-for-acf'), 'post_id'));
            }

            $field_key = isset($_POST['field_key']) ? sanitize_text_field($_POST['field_key']) : '';
            if (empty($field_key)) {
                // translators: %s: field_key
                throw new \InvalidArgumentException(\sprintf(__('Empty field "%1$s"', 'upload-field-to-youtube-for-acf'), 'field_key'));
            }

            // https://support.advancedcustomfields.com/forums/topic/get-choices-from-field-without-post_id/
            $field = get_field_object($field_key);
            if (!$field) {
                // translators: %s: field_key
                throw new \InvalidArgumentException(\sprintf(__('Unable to retrieve field "%1$s"', 'upload-field-to-youtube-for-acf'), $field_key));
            }

            $this->check_oauth_token();
            $googleServiceYouTube = new \Google_Service_YouTube($this->get_google_client());

            $googleServiceYouTubeVideoSnippet = new \Google_Service_YouTube_VideoSnippet();
            $googleServiceYouTubeVideoSnippet->setCategoryId($field['category_id']);
            $googleServiceYouTubeVideoSnippet->setTags(explode(',', $field['tags']));
            $googleServiceYouTubeVideoSnippet->setTitle(get_the_title($post_id));

            if (!empty($excerpt = get_post_field('post_excerpt', $post_id))) {
                $googleServiceYouTubeVideoSnippet->setDescription($excerpt);
            }

            $googleServiceYouTubeVideoStatus = new \Google_Service_YouTube_VideoStatus();
            // All videos uploaded via the videos.insert endpoint from unverified API projects
            // created after 28 July 2020 will be restricted to private viewing mode.
            // To lift this restriction, each API project must undergo an audit to verify compliance
            // with the Terms of Service. Please see the API Revision History for more details.
            $googleServiceYouTubeVideoStatus->setPrivacyStatus($field['privacy_status']);
            $googleServiceYouTubeVideoStatus->setSelfDeclaredMadeForKids((bool) $field['made_for_kids']); // or setMadeForKids()

            $googleServiceYouTubeVideo = new \Google_Service_YouTube_Video();
            $googleServiceYouTubeVideo->setSnippet($googleServiceYouTubeVideoSnippet);
            $googleServiceYouTubeVideo->setStatus($googleServiceYouTubeVideoStatus);

            // Quota impact: A call to this method has a quota cost of 1600 units.
            $response = $googleServiceYouTube->videos->insert('snippet,status', $googleServiceYouTubeVideo, [
                'uploadType' => 'resumable',
            ]);

            $uploadUrl = $response->getRequest()->getLastHeaders()['location'] ?? null;
            if ($uploadUrl) {
                // translators: %s: response
                $this->log('debug', \sprintf(__('Video "%1$s" retrieved successfully', 'upload-field-to-youtube-for-acf'), 'response'), ['response' => $response]);
                wp_send_json_success(['upload_url' => $uploadUrl]);
            } else {
                // translators: %s: location
                throw new \Exception(\sprintf(__('Unable to retrieve "%1$s" from response headers', 'upload-field-to-youtube-for-acf'), 'location'));
            }
        } catch (\Google_Service_Exception $exception) {
            $this->log('error', $exception, ['response' => $response ?? null]);
            $error_data = json_decode($exception->getMessage(), true);
            $error_message = $error_data['error']['message'] ?? $exception->getMessage();
            wp_send_json_error(['message' => $error_message]);
        } catch (\Exception $exception) {
            $this->log('error', $exception);
            wp_send_json_error(['message' => $exception->getMessage()]);
        }
    }

    public function wp_ajax_save_youtube_video_id(): void
    {
        try {
            $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
            if (empty($post_id)) {
                // translators: %s: post_id
                throw new \InvalidArgumentException(\sprintf(__('Empty field "%1$s"', 'upload-field-to-youtube-for-acf'), 'post_id'));
            }

            $video_id = isset($_POST['video_id']) ? sanitize_text_field($_POST['video_id']) : '';
            if (empty($video_id)) {
                // translators: %s: video_id
                throw new \InvalidArgumentException(\sprintf(__('Empty field "%1$s"', 'upload-field-to-youtube-for-acf'), 'video_id'));
            }

            if (!current_user_can('edit_post', $post_id)) {
                // translators: %s: video_id
                throw new \Exception(\sprintf(__('Insufficient permissions to save video "%1$s"', 'upload-field-to-youtube-for-acf'), $video_id));
            }

            $field_key = isset($_POST['field_key']) ? sanitize_text_field($_POST['field_key']) : '';
            if (empty($field_key)) {
                // translators: %s: field_key
                throw new \InvalidArgumentException(\sprintf(__('Empty field "%1$s"', 'upload-field-to-youtube-for-acf'), 'field_key'));
            }

            $result = update_field($field_key, $video_id, $post_id);
            if ($result) {
                wp_send_json_success();
            }

            // translators: %1$s: video_id, %2$s: field_key, %3$d: post_id
            throw new \UnexpectedValueException(\sprintf(__('Unable to save video "%1$s" to field "%2$s" in post ID "%3$d"', 'upload-field-to-youtube-for-acf'), $video_id, $field_key, $post_id));
        } catch (\Exception $exception) {
            $this->log('error', $exception);
            wp_send_json_error(['message' => $exception->getMessage()]);
        }
    }

    public function wp_ajax_get_videos_by_playlist(): void
    {
        try {
            $field_key = isset($_POST['field_key']) ? sanitize_text_field($_POST['field_key']) : '';
            if (empty($field_key)) {
                // translators: %s: field_key
                throw new \InvalidArgumentException(\sprintf(__('Empty field "%1$s"', 'upload-field-to-youtube-for-acf'), 'field_key'));
            }

            // https://support.advancedcustomfields.com/forums/topic/get-choices-from-field-without-post_id/
            $field = get_field_object($field_key);
            if (!$field) {
                // translators: %s: field_key
                throw new \InvalidArgumentException(\sprintf(__('Unable to retrieve field "%1$s"', 'upload-field-to-youtube-for-acf'), $field_key));
            }

            $playlist_id = isset($_POST['playlist_id']) ? sanitize_text_field($_POST['playlist_id']) : '';
            if (empty($playlist_id)) {
                // translators: %s: playlist_id
                throw new \InvalidArgumentException(\sprintf(__('Empty field "%1$s"', 'upload-field-to-youtube-for-acf'), 'playlist_id'));
            }

            $this->check_oauth_token();
            $googleServiceYouTube = new \Google_Service_YouTube($this->get_google_client());
            $params = [
                'playlistId' => $playlist_id,
                'maxResults' => 50,
            ];

            // Quota impact: A call to this method has a quota cost of 1 unit.
            $response = $googleServiceYouTube->playlistItems->listPlaylistItems('snippet,status', $params);

            // translators: %s: playlist_id
            $this->log('debug', \sprintf(__('Videos retrieved successfully by playlist ID "%1$s"', 'upload-field-to-youtube-for-acf'), $playlist_id), [
                'privacy_status' => $field['privacy_status'],
                'response' => $response,
            ]);

            $result = [];
            foreach ($response->getItems() as $item) {
                $videoId = $item->getSnippet()->getResourceId()->getVideoId();
                if (!isset($result[$videoId])) {
                    $status = $item->getStatus();
                    if ($status && $status->getPrivacyStatus() === $field['privacy_status']) {
                        $result[$videoId] = [
                            'id' => $videoId,
                            'title' => $item->getSnippet()->getTitle(),
                        ];
                    }
                }
            }

            if ($result) {
                $result = [
                    'items' => array_values($result),
                ];

                if (!empty($nextPageToken = $response->getNextPageToken())) {
                    $result['nextPageToken'] = $nextPageToken;
                }

                wp_send_json_success($result);
            }

            // translators: %s: playlist_id
            throw new \UnexpectedValueException(\sprintf(__('Unable to retrieve videos by playlist ID "%1$s"', 'upload-field-to-youtube-for-acf'), $playlist_id));
        } catch (\UnexpectedValueException $exception) {
            // FIXED - https://github.com/inpsyde/Wonolog/blob/2.x/src/HookLogFactory.php#L135
            // use `$exception->getMessage()` instead of `$exception`, because Wonolog
            // assigns the ERROR level to messages that are instances of Throwable
            $this->log('warning', $exception->getMessage());
            wp_send_json_error(['message' => $exception->getMessage()]);
        } catch (\Exception $exception) {
            $this->log('error', $exception);
            wp_send_json_error(['message' => $exception->getMessage()]);
        }
    }

    public function log($level, $message, array $context = []): void
    {
        if ($this->is_wonolog_active()) {
            do_action('wonolog.log.'.$level, $message, $context);
        } else {
            if ($message instanceof \Throwable) {
                $message = $message->getMessage();
            } elseif (is_wp_error($message)) {
                $context['wp_error_data'] = $message->get_error_data();
                $message = $message->get_error_message();
            }

            if (\is_array($message)) {
                $message = 'Message: '.wp_json_encode($message);
            }

            if (!empty($context)) {
                $message .= ' | Context: '.wp_json_encode($context);
            }

            error_log($message);
        }
    }

    public function is_wonolog_active()
    {
        return \function_exists('did_action') && class_exists(Configurator::class) && \defined(Configurator::class.'::ACTION_SETUP') && did_action(Configurator::ACTION_SETUP);
    }

    /**
     * Migrate options from old hyphen format to underscore format if needed.
     */
    private function migrate_options(): void
    {
        // Define migrations: old_key => new_key
        $option_migrations = [
            FRUGAN_UFTYFACF_NAME.'__access_token' => $this->name.'__access_token',
            FRUGAN_UFTYFACF_NAME.'__activated' => $this->name.'__activated',
        ];

        // Migrate options
        foreach ($option_migrations as $old_key => $new_key) {
            $this->migrate_option($old_key, $new_key);
        }

        // Migrate scheduled events: old_hook => new_hook
        $hook_migrations = [
            FRUGAN_UFTYFACF_NAME.'__check_oauth_token' => $this->name.'__check_oauth_token',
        ];

        // Migrate scheduled events
        foreach ($hook_migrations as $old_hook => $new_hook) {
            $this->migrate_scheduled_event($old_hook, $new_hook);
        }
    }

    /**
     * Migrate a single option from old key to new key.
     */
    private function migrate_option(string $old_key, string $new_key): void
    {
        if ($old_key === $new_key) {
            return;
        }

        $old_value = get_option($old_key);
        if ($old_value && !get_option($new_key)) {
            update_option($new_key, $old_value);
            delete_option($old_key);
        }
    }

    /**
     * Migrate a scheduled event from old hook to new hook.
     */
    private function migrate_scheduled_event(string $old_hook, string $new_hook): void
    {
        if ($old_hook === $new_hook) {
            return;
        }

        $timestamp = wp_next_scheduled($old_hook);
        if ($timestamp && !wp_next_scheduled($new_hook)) {
            wp_unschedule_event($timestamp, $old_hook);
            wp_schedule_event(time(), 'hourly', $new_hook);
        }
    }
}
