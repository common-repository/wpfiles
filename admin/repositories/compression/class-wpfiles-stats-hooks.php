<?php 
trait Wp_Files_Stats_Hooks {
    
    /**
     * Compression/Watermark stats related hooks
     * @since 1.0.0
     * @var void
     */
    public function hooks() {
    
        add_action(
            'wp_files_png_jpg_converted',
            function () {
                return $this->getSavings('pngjpg');
            }
        );  

        add_action(
            'wp_files_image_resized',
            function () {
                return $this->getSavings('resize');
            }
        );

        add_action('delete_attachment', array($this, 'updateLists'), 12);

        add_action('add_attachment', array($this, 'addToMediaAttachmentsList'));
    }

    /**
     * Get savings
     * @since 1.0.0
     * @param string $type          
     * @param bool $forceToUpdate  
     * @param bool $format        
     * @param bool $returnCount
     * @return int|array
     */
    public function getSavings($type, $forceToUpdate = true, $format = false, $returnCount = false)
    {
        $key = WP_FILES_PREFIX . $type . '_savings';

        $keyCount = WP_FILES_PREFIX . 'resize_count';

        if (!$forceToUpdate) {

            $savings = wp_cache_get($key, WP_FILES_CACHE_PREFIX);

            if (!$returnCount && $savings) {
                return $savings;
            }

            $count = wp_cache_get($keyCount, WP_FILES_CACHE_PREFIX);

            if ($returnCount && false !== $count) {
                return $count;
            }

        }

        $count      = 0;

        $offset     = 0;

        $queryNext = true;

        $savings = array(
            'resize' => array(
                'size_before' => 0,
                'size_after'  => 0,
                'bytes'       => 0,
            ),
            'pngjpg' => array(
                'size_before' => 0,
                'size_after'  => 0,
                'bytes'       => 0,
            ),
        );

        global $wpdb;

        while ($queryNext) {

            $queryData = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key=%s LIMIT %d, %d",
                    $key,
                    $offset,
                    $this->queryLimit
                )
            ); 

            if (empty($queryData)) {
                break;
            }

            foreach ($queryData as $data) {

                if (!empty($this->recompress_ids) && in_array($data->post_id, $this->recompress_ids, true)) {
                    continue;
                }

                $count++;

                if (empty($data)) {
                    continue;
                }

                $meta = maybe_unserialize($data->meta_value);

                if (!empty($meta) && !empty($meta['bytes'])) {
                    $savings['resize']['size_before'] += $meta['size_before'];
                    $savings['resize']['size_after']  += $meta['size_after'];
                    $savings['resize']['bytes']       += $meta['bytes'];
                }

                if (is_array($meta)) {
                    foreach ($meta as $size) {
                        $savings['pngjpg']['size_before'] += isset($size['size_before']) ? $size['size_before'] : 0;
                        $savings['pngjpg']['size_after']  += isset($size['size_after']) ? $size['size_after'] : 0;
                        $savings['pngjpg']['bytes']       += isset($size['bytes']) ? $size['bytes'] : 0;
                    }
                }
            }

            $offset += $this->queryLimit;

            $queryNext = $this->count_of_attachments_for_compression > $offset;
        }

        if ($format) {
            $savings[$type]['bytes'] = size_format($savings[$type]['bytes'], 1);
        }

        wp_cache_set(WP_FILES_PREFIX . 'resize_savings', $savings['resize'], WP_FILES_CACHE_PREFIX);

        wp_cache_set(WP_FILES_PREFIX . 'pngjpg_savings', $savings['pngjpg'], WP_FILES_CACHE_PREFIX);

        wp_cache_set($keyCount, $count, WP_FILES_CACHE_PREFIX);

        return $returnCount ? $count : $savings[$type];
    }

    /**
     * Adds the ID of the compressed image to the media_attachments list.
     * @since 1.0.0
     * @param int $attachmentID 
     */
    public function addToMediaAttachmentsList($attachmentID)
    {
        $posts = wp_cache_get('media_attachments', WP_FILES_CACHE_PREFIX);

        if (!$posts) {
            return;
        }

        $mimeType = get_post_mime_type($attachmentID);

        $id = (string) $attachmentID;

        if ($mimeType && in_array($mimeType, Wp_Files_Compression::$mimeTypes, true) && !in_array($id, $posts, true)) {
            $posts[] = $id;
            wp_cache_set('media_attachments', $posts, WP_FILES_CACHE_PREFIX);
        }
    }

    /**
     * Updates lists
     * @since 1.0.0
     * @param integer $id
     */
    public function updateLists($id)
    {
        $this->removeFromMediaAttachmentsList($id);
        self::removeFromCompressedList($id);
        self::removeFromWatermarkedList($id);
    }

    /**
     * Update api status
     * @since 1.0.0
     * @return array|json
     */
    public function update_api_status($return = false)
    {
        $response = array();

        $settings = (array) Wp_Files_Settings::loadSettings();

        $api = new Wp_Files_Api($settings['api_key']);

        $response = $this->processSiteStatus($api->get_site_status(true));

        if (is_wp_error($response)) {
            $code = is_numeric($response->get_error_code()) ? $response->get_error_code() : null;
            wp_send_json_error(
                array(
                    'message' => $response->get_error_message(),
                ),
                $code
            );
        }

        if ($response && $response->success) {

            Wp_Files_Settings::saveSiteStatus($settings, $response->data);
            
            //Disable cdn module if suspended or inactive from WPFiles account
            if($settings['cdn']) {
                if((int)$response->data->website->cdn == 0 || (int)$response->data->cdn_active  == 0) {
                    Wp_Files_Settings::updateSetting(WP_FILES_PREFIX .'cdn', 0);
                }
            }

            //Account upgradtion [First time]
            if(get_option(WP_FILES_PREFIX . 'account-connection-timestamp') && !get_option(WP_FILES_PREFIX . 'subscription-upgrade-timestamp') && get_option(WP_FILES_PREFIX . 'account-plan-id') && $response->data->plan->id > get_option(WP_FILES_PREFIX . 'account-plan-id')) {
                Wp_Files_Helper::addOrUpdateOption(WP_FILES_PREFIX . 'subscription-upgrade-timestamp', time());
            }

            //Account downgration [First time]
            if(get_option(WP_FILES_PREFIX . 'account-connection-timestamp') && !get_option(WP_FILES_PREFIX . 'subscription-downgrade-timestamp') && get_option(WP_FILES_PREFIX . 'account-plan-id') && $response->data->plan->id < get_option(WP_FILES_PREFIX . 'account-plan-id')) {
                Wp_Files_Helper::addOrUpdateOption(WP_FILES_PREFIX . 'subscription-downgrade-timestamp', time());
            }

            //Account connection [First time]
            if(!get_option(WP_FILES_PREFIX . 'account-connection-timestamp')) {
                Wp_Files_Helper::addOrUpdateOption(WP_FILES_PREFIX . 'account-connection-timestamp', time());
            }
            
            //Plan 
            Wp_Files_Helper::addOrUpdateOption(WP_FILES_PREFIX . 'account-plan-id', $response->data->plan->id);

            if (!wp_doing_cron() && $return) {
                wp_send_json_success([
                    'message' => __("API status updated successfully", 'wpfiles')
                ]);
            } else if(!wp_doing_cron()) {
                return $response;
            }
            
        } else {
            if (!wp_doing_cron() && $return) {
                wp_send_json_error([
                    'message' => $response->error
                ]);
            } else if(!wp_doing_cron()) {
                return $response;
            }
        }
    }
}