<?php

/**
 * @file plugins/reports/counter/classes/reports/CounterReportJR1.inc.php
 *
 * Copyright (c) 2014 University of Pittsburgh
 * Distributed under the GNU GPL v2 or later. For full terms see the file docs/COPYING.
 *
 * @class CounterReportJR1
 * @ingroup plugins_reports_counter
 *
 * @brief Server Report 1
 */

use APP\core\Application;
use APP\core\Services;
use APP\statistics\StatisticsHelper;
use PKP\db\DAORegistry;

import('plugins.reports.counter.classes.CounterReport');

class CounterReportJR1 extends CounterReport
{
    /**
     * Get the report title
     *
     * @return $string
     */
    public function getTitle()
    {
        return __('plugins.reports.counter.jr1.title');
    }

    /**
     * Convert an OPS metrics request to COUNTER ReportItems
     * @param string|array $columns column (aggregation level) selection
     * @param array $filters report-level filter selection
     * @param array $orderBy order criteria
     * @param null|DBResultRange $range paging specification
     * @see ReportPlugin::getMetrics for more details
     * @return array COUNTER\ReportItem array
     */
    public function getReportItems($columns = [], $filters = [], $orderBy = [], $range = null)
    {
        // Columns are fixed for this report
        $defaultColumns = [StatisticsHelper::STATISTICS_DIMENSION_CONTEXT_ID, StatisticsHelper::STATISTICS_DIMENSION_FILE_TYPE, StatisticsHelper::STATISTICS_DIMENSION_MONTH];
        if ($columns && array_diff($columns, $defaultColumns)) {
            $this->setError(new Exception(__('plugins.reports.counter.exception.column'), COUNTER_EXCEPTION_WARNING | COUNTER_EXCEPTION_BAD_COLUMNS));
        }
        // Check filters for correct context(s)
        $validFilters = $this->filterForContext($filters);
        // Filters defaults to last month, but can be provided by month or by day (which is defined in the $columns)
        if (!isset($filters['dateStart']) && !isset($filters['dateEnd'])) {
            $validFilters['dateStart'] = date_format(date_create('first day of previous month'), 'Ymd');
            $validFilters['dateEnd'] = date_format(date_create('last day of previous month'), 'Ymd');
        } elseif (!isset($filters['dateStart']) || !isset($filters['dateEnd'])) {
            // either start or end date not set
            $this->setError(new Exception(__('plugins.reports.counter.exception.filter'), COUNTER_EXCEPTION_WARNING | COUNTER_EXCEPTION_BAD_FILTERS));
        } elseif (isset($filters['dateStart']) && isset($filters['dateEnd'])) {
            $validFilters['dateStart'] = $filters['dateStart'];
            $validFilters['dateEnd'] = $filters['dateEnd'];
            unset($filters['dateStart']);
            unset($filters['dateEnd']);
        }
        if (!isset($filters['assocTypes'])) {
            $validFilters['assocTypes'] = Application::ASSOC_TYPE_SUBMISSION_FILE;
            unset($filters['assocTypes']);
        } elseif ($filters['assocTypes'] != Application::ASSOC_TYPE_SUBMISSION_FILE) {
            $this->setError(new Exception(__('plugins.reports.counter.exception.filter'), COUNTER_EXCEPTION_ERROR | COUNTER_EXCEPTION_BAD_FILTERS));
        }
        // Any further filters aren't recognized (at this time, at least)
        if (array_keys($filters)) {
            $this->setError(new Exception(__('plugins.reports.counter.exception.filter'), COUNTER_EXCEPTION_WARNING | COUNTER_EXCEPTION_BAD_FILTERS));
        }
        // TODO: range
        $results = Services::get('publicationStats')->getQueryBuilder($validFilters)
            ->getSum($defaultColumns)
            // Ordering must be by Server (ReportItem), and by Month (ItemPerformance) for JR1
            ->orderBy(StatisticsHelper::STATISTICS_DIMENSION_CONTEXT_ID, StatisticsHelper::STATISTICS_ORDER_DESC)
            ->orderBy(StatisticsHelper::STATISTICS_DIMENSION_MONTH, StatisticsHelper::STATISTICS_ORDER_ASC)
            ->get()->toArray();
        $reportItems = [];
        if ($results) {
            // We'll create a new Report Item with these Metrics on a server change
            $metrics = [];
            // We'll create a new Metric with these Performance Counters on a period change
            $counters = [];
            $lastPeriod = 0;
            $lastServer = 0;
            foreach ($results as $rs) {
                $rs = json_decode(json_encode($rs), true);
                // Identify the type of request
                $metricTypeKey = $this->getKeyForFiletype($rs[StatisticsHelper::STATISTICS_DIMENSION_FILE_TYPE]);
                // Period changes or greater trigger a new ItemPerformace metric
                if ($lastPeriod != $rs[StatisticsHelper::STATISTICS_DIMENSION_MONTH] || $lastServer != $rs[StatisticsHelper::STATISTICS_DIMENSION_CONTEXT_ID]) {
                    if ($lastPeriod != 0) {
                        $metrics[] = $this->createMetricByMonth($lastPeriod, $counters);
                        $counters = [];
                    }
                }
                $lastPeriod = $rs[StatisticsHelper::STATISTICS_DIMENSION_MONTH];
                $counters[] = new COUNTER\PerformanceCounter($metricTypeKey, $rs[StatisticsHelper::STATISTICS_METRIC]);
                // Server changes trigger a new ReportItem
                if ($lastServer != $rs[StatisticsHelper::STATISTICS_DIMENSION_CONTEXT_ID]) {
                    if ($lastServer != 0 && $metrics) {
                        $item = $this->_createReportItem($lastServer, $metrics);
                        if ($item) {
                            $reportItems[] = $item;
                        } else {
                            $this->setError(new Exception(__('plugins.reports.counter.exception.partialData'), COUNTER_EXCEPTION_WARNING | COUNTER_EXCEPTION_PARTIAL_DATA));
                        }
                        $metrics = [];
                    }
                }
                $lastServer = $rs[StatisticsHelper::STATISTICS_DIMENSION_CONTEXT_ID];
            }
            // Capture the last unprocessed ItemPerformance and ReportItem entries, if applicable
            if ($counters) {
                $metrics[] = $this->createMetricByMonth($lastPeriod, $counters);
            }
            if ($metrics) {
                $item = $this->_createReportItem($lastServer, $metrics);
                if ($item) {
                    $reportItems[] = $item;
                } else {
                    $this->setError(new Exception(__('plugins.reports.counter.exception.partialData'), COUNTER_EXCEPTION_WARNING | COUNTER_EXCEPTION_PARTIAL_DATA));
                }
            }
        } else {
            $this->setError(new Exception(__('plugins.reports.counter.exception.noData'), COUNTER_EXCEPTION_ERROR | COUNTER_EXCEPTION_NO_DATA));
        }
        return $reportItems;
    }

    /**
     * Given a serverId and an array of COUNTER\Metrics, return a COUNTER\ReportItems
     *
     * @param int $serverId
     * @param array $metrics COUNTER\Metric array
     *
     * @return mixed COUNTER\ReportItems or false
     */
    private function _createReportItem($serverId, $metrics)
    {
        $serverDao = DAORegistry::getDAO('ServerDAO'); /** @var ServerDAO $serverDao */
        $server = $serverDao->getById($serverId);
        if (!$server) {
            return false;
        }
        $serverName = $server->getLocalizedName();
        $serverPubIds = [];
        foreach (['print', 'online'] as $issnType) {
            if ($server->getData($issnType . 'Issn')) {
                try {
                    $serverPubIds[] = new COUNTER\Identifier(ucfirst($issnType) . '_ISSN', $server->getData($issnType . 'Issn'));
                } catch (Exception $ex) {
                    // Just ignore it
                }
            }
        }
        $serverPubIds[] = new COUNTER\Identifier(COUNTER_LITERAL_PROPRIETARY, $server->getPath());
        $reportItem = [];
        try {
            $reportItem = new COUNTER\ReportItems(__('common.software'), $serverName, COUNTER_LITERAL_SERVER, $metrics, null, $serverPubIds);
        } catch (Exception $e) {
            $this->setError($e, COUNTER_EXCEPTION_ERROR | COUNTER_EXCEPTION_INTERNAL);
        }
        return $reportItem;
    }
}
