<?php
/**
 * Hunter_Geolocation
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0), a
 * copy of which is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category   Hunter
 * @package    Hunter_Geolocation
 * @author     Travis Hunter <travis.c.hunter@gmail.com>
 * @copyright  Copyright (c) 2014 Travis Hunter
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

// Should load all dependencies for Maxmind API
require 'vendor/autoload.php';
use GeoIp2\Database\Reader as Reader;

class Hunter_Geolocation_Helper_Data extends Mage_Core_Helper_Abstract
{
    protected $_dbPath = '/usr/local/share/GeoIP/GeoLite2-City.mmdb';
    protected $_sessionRequestIpKey = "sessionIP";
    protected $_sessionRequestCountryKey = "sessionCountry";
    protected $_sessionRequestRegionKey = "sessionRegion";
    protected $_sessionRequestCityKey = "sessionCity";

    /**
     * Grab the country value stored in the session.
     *
     * @return string
     */
    public function getSessionCountry()
    {
        return Mage::getSingleton('core/session')->getData($this->_sessionRequestCountryKey);
    }

    /**
     * Grab the region value stored in the session.
     *
     * @return string
     */
    public function getSessionRegion()
    {
        return Mage::getSingleton('core/session')->getData($this->_sessionRequestRegionKey);
    }
        
    /**
     * Grab the city value stored in the session.
     *
     * @return string
     */
    public function getSessionCity()
    {
        return Mage::getSingleton('core/session')->getData($this->_sessionRequestCityKey);
    }

    /**
     * Initiates geolocation lookup, and stores the IP address on the session so that the 
     * operation is only performed once.
     *
     * @param string $ipAddress
     */
    public function setSessionIp($ipAddress)
    {
        $session = Mage::getSingleton('core/session');
        $lastIp = $session->getData($this->_sessionRequestIpKey);

        if ($ipAddress && $ipAddress != $lastIp) {
            $this->lookupGeolocationForIp($ipAddress);
            $session->setData($this->_sessionRequestIpKey, $ipAddress);
        }
    }

    private function setSessionCountry($country)
    {
        Mage::getSingleton('core/session')->setData($this->_sessionRequestCountryKey, $country);
    }

    private function setSessionRegion($region)
    {
        Mage::getSingleton('core/session')->setData($this->_sessionRequestRegionKey, $region);
    }
        
    private function setSessionCity($city)
    {
        Mage::getSingleton('core/session')->setData($this->_sessionRequestCityKey, $city);
    }

    /**
     * This function will request the location info from a webservice first, and then
     * use the MaxMind database as a fallback.
     *
     * @param string $ipAddress
     */
    private function lookupGeolocationForIp($ipAddress)
    {
        $success = $this->getFreeGeoIpLocationInfo($ipAddress);
        if (!$success) {
            $this->getMaxmindLocationInfo($ipAddress);
        }
    }

    /**
     * Request geolocation info from freegeoip.net. Returns true if successful, false otherwise.
     *
     * @param string $ipAddress
     * @return boolean
     */
    private function getFreeGeoIpLocationInfo($ipAddress)
    {
        try {
            // GET it
            $response = file_get_contents("http://freegeoip.net/json/" . $ipAddress);
            if ($response != false) {
                // Save it
                $json = json_decode($response, true);
                $this->setSessionCountry($json['country_code']);
                $this->setSessionRegion($json['region_code']);
                $this->setSessionCity($json['city']);
                return true;
            }
        } catch (Exception $e) {
            // Uh-oh
        }
        return false;
    }

    /**
     * Lookup geolocation info in local MaxMind DB. Returns true if successful, false otherwise.
     *
     * @param string $ipAddress
     * @return boolean
     */
    private function getMaxmindLocationInfo($ipAddress)
    {
        try {
            // Init and query database
            $reader = new Reader($this->_dbPath);
            $record = $reader->city($ipAddress);
            // Store in session
            $this->setSessionCountry($record->country->isoCode);
            $this->setSessionRegion($record->mostSpecificSubdivision->isoCode);
            $this->setSessionCity($record->city->name);
        } catch (Exception $e) {
            return false;
        }
        return true;
    }
}