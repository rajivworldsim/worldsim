<?php
/**
* @author Amasty Team
* @copyright Copyright (c) 2022 Amasty (https://www.amasty.com)
* @package GeoIP Data for Magento 2 (System)
*/

namespace Amasty\Geoip\Test\Unit\Traits;

/**
 * Create Object Manager instance for test purposes.
 *
 * @codingStandardsIgnoreFile
 */
trait ObjectManagerTrait
{
    /**
     * @var \Magento\Framework\TestFramework\Unit\Helper\ObjectManager
     */
    private $objectManager;

    /**
     * @return \Magento\Framework\TestFramework\Unit\Helper\ObjectManager
     */
    private function getObjectManager()
    {
        if (!$this->objectManager) {
            $this->objectManager = new \Magento\Framework\TestFramework\Unit\Helper\ObjectManager($this);
        }

        return $this->objectManager;
    }
}
