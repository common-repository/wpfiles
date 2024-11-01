<?php
/**
 * Class that contain every thing about watermark/compression stats
 */
class Wp_Files_Stats
{
    use Wp_Files_Stats_Hooks;

    /**
     * Stores the stats for all the images.
     * @since 1.0.0
     * @var array $stats
     */
    public $stats;

    /**
     * Compressed attachments
     * @var array $dir_compression_stats
     */
    public $dir_compression_stats;

    /**
     * Watermarked attachments
     * @since 1.0.0
     * @var array $dir_watermark_stats
     */
    public $dir_watermark_stats;

    /**
     * Mysql query limit
     * @since 1.0.0
     * @var int $queryLimit
     */
    private $queryLimit;

    /**
     * Set a limit to max number of rows in MySQL query. Default: 5000.
     * @var int $maxRows
     */
    private $maxRows;

    /**
     * Attachment IDs.
     * @since 1.0.0
     * @var array $attachments
     */
    public $attachments = array();

    /**
     * Compressed attachments
     * @since 1.0.0
     * @var array $compressed_attachments
     */
    public $compressed_attachments = array();

    /**
     * Watermarked attachments
     * @since 1.0.0
     * @var array $watermarked_attachments
     */
    public $watermarked_attachments = array();

    /**
     * Uncompressed attachments
     * @since 1.0.0
     * @var array $uncompressed_attachments
     */
    public $uncompressed_attachments = array();

    /**
     * Unwatermarked attachments
     * @since 1.0.0
     * @var array $unwatermarked_attachments
     */
    public $unwatermarked_attachments = array();

    /**
     * Skipped attachments
     * @since 1.0.0
     * @var array $skippedAttachments
     */
    public $skippedAttachments = array();

    /**
     * Total compressed attachments
     * @since 1.0.0
     * @var int $compressed_count
     */
    public $compressed_count = 0;

    /**
     * Total watermarked attachments
     * @since 1.0.0
     * @var int $watermarked_count
     */
    public $watermarked_count = 0;

    /**
     * Remaining compressed count
     * @since 1.0.0
     * @var int $remainingCompressionCount
     */
    public $remainingCompressionCount = 0;

    /**
     * Remaining watermark count
     * @since 1.0.0
     * @var int $remainingWatermarkCount
     */
    public $remainingWatermarkCount = 0;

    /**
     * Skipped images count
     * @since 1.0.0
     * @var int $skippedCount
     */
    public $skippedCount = 0;

    /**
     * Super compressed count.
     * @since 1.0.0
     * @var int $super_compressed
     */
    public $super_compressed = 0;

    /**
     * Total count of attachments for compressing.
     * @since 1.0.0
     * @var int $count_of_attachments_for_compression
     */
    public $count_of_attachments_for_compression = 0;

    /**
     * Total count of attachments for watermarking.
     * @since 1.0.0
     * @var int $count_of_attachments_for_watermark
     */
    public $count_of_attachments_for_watermark = 0;

    /**
     * Attachments that needs to be recompression
     * @since 1.0.0
     * @var array $recompress_ids
     */
    public $recompress_ids = array();

    /**
     * Attachments that needs to be rewatermarked.
     * @since 1.0.0
     * @var array $rewatermark_ids
     */
    public $rewatermark_ids = array();

    /**
     * settings
     * @since 1.0.0
     * @var object $settings
     */
    private $settings;

    /**
     * directory
     * @since 1.0.0
     * @var object $directory
     */
    private $directory;

    /**
     * Stats constructor.
     * @since 1.0.0
     */
    public function __construct($settings)
    {
        $this->settings = $settings;
    }

    /**
     * Hooks
     * @since 1.0.0
     */
    public function init()
    {
        $this->queryLimit = apply_filters('wp_files_query_limit', 3000);

        $this->maxRows    = apply_filters('wp_files_max_rows', 5000);

        $this->hooks();
    }

    /**
     * Setup global stats
     * @since 1.0.0
     * @param bool $forceUpdate 
     */
    public function setupGlobalStats($forceUpdate = false)
    {
        $this->directory = new Wp_Files_Directory($this->settings);

        // Set Attachment IDs, and total count.
        $this->attachments = $this->getMediaAttachments();

        /**********compression***********/

        // Set directory compression status.
        $this->dir_compression_stats = Wp_Files_Directory::shouldContinue() ? $this->directory->totalStats($forceUpdate) : array();

        // Set total count for compression.
        $this->count_of_attachments_for_compression = !empty($this->attachments) && is_array($this->attachments) ? count($this->attachments) : 0;

        $this->stats = $this->getGlobalStats($forceUpdate);

        // Get compressed attachments.
        if (empty($this->compressed_attachments)) {
            $this->compressed_attachments = $this->getCompressedAttachments($forceUpdate);
        }

        // Get super compressed images count.
        if (!$this->super_compressed) {
            $this->super_compressed = count($this->getSuperCompressedAttachments());
        }

        // Set pro savings.
        $this->setProSavings();

        // Get skipped attachments.

        $this->skippedAttachments = $this->skippedCount($forceUpdate);

        $this->skippedCount       = count($this->skippedAttachments);

        // Set compressed count.

        $this->compressed_count   = !empty($this->compressed_attachments) ? count($this->compressed_attachments) : 0;

        $this->remainingCompressionCount = $this->remainingCompressionCount();
        /**********compression***********/

        /**********watermark***********/

        // Set total count for compression.
        $this->count_of_attachments_for_watermark = !empty($this->attachments) && is_array($this->attachments) ? count($this->attachments) : 0;

        // Set directory watermark status.
        $this->dir_watermark_stats = Wp_Files_Directory::shouldContinue() ? $this->directory->totalStats($forceUpdate, 'watermark') : array();
        
        // Get watermarked attachments.
        if (empty($this->watermarked_attachments)) {
            $this->watermarked_attachments = $this->getWatermarkedAttachments($forceUpdate);
        }

        // Set watermarked count.
        $this->watermarked_count   = !empty($this->watermarked_attachments) ? count($this->watermarked_attachments) : 0;

        $this->remainingWatermarkCount = $this->remainingWatermarkCount();
        
        /**********watermark***********/
    }

    /**
     * Get the media attachment IDs.
     * @since 1.0.0
     * @param bool $forceUpdate 
     * @return array
     */
    public function getMediaAttachments($forceUpdate = false)
    {
        if (!$forceUpdate) {
            $posts = wp_cache_get('media_attachments', WP_FILES_CACHE_PREFIX);
            if ($posts) {
                return $posts;
            }
        }

        do_action('wp_files_remove_filters');

        $mimeType = implode("', '", Wp_Files_Compression::$mimeTypes);

        global $wpdb;

        $posts = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND (post_status = 'inherit' OR post_status = 'private') AND post_mime_type IN ('$mimeType')"); // Db call ok.

        wp_cache_set('media_attachments', $posts, WP_FILES_CACHE_PREFIX);

        return $posts;
    }

    /**
     * Return global stats
     * @since 1.0.0
     * @param bool $forceUpdate 
     * @return array|bool|mixed
     */
    private function getGlobalStats($forceUpdate = false)
    {
        $stats = get_option('wpfiles_global_stats');

        if (!$forceUpdate && $stats && !empty($stats) && isset($stats['size_before'])) {
            if (isset($stats['id'])) {
                unset($stats['id']);
            }

            return $stats;
        }

        global $wpdb;

        $compressedData = array(
            'percent'      => 0,
            'size_after'   => 0,
            'human'        => 0,
            'size_before'  => 0,
            'total_images' => 0,
            'bytes'        => 0,
        );

        $offset       = 0;

        $supercompressed = 0;

        $nextQuery   = true;

        while ($nextQuery) {
            $allData = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT meta_value, post_id FROM {$wpdb->postmeta} WHERE meta_key=%s LIMIT %d, %d",
                    Wp_Files_Compression::$compressedMetaKey,
                    $offset,
                    $this->queryLimit
                )
            ); 

            if (!$allData) {
                break;
            }

            foreach ($allData as $data) {

                if (!in_array($data->post_id, $this->attachments, true)) {
                    continue;
                }

                $compressedData['id'][] = $data->post_id;

                if (!empty($data->meta_value)) {

                    $meta = maybe_unserialize($data->meta_value);

                    if (!empty($meta['stats'])) {

                        if (true === $meta['stats']['lossy']) {
                            $supercompressed++;
                        }

                        if (!empty($meta['stats']) && $meta['stats']['size_before'] >= $meta['stats']['size_after']) {

                            $compressedData['size_before']  += !empty($meta['stats']['size_before']) ? (int) $meta['stats']['size_before'] : 0;
                            
                            $compressedData['size_after']   += !empty($meta['stats']['size_after']) ? (int) $meta['stats']['size_after'] : 0;

                            $compressedData['total_images'] += !empty($meta['sizes']) ? count($meta['sizes']) : 0;
                            
                        }
                    }
                }
            }

            $offset += $this->queryLimit;

            if (!empty($this->count_of_attachments_for_compression) && $this->count_of_attachments_for_compression <= $offset) {
                $nextQuery = false;
            }

            $compressedData['bytes'] = $compressedData['size_before'] - $compressedData['size_after'];

        }

        if (!empty($this->dir_compression_stats['orig_size']) && $this->dir_compression_stats['orig_size'] > 0) {
            $compressedData['size_before'] += $this->dir_compression_stats['orig_size'];
        }

        if (!empty($this->dir_compression_stats['bytes']) && $this->dir_compression_stats['bytes'] > 0) {
            $compressedData['bytes'] += $this->dir_compression_stats['bytes'];
        }

        if (!empty($this->dir_compression_stats['optimized']) && $this->dir_compression_stats['optimized'] > 0) {
            $compressedData['total_images'] += $this->dir_compression_stats['optimized'];
        }

        if (!empty($this->dir_compression_stats['image_size']) && $this->dir_compression_stats['image_size'] > 0) {
            $compressedData['size_after'] += $this->dir_compression_stats['image_size'];
        }

        $resize_savings               = $this->getSavings('resize', false);

        $compressedData['resize_savings'] = !empty($resize_savings['bytes']) ? $resize_savings['bytes'] : 0;

        $compressedData['resize_count']   = $this->getSavings('resize', false, false, true);

        $conversion_savings               = $this->getSavings('pngjpg', false);

        $compressedData['conversion_savings'] = !empty($conversion_savings['bytes']) ? $conversion_savings['bytes'] : 0;

        if (!isset($compressedData['bytes']) || $compressedData['bytes'] < 0) {
            $compressedData['bytes'] = 0;
        }

        $compressedData['size_after']  += $resize_savings['size_after'];

        $compressedData['size_before'] += $resize_savings['size_before'];

        $compressedData['bytes']       += $compressedData['conversion_savings'];

        $compressedData['bytes']       += $compressedData['resize_savings'];

        $compressedData['size_after']  += $conversion_savings['size_after'];
        
        $compressedData['size_before'] += $conversion_savings['size_before'];


        if ($compressedData['size_before'] > 0) {
            $compressedData['percent'] = ($compressedData['bytes'] / $compressedData['size_before']) * 100;
        }

        $compressedData['percent'] = round($compressedData['percent'], 1);

        $compressedData['human'] = size_format(
            $compressedData['bytes'],
            ($compressedData['bytes'] >= 1024) ? 1 : 0
        );

        $this->compressed_attachments = !empty($compressedData['id']) ? $compressedData['id'] : '';

        $this->super_compressed = $supercompressed;

        unset($compressedData['id']);

        update_option('wpfiles_global_stats', $compressedData, false);

        return $compressedData;
    }

    /**
     * Get compressed attachments
     * @since 1.0.0
     * @param bool $forceUpdate
     * @return array
     */
    public function getCompressedAttachments($forceUpdate = false)
    {
        if (!$forceUpdate) {
            $compressedCount = wp_cache_get(WP_FILES_PREFIX . 'compressed_ids', WP_FILES_CACHE_PREFIX);
            if (false !== $compressedCount && !empty($compressedCount)) {
                return $compressedCount;
            }
        }

        do_action('wp_files_remove_filters');

        global $wpdb;

        $posts = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key=%s",
                Wp_Files_Compression::$compressedMetaKey
            )
        ); 

        if (!empty($this->recompress_ids) && is_array($this->recompress_ids)) {
            $posts = array_diff($posts, $this->recompress_ids);
        }

        wp_cache_set(WP_FILES_PREFIX . 'compressed_ids', $posts, WP_FILES_CACHE_PREFIX);

        return $posts;
    }

    /**
     * Super compressed attachments
     * @since 1.0.0
     * @return array
     */
    public function getSuperCompressedAttachments()
    {
        $metaQuery = array(
            array(
                'key'   => 'wpfiles-lossy',
                'value' => 1,
            ),
        );

        return $this->runQuery($metaQuery);
    }

    /**
     * Run query
     * @since 1.0.0 
     * @param array $metaQuery 
     * @return array
     */
    private function runQuery($metaQuery = array())
    {
        $getPosts   = true;

        $attachments = array();

        $args = array(
            'orderby'                => 'ID',
            'post_status'            => 'inherit, private',
            'post_type'              => 'attachment',
            'posts_per_page'         => $this->queryLimit,
            'order'                  => 'DESC',
            'fields'                 => array('ids', 'post_mime_type'),
            'update_post_term_cache' => false,
            'offset'                 => 0,
            'meta_query'             => $metaQuery,
            'no_found_rows'          => true,
        );

        while ($getPosts) {

            do_action('wp_files_remove_filters');

            $query = new WP_Query($args);

            if (!empty($query->post_count) && count($query->posts) > 0) {

                $posts = Wp_Files_Helper::filterPostByMimeType($query->posts);

                $attachments = array_merge($attachments, $posts);

                $args['offset'] += $this->queryLimit;

            } else {

                $getPosts = false;
                
            }

            if (count($attachments) >= $this->maxRows) {
                $getPosts = false;
            } elseif (!empty($this->count_of_attachments_for_compression) && $this->count_of_attachments_for_compression <= $args['offset']) {
                $getPosts = false;
            }
        }

       
        if (!empty($this->recompress_ids) && is_array($this->recompress_ids)) {
            $attachments = array_diff($attachments, $this->recompress_ids);
        }

        return $attachments;

    }

    /**
     * Set pro savings stats for not pro user. [Actually for non pro users show saving with free version settings]
     * @since 1.0.0
     * @return mixed
     */
    public function setProSavings()
    {
        if (Wp_Files_Subscription::is_pro()) {
            return;
        }

        $this->stats['pro_savings'] = array(
            'percent' => 0,
            'savings' => 0,
        );

        $savings = $this->stats['percent'] > 0 ? $this->stats['percent'] : 0;
        
        $savingBytes = $this->stats['human'] > 0 ? $this->stats['bytes'] : '0';

        $originalDifference =  2.22745683;

        if (!empty($savings) && $savings > 49) {
            $originalDifference =  1.22036527;
        }

        if (!empty($savings)) {
            $savings = $originalDifference * $savings;
            $savingBytes = $originalDifference * $savingBytes;
        }

        if ($savings > 0) {
            $this->stats['pro_savings'] = array(
                'percent' => number_format_i18n($savings, 1),
                'savings' => size_format($savingBytes, 1),
            );
        }
    }

    /**
     * Skipped attachments count
     * @since 1.0.0
     * @param bool $force
     * @return array
     */
    private function skippedCount($force)
    {
        $attachments = wp_cache_get('skipped_images', WP_FILES_CACHE_PREFIX);

        if (!$force && $attachments) {
            return $attachments;
        }

        global $wpdb;

        $attachments = $wpdb->get_col("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='wpfiles-ignore-bulk'"); // Db call ok.

        wp_cache_set('skipped_images', $attachments, WP_FILES_CACHE_PREFIX);

        return $attachments;
    }

    /**
     * Remaining compression count
     * @since 1.0.0
     * @return int
    */
    private function remainingCompressionCount()
    {
        $recompressionCount   = count($this->recompress_ids);

        $remainingCompressionCount = $this->count_of_attachments_for_compression - $this->compressed_count - $this->skippedCount;

        $remainingCompressionCount = $remainingCompressionCount > 0 ? $remainingCompressionCount : 0;

        if ($recompressionCount > 0 && ($recompressionCount !== $this->compressed_count || 0 === absint($remainingCompressionCount))) {
            return $recompressionCount + $remainingCompressionCount;
        }

        return $remainingCompressionCount;
    }

    /**
     * Remove attachment from the  media attachment lists.
     * @since 1.0.0
     * @param int $id
    */
    private function removeFromMediaAttachmentsList($id)
    {
        $attachments = wp_cache_get('media_attachments', WP_FILES_CACHE_PREFIX);

        if (!$attachments) {
            return;
        }

        $index = array_search((string) $id, $attachments, true);

        if (false !== $index) {
            unset($attachments[$index]);
            wp_cache_set('media_attachments', $attachments, WP_FILES_CACHE_PREFIX);
        }
    }

    /**
     * Remove attachment from the compression list.
     * @since 1.0.0
     * @param integer $id
     */
    public static function removeFromCompressedList($id)
    {
        $compressedIds = wp_cache_get(WP_FILES_PREFIX . 'compressed_ids', WP_FILES_CACHE_PREFIX);

        if (!empty($compressedIds)) {

            $index = array_search(strval($id), $compressedIds, true);

            if (false !== $index) {

                unset($compressedIds[$index]);

                wp_cache_set(WP_FILES_PREFIX . 'compressed_ids', $compressedIds, WP_FILES_CACHE_PREFIX);

            }

        }
        
    }

    /**
     * Total stats
     * @since 1.0.0
     * @param array $totalStats 
     * @return mixed
     */
    public function totalCompression($totalStats)
    {
        $totalStats['stats']['size_after']  = 0;

        $totalStats['stats']['size_before'] = 0;

        $totalStats['stats']['time']        = 0;

        foreach ($totalStats['sizes'] as $row) {

            $totalStats['stats']['size_after']  += !empty($row->size_after) ? $row->size_after : 0;

            $totalStats['stats']['size_before'] += !empty($row->size_before) ? $row->size_before : 0;

            $totalStats['stats']['time']        += !empty($row->time) ? $row->time : 0;
            
        }

        $totalStats['stats']['bytes'] = !empty($totalStats['stats']['size_before']) && $totalStats['stats']['size_before'] > $totalStats['stats']['size_after'] ? $totalStats['stats']['size_before'] - $totalStats['stats']['size_after'] : 0;

        if (!empty($totalStats['stats']['bytes']) && !empty($totalStats['stats']['size_before'])) {
            $totalStats['stats']['percent'] = ($totalStats['stats']['bytes'] / $totalStats['stats']['size_before']) * 100;
        }

        return $totalStats;
    }

    /**
     * Add attachment to the compression list
     * @since 1.0.0
     * @param integer $id 
     */
    public static function addToCompressionList($id)
    {
        $compressedIds = wp_cache_get(WP_FILES_PREFIX . 'compressed_ids', WP_FILES_CACHE_PREFIX);

        if (!empty($compressedIds)) {

            $id = strval($id);

            if (!in_array($id, $compressedIds, true)) {

                $compressedIds[] = $id;

                wp_cache_set(WP_FILES_PREFIX . 'compressed_ids', $compressedIds, WP_FILES_CACHE_PREFIX);
            }
        }
    }

    /**
     * Uncompressed attachments.
     * @since 1.0.0
     * @return array
     */
    public function getUncompressedAttachments()
    {
        if (!empty($this->attachments) && !empty($this->compressed_attachments)) {
            $attachments = array_diff($this->attachments, $this->compressed_attachments);

            if (!empty($this->skippedAttachments)) {
                $attachments = array_diff($attachments, $this->skippedAttachments);
            }

            $attachments = !empty($attachments) && is_array($attachments) ? array_slice($attachments, 0, $this->maxRows) : array();
        } else {
            $attachments = $this-> getAttachments(Wp_Files_Compression::$compressedMetaKey);
        }

        if (!empty($this->recompress_ids) && is_array($this->recompress_ids)) {
            $attachments = array_diff($attachments, $this->recompress_ids);
        }

        return $attachments;
    }

    /**
     * Attachments not optimized/watermarked.
     * @since 1.0.0
     * @param $key
     * @return array
     */
    private function getAttachments($key)
    {
        $metaQuery = array(
            array(
                'key'     => $key,
                'compare' => 'Not exists',
            ),
            array(
                'key'     => 'wpfiles-ignore-bulk',
                'value'   => 'true',
                'compare' => 'Not exists',
            ),
        );

        return $this->runQuery($metaQuery);
    }

    /**
     * Prints message for pending compression
     * @since 1.0.0
     * @param int $totalAttachmentCount Recompress + Uncompressed attachment count.
     * @param int $recompressCount Recompress count.
     * @param int $uncompressedCount Uncompressed image count.
     */
    public function displayPendingCompressionMessage($totalAttachmentCount, $recompressCount, $uncompressedCount)
    {
        $message = sprintf(
            _n('Total %d attachment that needs compression. Click Bulk Compression and compress the images in bulk.', 'Total %d attachments that need compression. Click Bulk Compression and compress the images in bulk.', $totalAttachmentCount, 'wpfiles'),
            $totalAttachmentCount
        );

        $uncompressedMessage = '';

        if (0 < $uncompressedCount) {
            $uncompressedMessage = sprintf(
                esc_html(_n('%1$s%2$d attachment%3$s that needs compression', '%1$s%2$d attachments%3$s that need compression', $uncompressedCount, 'wpfiles')),
                '<strong>',
                absint($uncompressedCount),
                '</strong>'
            );
        }

        $recompressionMessage = '';

        if (0 < $recompressCount) {
            $recompressionMessage = sprintf(
                esc_html(_n('%1$s%2$d attachment%3$s that needs re-compression', '%1$s%2$d attachments%3$s that need re-compression', $recompressCount, 'wpfiles')),
                '<strong>',
                esc_html($recompressCount),
                '</strong>'
            );
        }

        $countImageDescription = sprintf(
            __('%1$s, you have %2$s%3$s%4$s!', 'wpfiles'),
            esc_html(Wp_Files_Helper::getUserName()),
            $uncompressedMessage,
            ($uncompressedMessage && $recompressionMessage ? esc_html__(' and ', 'wpfiles') : ''),
            $recompressionMessage
        );

        if (!Wp_Files_Subscription::is_pro() && $totalAttachmentCount > Wp_Files_Compression::$maximumFreeBulk) {
            $url = add_query_arg(
                array(
                    'coupon'       => 'SDFFILESSFSDF',
                    'checkout'     => 0,
                    'utm_source'   => 'wpfiles',
                    'utm_medium'   => 'plugin',
                    'utm_campaign' => 'bulk-compression',
                ),
                esc_url(WP_FILES_GO_URL.'/pricing')
            );

            $countImageDescription .= sprintf(
                esc_html__(' %1$sUpgrade to Pro%2$s for bulk compression with no limit. Free users can compress %3$d attachments per click.', 'wpfiles'),
                '<a href="' . esc_url($url) . '" target="_blank">',
                '</a>',
                esc_html(Wp_Files_Compression::$maximumFreeBulk)
            );
        }

        if (empty($totalAttachmentCount)) {
            return [
                "description" => esc_html('All done.'),
                "tooltip_message" => esc_attr($message),
                "total_image_count" => esc_html($totalAttachmentCount),
            ];
        } else {
            return [
                "description" => $countImageDescription,
                "tooltip_message" => esc_attr($message),
                "total_image_count" => esc_html($totalAttachmentCount),
            ];
        }
    }

    /**
     * Get stats for attachments
     * @since 1.0.0
     * @param array $attachments
     * @return array 
     */
    public function getStatsForAttachments($attachments = array())
    {
        $totalStats = array(
            'savings_resize'     => 0,
            'size_after'         => 0,
            'savings_conversion' => 0,
            'size_before'        => 0,
            'count_compressed'      => 0,
            'count_images'       => 0,
            'count_supercompressed' => 0,
            'count_remaining'    => 0,
            'count_resize'       => 0,
        );

        if (empty($attachments) || !is_array($attachments)) {
            return $totalStats;
        }

        foreach ($attachments as $attachment) {

            $compressionStats = get_post_meta($attachment, Wp_Files_Compression::$compressedMetaKey, true);

            $resizeSavings = get_post_meta($attachment, WP_FILES_PREFIX . 'resize_savings', true);

            $conversionSavings = Wp_Files_Helper::fetchPngTojpgConversionSavings($attachment);

            if (!empty($compressionStats['stats'])) {
                $totalStats['size_after']  += !empty($compressionStats['stats']['size_after']) ? $compressionStats['stats']['size_after'] : 0;
                
                $totalStats['size_before'] += !empty($compressionStats['stats']['size_before']) ? $compressionStats['stats']['size_before'] : 0;
            }

            $totalStats['count_images']       += !empty($compressionStats['sizes']) && is_array($compressionStats['sizes']) ? count($compressionStats['sizes']) : 0;

            $totalStats['count_supercompressed'] += !empty($compressionStats['stats']) && $compressionStats['stats']['lossy'] ? 1 : 0;

            if (!empty($resizeSavings)) {

                $totalStats['size_before']    += !empty($resizeSavings['size_before']) ? $resizeSavings['size_before'] : 0;
                
                $totalStats['savings_resize'] += !empty($resizeSavings['bytes']) ? $resizeSavings['bytes'] : 0;

                $totalStats['count_resize']   += 1;
                
                $totalStats['size_after']     += !empty($resizeSavings['size_after']) ? $resizeSavings['size_after'] : 0;
                
            }

            if (!empty($conversionSavings)) {
                
                $totalStats['size_before']        += !empty($conversionSavings['size_before']) ? $conversionSavings['size_before'] : 0;
                
                $totalStats['savings_conversion'] += !empty($conversionSavings['bytes']) ? $conversionSavings['bytes'] : 0;

                $totalStats['size_after']         += !empty($conversionSavings['size_after']) ? $conversionSavings['size_after'] : 0;
            
            }

            $totalStats['count_compressed'] += 1;
        }

        return $totalStats;
    }

    /**
     * Get total images to be compressed.
     * @since 1.0.0
     * @return integer
    */
    public function getTotalImagesToCompress()
    {
        $imageToRecompress = count(get_option('wpfiles-recompress-list', array()));

        $uncompressedCount = $this->count_of_attachments_for_compression - $this->compressed_count - $this->skippedCount;

        if ($uncompressedCount > 0) {
            return $imageToRecompress + $uncompressedCount;
        }

        return $imageToRecompress;
    }

    /**
     * Returns remaining count
     * @since 1.0.0
     * @return int
    */
    private function remainingWatermarkCount()
    {
        $rewatermarkCount   = count($this->rewatermark_ids);

        $remainingWatermarkCount = $this->count_of_attachments_for_watermark - $this->watermarked_count;

        $remainingWatermarkCount = $remainingWatermarkCount > 0 ? $remainingWatermarkCount : 0;

        if ($rewatermarkCount > 0 && ($rewatermarkCount !== $this->compressed_count || 0 === absint($remainingWatermarkCount))) {
            return $rewatermarkCount + $remainingWatermarkCount;
        }

        return $remainingWatermarkCount;
    }

    /**
     * Removes an ID from the watermarked IDs list from the object cache.
     * @since 1.0.0
     * @param integer $id
     */
    public static function removeFromWatermarkedList($id)
    {
        $watermarkedIds = wp_cache_get(WP_FILES_PREFIX . 'watermarked_ids', WP_FILES_CACHE_PREFIX);

        if (!empty($watermarkedIds)) {
            $index = array_search(strval($id), $watermarkedIds, true);
            if (false !== $index) {
                unset($watermarkedIds[$index]);
                wp_cache_set(WP_FILES_PREFIX . 'watermarked_ids', $watermarkedIds, WP_FILES_CACHE_PREFIX);
            }
        }
    }

    /**
     * Watermarked attachment IDs.
     * @since 1.0.0
     * @param bool $forceUpdate 
     * @return array
     */
    public function getWatermarkedAttachments($forceUpdate = false)
    {
        if (!$forceUpdate) {
            $watermarkedCount = wp_cache_get(WP_FILES_PREFIX . 'watermarked_ids', WP_FILES_CACHE_PREFIX);
            if (false !== $watermarkedCount && !empty($watermarkedCount)) {
                return $watermarkedCount;
            }
        }

        do_action('wp_files_remove_filters');

        global $wpdb;

        $posts = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key=%s",
                Wp_Files_Compression::$watermarkMetaKey
            )
        ); 

        if (!empty($this->watermark_ids) && is_array($this->watermark_ids)) {
            $posts = array_diff($posts, $this->watermark_ids);
        }

        wp_cache_set(WP_FILES_PREFIX . 'watermarked_ids', $posts, WP_FILES_CACHE_PREFIX);

        return $posts;
    }

    /**
     * Unwatermarked attachments.
     * @since 1.0.0
     * @return array
     */
    public function getUnwatermarkedAttachments()
    {
        if (!empty($this->attachments) && !empty($this->watermarked_attachments)) {
            $attachments = array_diff($this->attachments, $this->watermarked_attachments);

            if (!empty($this->skippedAttachments)) {
                $attachments = array_diff($attachments, $this->skippedAttachments);
            }

            $attachments = !empty($attachments) && is_array($attachments) ? array_slice($attachments, 0, $this->maxRows) : array();
        } else {
            $attachments = $this-> getAttachments(Wp_Files_Compression::$watermarkMetaKey);
        }

        if (!empty($this->rewatermark_ids) && is_array($this->rewatermark_ids)) {
            $attachments = array_diff($attachments, $this->rewatermark_ids);
        }

        return $attachments;
    }

    /**
     * Total images to be watermarked.
     * @since 1.0.0
     * @return integer
     */
    public function getTotalImagesToWatermark()
    {
        $imageToRewatermark = count(get_option('wpfiles-rewatermark-list', array()));

        $unwatermarkedCount = $this->count_of_attachments_for_watermark - $this->watermarked_count;

        if ($unwatermarkedCount > 0) {
            return $imageToRewatermark + $unwatermarkedCount;
        }

        return $imageToRewatermark;
    }

    /**
     * Add to watermark list
     * @since 1.0.0
     * @param integer $id 
     */
    public static function addToWatermarkedList($id)
    {
        $watermarkedIds = wp_cache_get(WP_FILES_PREFIX . 'watermarked_ids', WP_FILES_CACHE_PREFIX);

        if (!empty($watermarkedIds)) {

            $id = strval($id);

            if (!in_array($id, $watermarkedIds, true)) {

                $watermarkedIds[] = $id;

                wp_cache_set(WP_FILES_PREFIX . 'watermarked_ids', $watermarkedIds, WP_FILES_CACHE_PREFIX);
            }
        }
    }

    /**
     * Prints message for pending watermark
     * @since 1.0.0
     * @param int $totalImagesCount     
     * @param int $rewatermarkCount  
     * @param int $unwatermarkedCount 
     */
    public function displayPendingWatermarkMessage($totalImagesCount, $rewatermarkCount, $unwatermarkedCount)
    {
        $message = sprintf(
            _n('Total %d attachment that needs watermarking. Click Bulk Watermark and watermark the images in bulk.', 'Total %d attachments that need watermarking. Click Bulk Watermark and watermark the images in bulk.', $totalImagesCount, 'wpfiles'),
            $totalImagesCount
        );

        $unwatermarkedMessage = '';

        if (0 < $unwatermarkedCount) {
            $unwatermarkedMessage = sprintf(
                esc_html(_n('%1$s%2$d attachment%3$s that needs watermarking', '%1$s%2$d attachments%3$s that need watermarking', $unwatermarkedCount, 'wpfiles')),
                '<strong>',
                absint($unwatermarkedCount),
                '</strong>'
            );
        }

        $rewatermarkMessage = '';

        if (0 < $rewatermarkCount) {
            $rewatermarkMessage = sprintf(
                esc_html(_n('%1$s%2$d attachment%3$s that needs re-watermarking', '%1$s%2$d attachments%3$s that need re-watermarking', $rewatermarkCount, 'wpfiles')),
                '<strong>',
                esc_html($rewatermarkCount),
                '</strong>'
            );
        }

        $imageCountDescription = sprintf(
            __('%1$s, you have %2$s%3$s%4$s!', 'wpfiles'),
            esc_html(Wp_Files_Helper::getUserName()),
            $unwatermarkedMessage,
            ($unwatermarkedMessage && $rewatermarkMessage ? esc_html__(' and ', 'wpfiles') : ''),
            $rewatermarkMessage
        );

        if (empty($totalImagesCount)) {
            return [
                "description" => esc_html('All done.'),
                "tooltip_message" => esc_attr($message),
                "total_image_count" => esc_html($totalImagesCount),
            ];
        } else {
            return [
                "description" => $imageCountDescription,
                "tooltip_message" => esc_attr($message),
                "total_image_count" => esc_html($totalImagesCount),
            ];
        }
    }

    /**
     * Process CDN status.
     * @since 1.0.0
     * @param array|WP_Error $response
     * @return stdClass|WP_Error
     */
    public function processSiteStatus($response)
    {
        if (is_wp_error($response)) {
            return $response;
        }

        $status = json_decode($response['body']);

        if (is_null($status) || wp_remote_retrieve_response_code($response) === 429) {
            wp_send_json_error(
                array(
                    'message' =>  __('Too many requests, please try again in a moment', 'wpfiles')
                ),
                200
            );
        }

        return $status;
    }
    
}
