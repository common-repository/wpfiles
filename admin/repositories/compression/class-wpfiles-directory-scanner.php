<?php
class Wp_Files_Directory_Scanner
{
    /**
     * Scanned current step
     * @since 1.0.0
     * @var int
     */
    private $currentStep = 0;

    /**
     * Scanning slug
     * @since 1.0.0
     * @var string IS_SCANNING_SLUG
     */
    const IS_SCANNING_SLUG = 'wpfiles-files-scanning';

    /**
     * Scanning current step
     * @since 1.0.0
     * @var int CURRENT_STEP
     */

    const CURRENT_STEP     = 'wpfiles-scan-step';

    /**
     * Scan process
     * @since 1.0.0
     * @var boolean $isScanning
    */
    private $isScanning = false;

    /**
     * Refresh status
     * @since 1.0.0
     * @return void
    */
    private function refreshStatus()
    {
        $this->isScanning  = get_transient(self::IS_SCANNING_SLUG);
        $this->currentStep = (int) get_option(self::CURRENT_STEP);
    }

    /**
     * Initializes the scan
     * @since 1.0.0
     * @return void
    */
    public function initScan()
    {
        set_transient(self::IS_SCANNING_SLUG, true, 60 * 5); // 5 minutes max
        update_option(self::CURRENT_STEP, 0);
        $this->refreshStatus();
    }

    /**
     * Current step being scanned.
     * @since 1.0.0
     * @param int $step
     * @return void
    */
    public function updateCurrentStep($step)
    {
        update_option(self::CURRENT_STEP, absint($step));
        $this->refreshStatus();
    }


    /**
     * Reset scan
     * @since 1.0.0
     * @return void
    */
    public function resetScan()
    {
        delete_transient(self::IS_SCANNING_SLUG);
        delete_option(self::CURRENT_STEP);
        $this->refreshStatus();
    }
    
    /**
     * Current scan step being scanned
     * @since 1.0.0
     * @return int
    */
    public function getCurrentScanStep()
    {
        $this->refreshStatus();
        
        return $this->currentStep;
    }

    /**
     * Check if a scanning is in process
     * @since 1.0.0
     * @return boolean
    */
    public function isScanning()
    {
        $this->refreshStatus();
        return $this->isScanning;
    }

    /**
     * Return total steps to finish the scan
     * @since 1.0.0
     * @param object $dir_instance
     * @return int
    */
    public function getScanSteps($dir_instance)
    {
        return count($dir_instance->getScannedImages());
    }
}
