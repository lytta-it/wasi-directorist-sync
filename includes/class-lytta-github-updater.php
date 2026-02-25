<?php
/**
 * Enables automatic plugin updates directly from a GitHub repository's Releases.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Lytta_GitHub_Updater
{

    private $file;
    private $plugin;
    private $basename;
    private $active;
    private $username;
    private $repository;
    private $authorize_token;
    private $github_response;

    public function __construct($file, $repo, $token = '')
    {
        $this->file = $file;
        $this->plugin = plugin_basename($this->file);
        $this->basename = plugin_basename($this->file);
        $this->active = is_plugin_active($this->basename);

        // e.g. lytta-it/wasi-directorist-sync
        $parts = explode('/', $repo);
        $this->username = isset($parts[0]) ? $parts[0] : '';
        $this->repository = isset($parts[1]) ? $parts[1] : '';

        $this->authorize_token = $token;

        add_filter('pre_set_site_transient_update_plugins', array($this, 'modify_transient'), 10, 1);
        add_filter('plugins_api', array($this, 'plugin_popup'), 10, 3);
        add_filter('upgrader_post_install', array($this, 'after_install'), 10, 3);
    }

    private function get_repository_info()
    {
        if (is_null($this->github_response)) {
            $request_uri = sprintf('https://api.github.com/repos/%s/%s/releases/latest', $this->username, $this->repository);

            $args = array();
            if ($this->authorize_token) {
                $args['headers']['Authorization'] = "token {$this->authorize_token}";
            }

            $response = json_decode(wp_remote_retrieve_body(wp_remote_get($request_uri, $args)), true);

            if (is_array($response)) {
                $this->github_response = $response;
            }
        }
    }

    public function modify_transient($transient)
    {
        if (property_exists($transient, 'checked')) {

            // Get local version
            $plugin_data = get_plugin_data($this->file);
            $local_version = $plugin_data['Version'];

            $this->get_repository_info();

            // Check if GitHub responded cleanly with a tag verison
            if (!empty($this->github_response) && isset($this->github_response['tag_name'])) {
                $github_version = ltrim($this->github_response['tag_name'], 'v');

                if (version_compare($github_version, $local_version, '>')) {
                    $plugin = array(
                        'url' => $this->plugin,
                        'slug' => current(explode('/', $this->plugin)),
                        'package' => $this->github_response['zipball_url'],
                        'new_version' => $github_version
                    );

                    $transient->response[$this->plugin] = (object)$plugin;
                }
            }
        }

        return $transient;
    }

    public function plugin_popup($result, $action, $args)
    {
        if (!empty($args->slug)) {
            if ($args->slug == current(explode('/', $this->plugin))) {

                $this->get_repository_info();

                if (!empty($this->github_response) && isset($this->github_response['tag_name'])) {
                    $plugin_data = get_plugin_data($this->file);

                    $plugin = array(
                        'name' => $plugin_data['Name'],
                        'slug' => $args->slug,
                        'version' => ltrim($this->github_response['tag_name'], 'v'),
                        'author' => $plugin_data['AuthorName'],
                        'author_profile' => $plugin_data['AuthorURI'],
                        'last_updated' => $this->github_response['published_at'],
                        'homepage' => $plugin_data['PluginURI'],
                        'short_description' => $plugin_data['Description'],
                        'sections' => array(
                            'Description' => $plugin_data['Description'],
                            'Updates' => $this->github_response['body'],
                        ),
                        'download_link' => $this->github_response['zipball_url']
                    );

                    return (object)$plugin;
                }
            }
        }
        return $result;
    }

    public function after_install($response, $hook_extra, $result)
    {
        global $wp_filesystem;

        $install_directory = plugin_dir_path($this->file);
        $wp_filesystem->move($result['destination'], $install_directory);
        $result['destination'] = $install_directory;

        if ($this->active) {
            activate_plugin($this->basename);
        }

        return $result;
    }
}
