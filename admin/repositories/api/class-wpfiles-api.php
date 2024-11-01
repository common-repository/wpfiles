<?php
/**
 * Class that communicate to WPFiles
 */
class Wp_Files_Api
{
    /**
     * API key.
     * @since 1.0.0
     * @var string
     */
    public $api_key = '';

    /**
     * API request instance.
     * @since 1.0.0
     * @var Request
     */
    protected $request;

    /**
     * Endpoint name.
     * @since 1.0.0
     * @var string
     */
    public $name = 'wpfiles';

    /**
     * Endpoint version.
     * @since 1.0.0
     * @var string
     */
    public $version = 'v1';

    /**
     * API constructor.
     * @since 1.0.0
     * @param string $key  API key.
     * @throws Exception  API Request exception.
     */
    public function __construct($key)
    {
        $this->api_key = $key;

        // The Request class needs these to make requests.
        if (empty($this->version) || empty($this->name)) {
            throw new Exception(__('API object require a version and name property', 'wpfiles'), 404);
        }

        $this->request = new Wp_Files_Request($this);
    }
    
    /**
     * Fetch api status
     * @since 1.0.0
     * @param bool $is  Exponential back off.
     * @return mixed|WP_Error
     */
    public function get_site_status($is = false)
    {
        $response = $this->request->post_request(
            'user/fetch/status',
            array(
                'api_key' => $this->api_key,
                'domain'  => $this->request->get_this_site(),
            ),
            $is
        );
        
        //Unauthorized
        if(wp_remote_retrieve_response_code($response) == 402) {
            Wp_Files_Settings::removeCredentials();
        }


        //Invalid domain
        if(wp_remote_retrieve_response_code($response) == 410) {
            $status = json_decode($response['body']);
            Wp_Files_Settings::removeCredentials();
            Wp_Files_Helper::addOrUpdateOption(WP_FILES_PREFIX . 'domain-mismatch', $status->data->domain);
        }
        
        return $response;
    }

    /**
     * Add newsletter email
     * @since 1.0.0
     * @param string $email  
     * @param bool $is Exponential back off
     * @return mixed|WP_Error
     */
    public function addNewsLetterEmail($email, $redirect, $is = false)
    {
        return $this->request->post_request(
            'user/add-newsletter-email',
            array(
                'email' => $email,
                'redirect' => $redirect,
            ),
            $is
        );
    }

    /**
     * Submit feedback
     * @since 1.0.0
     * @param string $email  
     * @param int $rating  
     * @param string $feedback  
     * @param string $name  
     * @param bool $is Exponential back off
     * @return mixed|WP_Error
     */
    public function submitFeedback($email, $rating, $feedback, $name, $type, $option, $is = false)
    {
        return $this->request->post_request(
            'user/feedback',
            array(
                'email' => $email,
                'rating' => $rating,
                'feedback' => $feedback,
                'name' => $name,
                'type' => $type,
                'option' => $option,
            ),
            $is
        );
    }

    /**
     * Purge website cache
     * @since 1.0.0
     * @param bool $is Exponential back off
     * @return mixed|WP_Error
     */
    public function purgeCache($is = false)
    {
        return $this->request->post_request(
            'website/purge-cache',
            array(
                'domain'  => $this->request->get_this_site(),
            ),
            $is
        );
    }

    /**
     * Save usage tracking
     * @since 1.0.0
     * @param array $data  
     * @param string $domain  
     * @param bool $is Exponential back off
     * @return mixed|WP_Error
     */
    public function saveUsageTracking($data, $domain, $is = false)
    {
        return $this->request->put(
            'user/save-usage-tracking',
            array(
                'data' => $data,
                'domain' => $domain,
            ),
            $is
        );
    }

    /**
     * Fetch cdn status
     * @since 1.0.0
     * @param bool $is Exponential back off
     * @return mixed|WP_Error
     */
    public function fetch_cdn_status($is = false)
    {
        $response = $this->request->post_request(
            'user/fetch/cdn/status',
            array(
                'api_key' => $this->api_key,
                'domain'  => $this->request->get_this_site(),
            ),
            $is
        );

        //Unauthorized
        if(wp_remote_retrieve_response_code($response) == 402) {
            Wp_Files_Settings::removeCredentials();
        }

        //Invalid domain
        if(wp_remote_retrieve_response_code($response) == 410) {
            $status = json_decode($response['body']);
            Wp_Files_Settings::removeCredentials();
            Wp_Files_Helper::addOrUpdateOption(WP_FILES_PREFIX . 'domain-mismatch', $status->data->domain);
        }

        return $response;
    }

    /**
     * Return valid download plugin url
     * @since 1.0.0
     * @param  mixed $domain
     * @param  mixed $token
     * @return void
     */
    public function downloadPluginUrl($domain, $token)
    {
        return $this->request->getRequestUrl(
            $token ? 'website/download-plugin/'.$token : 'website/download-plugin',
            array(
                'domain' => $domain,
            )
        );
    }
}
