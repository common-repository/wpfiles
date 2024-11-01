<?php
/**
 * API request class for communication using HTTP Methods
 * @since 1.0.0
*/

class Wp_Files_Request
{
    /**
     * Request max timeout.
     * @since 1.0.0
     * @var int
    */
    private $timeout = 15;

    /**
     * API service.
     * @since 1.0.0
     * @var null|API
    */
    private $service = null;

    /**
     * POST arguments.
     * @since 1.0.0
     * @var array
    */
    private $post_arguments = array();

    /**
     * GET arguments.
     * @since 1.0.0
     * @var array
    */
    private $get_arguments = array();

    /**
     * Header arguments
     * @since 1.0.0
     * @var array
    */
    private $request_headers = array();

    /**
     * Request constructor.
     * @since 1.0.0
     * @param API $service  API service.
     * @throws Exception Init exception.
    */
    public function __construct($service)
    {
        if (!$service instanceof Wp_Files_Api) {
            throw new Exception(__('Invalid API service.', 'wpfiles'), 404);
        }

        $this->service = $service;
    }

    /**
     * Get the current site URL.
     * The network_site_url() of the WP installation. (Or network_home_url if not passing an API key).
     * @since 1.0.0
     * @return string
    */
    public function get_this_site()
    {
        return network_site_url();
    }

    /**
     * Argument for POST requests.
     * @since 1.0.0
     * @param string $name   Argument name.
     * @param string $value  Argument value.
    */
    public function add_post_argument($name, $value)
    {
        $this->post_arguments[$name] = $value;
    }

    /**
     * Set request timeout.
     * @since 1.0.0
     * @param int $timeout Request timeout.
    */
    public function set_timeout($timeout)
    {
        $this->timeout = $timeout;
    }

    /**
     * Argument for GET requests.
     * @since 1.0.0
     * @param string $name   Argument name.
     * @param string $value  Argument value.
    */
    public function add_header_argument($name, $value)
    {
        $this->request_headers[$name] = $value;
    }

    /**
     * Argument for GET requests.
     * @since 1.0.0
     * @param string $name   Argument name.
     * @param string $value  Argument value.
    */
    public function add_get_argument($name, $value)
    {
        $this->get_arguments[$name] = $value;
    }

    /**
     * POST API call.
     * @since 1.0.0
     * @param string $path Endpoint route.
     * @param array  $data Data array.
     * @param bool   $is Exponential backoff.
     * @return mixed|WP_Error
    */
    public function post_request($path, $data = array(), $is = false)
    {
        try {
            $result = $this->final_request($path, $data, 'post', $is);
            return $result;
        } catch (Exception $e) {
            return new WP_Error($e->getCode(), $e->getMessage());
        }
    }

    /**
     * PUT API call.
     * @since 1.0.0
     * @param string $path Endpoint route.
     * @param array  $data Data array.
     * @param bool   $is Exponential backoff.
     *
     * @return mixed|WP_Error
    */
    public function put($path, $data = array(), $is = false)
    {
        try {
            $result = $this->final_request($path, $data, 'put', $is);
            return $result;
        } catch (Exception $e) {
            return new WP_Error($e->getCode(), $e->getMessage());
        }
    }

    /**
     * GET API call.
     * @since 1.0.0
     * @param string $path Endpoint route.
     * @param array  $data Data array.
     * @param bool $is Exponential backoff.
     * @return mixed|WP_Error
    */
    public function get($path, $data = array(), $is = false)
    {
        try {
            if(count($data) > 0) {
                foreach ($data as $key => $value) {
                    $this->add_get_argument($key, $value);
                }
            }
            $result = $this->final_request($path, $data, 'get', $is);
            return $result;
        } catch (Exception $e) {
            return new WP_Error($e->getCode(), $e->getMessage());
        }
    }

    /**
     * Make a request url.
     * @since 1.0.0
     * @param string $path Endpoint route.
     * @param array  $data Data array.
     * @return mixed
    */
    public function getRequestUrl($path, $data = array())
    {
        if(count($data) > 0) {
            foreach ($data as $key => $value) {
                $this->add_get_argument($key, $value);
            }
        }
        
        $url = $this->get_api_url($path);

        $url = add_query_arg($this->get_arguments, $url);

        return $url;
    }

    /**
     * API endpoint
     * @since 1.0.0
     * @param string $path Endpoint path.
     * @return string
    */
    private function get_api_url($path = '')
    {
        $url = WP_FILES_API_URL.'/api/' . $this->service->name . '/' . $this->service->version . '/';
        $url = trailingslashit($url . $path);
        return $url;
    }

    /**
     * HEAD API call.
     * @since 1.0.0
     * @param string $path Endpoint route.
     * @param array  $data Data array.
     * @return mixed|WP_Error
    */
    public function head($path, $data = array())
    {
        try {
            $result = $this->final_request($path, $data, 'head');
            return $result;
        } catch (Exception $e) {
            return new WP_Error($e->getCode(), $e->getMessage());
        }
    }

    /**
     * Authorization headers.
     * @since 1.0.0
     * @return void
    */
    private function authorization()
    {
        if (!empty($this->service->api_key)) {
            $this->add_header_argument('Authorization', $this->service->api_key);
            $this->add_header_argument('Authtoken', $this->service->api_key);
        }
    }

    /**
     * Final API request.
     * @since 1.0.0
     * @param string $path API endpoint route.
     * @param array  $data Data array.
     * @param string $method API method.
     * @param bool   $is Exponential backoff.
     * @return array|WP_Error
    */
    private function final_request($path, $data = array(), $method = 'post', $is = false)
    {
        $defaults = array(
            'time'  => time(),
            'fails' => 0,
        );

        $last_run = get_site_option(WP_FILES_PREFIX . 'last_run', $defaults);

        $backoff = min(pow(5, $last_run['fails']), HOUR_IN_SECONDS); // Exponential 5, 25, 125 and so on.

        if ($last_run['fails'] && $last_run['time'] > (time() - $backoff) && !$is) {
            $last_run['time'] = time();
            update_site_option(WP_FILES_PREFIX . 'last_run', $last_run);
            return new WP_Error('api-backoff-error', __('Declined due to API error exponential backoff.', 'wpfiles'));
        }

        $url = $this->get_api_url($path);

        $this->authorization();

        $url = add_query_arg($this->get_arguments, $url);
        
        if ('post' !== $method && 'put' !== $method && 'patch' !== $method && 'delete' !== $method) {
            $url = add_query_arg($data, $url);
        }

        $args = array(
            'headers'    => $this->request_headers,
            'user-agent' => WP_FILES_UA,
            'timeout'    => $this->timeout,
            'method'     => strtoupper($method),
        );

        if (!$args['timeout'] || 2 === $args['timeout']) {
            $args['blocking'] = false;
        }

        switch (strtolower($method)) {
            case 'patch':
            case 'delete':
            case 'post':
                if (is_array($data)) {
                    $args['body'] = array_merge($data, $this->post_arguments);
                } else {
                    $args['body'] = $data;
                }
                $response = wp_remote_post($url, $args);
                break;
            case 'put':
                if (is_array($data)) {
                    $args['body'] = array_merge($data, $this->post_arguments);
                } else {
                    $args['body'] = $data;
                }
                $response = wp_remote_request($url, $args);
                break;
            case 'head':
                $response = wp_remote_head($url, $args);
                break;
            case 'get':
                $response = wp_remote_get($url, $args);
                break;
            default:
                $response = wp_remote_request($url, $args);
                break;
        }

        $last_run['time'] = time();

        if ($is || (!is_wp_error($response) && 200 === wp_remote_retrieve_response_code($response))) {
            $last_run['fails'] = 0;
        } else {
            $last_run['fails'] = $last_run['fails'] + 1;
        }

        update_site_option(WP_FILES_PREFIX . 'last_run', $last_run);

        return $response;
    }

    /**
     * Make a PATCH API call.
     * @since 1.0.0
     * @param string $path Endpoint route.
     * @param array  $data Data array.
     * @return mixed|WP_Error
     */
    public function patch($path, $data = array())
    {
        try {
            $result = $this->final_request($path, $data, 'patch');
            return $result;
        } catch (Exception $e) {
            return new WP_Error($e->getCode(), $e->getMessage());
        }
    }

    /**
     * DELETE API call.
     * @since 1.0.0
     * @param string $path Endpoint route.
     * @param array  $data Data array.
     * @return mixed|WP_Error
     */
    public function delete($path, $data = array())
    {
        try {
            $result = $this->final_request($path, $data, 'delete');
            return $result;
        } catch (Exception $e) {
            return new WP_Error($e->getCode(), $e->getMessage());
        }
    }
}
