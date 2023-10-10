<?php
/**
 * Mirasvit
 *
 * This source file is subject to the Mirasvit Software License, which is available at https://mirasvit.com/license/.
 * Do not edit or add to this file if you wish to upgrade the to newer versions in the future.
 * If you wish to customize this module for your needs.
 * Please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Mirasvit
 * @package   mirasvit/module-cache-warmer
 * @version   1.7.7
 * @copyright Copyright (C) 2022 Mirasvit (https://mirasvit.com/)
 */


namespace Mirasvit\CacheWarmer\Block\Adminhtml\Info;

use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\View\Element\Template;
use Mirasvit\CacheWarmer\Api\Data\PageInterface;
use Mirasvit\CacheWarmer\Model\Config;
use Mirasvit\CacheWarmer\Repository\LogRepository;
use Mirasvit\CacheWarmer\Service\Config\ExtendedConfig;
use Mirasvit\CacheWarmer\Service\Rate\CacheFillRateService;
use Mirasvit\CacheWarmer\Service\Rate\ServerLoadRateService;
use Mirasvit\CacheWarmer\Service\WarmerSpeedService;

class Info extends Template
{
    const CACHE_COVERAGE_ID = 'cacheCoverage';
    const CACHE_STATUS_ID   = 'cacheStatus';
    const CACHE_HISTORY_ID  = 'cacheHistory';
    const SERVER_LOAD_ID    = 'serverLoad';

    const CACHE_COVERAGE_LABEL = "Cache Coverage (last 24H)";
    const CACHE_STATUS_LABEL   = "Cache Fill Rate";
    const CACHE_HISTORY_LABEL  = "Cache Fill History (last 24H)";
    const SERVER_LOAD_LABEL    = "Average system load";

    const CHART_TYPE_DOUGHNUT = 'doughnut';
    const CHART_TYPE_LINE     = 'line';

    private $fillRateService;

    private $config;

    private $deploymentConfig;

    private $warmerSpeedService;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    private $urlBuilder;

    private $logRepository;

    private $extendedConfig;
    
    private $serverLoadRateService;

    public function __construct(
        ServerLoadRateService $serverLoadRateService,
        CacheFillRateService $fillRateService,
        WarmerSpeedService $warmerSpeedService,
        Config $config,
        ExtendedConfig $extendedConfig,
        LogRepository $logRepository,
        DeploymentConfig $deploymentConfig,
        Template\Context $context,
        array $data = []
    ) {
        $this->serverLoadRateService = $serverLoadRateService;
        $this->fillRateService       = $fillRateService;
        $this->warmerSpeedService    = $warmerSpeedService;
        $this->config                = $config;
        $this->extendedConfig        = $extendedConfig;
        $this->deploymentConfig      = $deploymentConfig;
        $this->logRepository         = $logRepository;
        $this->urlBuilder            = $context->getUrlBuilder();

        parent::__construct($context, $data);
    }

    /**
     * {@inheritdoc}
     */
    protected function _toHtml()
    {
        if (!$this->extendedConfig->isStatisticsEnabled()) {
            return '';
        }

        return parent::_toHtml(); // TODO: Change the autogenerated stub
    }

    /**
     * @param string $type
     * @param string $id
     *
     * @return array
     */
    public function getChartConfigData($type, $id)
    {
        $data = [];

        $data['id']   = $id;
        $data['type'] = $type;

        $data['data'] = $this->getChartDataConfig($type, $id);

        $data['chartOptions'] = $this->getChartOptionsConfig($type, $id);


        return [
            'id'           => $id,
            'type'         => $type,
            'data'         => $this->getChartDataConfig($type, $id),
            'chartOptions' => $this->getChartOptionsConfig($type, $id)
        ];
    }

    /**
     * @return array
     */
    public function getFillHistory()
    {
        $history = [];

        $ts          = ceil($this->config->getDateTime()->getTimestamp() / 60) * 60 - 24 * 60 * 60;
        $rateHistory = $this->fillRateService->getHistory();

        $prevValue = 0;

        for ($i = 0; $i < 24 * 60; $i++) {
            $ts += 60;

            $date = date("Y-m-d H:i:00", $ts);

            $value = isset($rateHistory[$ts]) ? $rateHistory[$ts] : $prevValue;

            $history[] = [
                'x' => $date,
                'y' => $value,
            ];

            $prevValue = $value;
        }

        return $history;
    }

    public function getServerLoadHistory()
    {
        $history = [];

        $storedHistory = $this->serverLoadRateService->getHistory();

        foreach ($storedHistory as $ts => $rate) {
            $date = date("Y-m-d H:i:00", $ts);

            $history[] = [
                'x' => $date,
                'y' => $rate
            ];
        }

        return $history;
    }

    /**
     * @return array
     */
    public function getPagesStatusData()
    {
        $cached      = $this->fillRateService->getPagesCountByStatus(PageInterface::STATUS_CACHED);
        $pending     = $this->fillRateService->getPagesCountByStatus(PageInterface::STATUS_PENDING);
        $uncacheable = $this->fillRateService->getPagesCountByStatus(PageInterface::STATUS_UNCACHEABLE);

        $total = $cached + $pending + $uncacheable;

        $cachedRate      = $total > 0 ? round($cached / $total * 100) : 0;
        $pendingRate     = $total > 0 ? round($pending / $total * 100) : 0;
        $uncacheableRate = $total > 0 ? 100 - $cachedRate - $pendingRate : 0;

        $rates = [$cachedRate, $pendingRate, $uncacheableRate];

        $data = [
            'cached'      => $cached,
            'pending'     => $pending,
            'uncacheable' => $uncacheable,
            'rates'       => $rates,
        ];

        return $data;
    }

    /**
     * @return array
     */
    public function getCacheCoverageRate()
    {
        $all = $this->logRepository->getCollection()
            ->addFieldToFilter('created_at', ['lt' => date("Y-m-d H:i:s")])
            ->addFieldToFilter('created_at', ['gteq' => date("Y-m-d H:i:s", strtotime('-1day', time()))])
            ->getSize();

        $hits = $this->logRepository->getCollection()
            ->addFieldToFilter('created_at', ['lt' => date("Y-m-d H:i:s")])
            ->addFieldToFilter('created_at', ['gteq' => date("Y-m-d H:i:s", strtotime('-1day', time()))])
            ->addFieldToFilter('is_hit', 1)
            ->getSize();

        $miss = $all - $hits;

        $hitsRate = $all > 0 ? round($hits / $all * 100) : 0;
        $missRate = 100 - $hitsRate;

        $data = [
            'hit'   => $hits,
            'miss'  => $miss,
            'rates' => [$hitsRate, $missRate],
        ];

        return $data;
    }

    /**
     * @return string
     */
    public function getTestPageUrl()
    {
        return $this->urlBuilder->getBaseUrl() . 'cache_warmer/test/cacheable';
    }

    /**
     * @return string
     */
    public function getCacheType()
    {
        switch ($this->config->getCacheType()) {
            case 1:
                $cacheType = 'Built-in';

                if ($external = $this->deploymentConfig->get('cache/frontend/page_cache/backend')) {
                    $type = strpos($external, 'Redis') !== false ? 'Redis' : $external;

                    $cacheType .= " ($type)";
                }

                return $cacheType;
            case 'LITEMAGE':
                return 'LiteMage';
            default:
                return 'Varnish';
        }
    }

    /**
     * @return string
     */
    public function getPrettyTtl()
    {
        $ttl = $this->config->getCacheTtl();

        $hour = 60 * 60;
        $day  = 24 * $hour;

        if ($ttl > 2 * $day) { // 2 days
            return __("%1 days", intval($ttl / $day));
        }

        if ($ttl > 3 * $hour) { // 3 hours
            return __("%1 hours", intval($ttl / $hour));
        }

        return __("%1 mins", intval($ttl / 60));
    }

    public function getAverageWarmingSpeed()
    {
        return $this->warmerSpeedService->getAverageWarmingSpeed() ? : 'N/A';
    }

    /**
     * @param string $type
     * @param string $id
     *
     * @return array
     */
    private function getChartDataConfig($type, $id)
    {
        $data    = [];
        $dataset = [
            'label'           => '',
            'backgroundColor' => $this->getBackgroundColor($id),
            'borderWidth'     => 0,
            'borderColor'     => 'rgba(0,0,0,0)',
            'data'            => $this->getChartData($id),
        ];

        if ($type === self::CHART_TYPE_LINE) {
            $dataset['pointRadius'] = 0;
            $dataset['fill']        = 'origin';
        }

        $data['datasets'] = [$dataset];

        if ($type === self::CHART_TYPE_DOUGHNUT) {
            $data['labels'] = $this->getDataLabels($id);
        }

        return $data;
    }

    /**
     * @param string $type
     * @param string $id
     *
     * @return array
     */
    private function getChartOptionsConfig($type, $id)
    {
        $data = [];

        $data['title'] = [
            'display' => false,
            'text'    => $this->getChartLabel($id),
        ];

        $data['tooltips'] = [
            'enabled'   => true,
            'mode'      => 'nearest',
            'intersect' => false,
        ];

        if ($type === self::CHART_TYPE_DOUGHNUT) {
            $data['responsive']       = false;
            $data['cutoutPercentage'] = 75;
            $data['legend']           = [
                'display' => false,
            ];
        } else {
            $data['responsive'] = true;
            $data['maintainAspectRatio'] = false;
            $data['legend']     = [
                'display' => false,
            ];
            $data['scales']     = [
                'xAxes' => [
                    [
                        'type'      => 'time',
                        'display'   => true,
                        'gridLines' => [
                            'tickMarkLength' => 6,
                            'zeroLineColor'  => 'rgba(0, 0, 0, 0.1)',
                        ],
                        'ticks'     => [
                            'fontSize'   => 12,
                            'fontStyle'  => 'bold',
                            'lineHeight' => 1,
                            'fontColor'  => '#999',
                            'major'      => [
                                'enabled' => true,
                            ],
                        ],
                        'time'      => [
                            'unit'           => 'hour',
                            'stepSize'       => self::SERVER_LOAD_ID == $id ? 1 : 4,
                            'displayFormats' => [
                                'hour' => 'H:mm',
                            ],
                        ],
                    ],
                ],
                'yAxes' => [
                    [
                        'gridLines' => [
                            'tickMarkLength' => 6,
                            'zeroLineWidth'  => 2,
                        ],
                        'ticks'     => [
                            'stepSize'     => 25,
                            'beginAtZero'  => true,
                            'fontSize'     => 12,
                            'fontStyle'    => 'bold',
                            'fontFamily'   => "'Helvetica Neue', Helvetica, Arial, sans-serif",
                            'lineHeight'   => 1,
                            'fontColor'    => '#999',
                            'suggestedMax' => 100,
                        ],
                        'major'     => [
                            'enabled' => true,
                        ],
                    ],
                ],
            ];
        }

        return $data;
    }

    /**
     * @param string $id
     *
     * @return array
     */
    private function getDataLabels($id)
    {
        switch ($id) {
            case self::CACHE_COVERAGE_ID:

                return ['Hit', 'Miss'];
            case self::CACHE_STATUS_ID:

                return ['Cached', 'Pending', 'Uncacheable'];
            default:
                return [];
        }
    }

    /**
     * @param string $id
     *
     * @return string
     */
    private function getChartLabel($id)
    {
        switch ($id) {
            case self::CACHE_COVERAGE_ID:

                return self::CACHE_COVERAGE_LABEL;
            case self::CACHE_STATUS_ID:

                return self::CACHE_STATUS_LABEL;
            case self::CACHE_HISTORY_ID:

                return self::CACHE_HISTORY_LABEL;
            case self::SERVER_LOAD_ID:

                return self::SERVER_LOAD_LABEL;
            default:
                return '';
        }
    }

    /**
     * @param string $id
     *
     * @return array|string
     */
    private function getBackgroundColor($id)
    {
        switch ($id) {
            case self::CACHE_COVERAGE_ID:

                return ['#47cd4a', '#f0b700'];
            case self::CACHE_STATUS_ID:

                return ['#47cd4a', '#f0b700', '#ff2525'];
            case self::CACHE_HISTORY_ID:
            case self::SERVER_LOAD_ID:

                return 'rgba(71,205,74,0.8)';
            default:
                return '#fff';
        }
    }

    /**
     * @param string $id
     *
     * @return array
     */
    private function getChartData($id)
    {
        switch ($id) {
            case self::CACHE_COVERAGE_ID:

                return $this->getCacheCoverageRate()['rates'];
            case self::CACHE_STATUS_ID:

                return $this->getPagesStatusData()['rates'];
            case self::CACHE_HISTORY_ID:

                return $this->getFillHistory();
            case self::SERVER_LOAD_ID:

                return $this->getServerLoadHistory();
            default:
                return [];
        }
    }

}
