<?php

/**
* @file classes/statistics/StatisticsHelper.inc.php
*
* Copyright (c) 2013-2021 Simon Fraser University
* Copyright (c) 2003-2021 John Willinsky
* Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
*
* @class StatisticsHelper
* @ingroup statistics
*
* @brief Statistics helper class.
*
*/

namespace APP\statistics;

use APP\core\Application;
use APP\i18n\AppLocale;
use PKP\statistics\PKPStatisticsHelper;

class StatisticsHelper extends PKPStatisticsHelper
{
    // Metrics
    public const STATISTICS_METRIC_INVESTIGATIONS = 'metric_investigations';
    public const STATISTICS_METRIC_INVESTIGATIONS_UNIQUE = 'metric_investigations_unique';
    public const STATISTICS_METRIC_REQUESTS = 'metric_requests';
    public const STATISTICS_METRIC_REQUESTS_UNIQUE = 'metric_requests_unique';
    public const STATISTICS_METRIC_TOTAL_ORDERBY = self::STATISTICS_METRIC_INVESTIGATIONS;

    /**
     * COUNTER DB tables metrics columns
     */
    public static function getCounterMetricsColumns(): array
    {
        return [
            self::STATISTICS_METRIC_INVESTIGATIONS,
            self::STATISTICS_METRIC_INVESTIGATIONS_UNIQUE,
            self::STATISTICS_METRIC_REQUESTS,
            self::STATISTICS_METRIC_REQUESTS_UNIQUE,
        ];
    }

    /**
     * Get report column names, for JSON keys as well as translations used in CSV reports
     */
    public static function getReportMetricsColumnNames(): array
    {
        return [
            self::STATISTICS_METRIC_INVESTIGATIONS => 'totalViews',
            self::STATISTICS_METRIC_REQUESTS => 'totalDownloads',
            self::STATISTICS_METRIC_INVESTIGATIONS_UNIQUE => 'uniqueViews',
            self::STATISTICS_METRIC_REQUESTS_UNIQUE => 'uniqueDownloads',
        ];
    }

    /**
     * @see PKPStatisticsHelper::getReportObjectTypesArray()
     */
    protected function getReportObjectTypesArray()
    {
        $objectTypes = parent::getReportObjectTypesArray();
        AppLocale::requireComponents(LOCALE_COMPONENT_APP_EDITOR);
        $objectTypes = $objectTypes + [
            Application::ASSOC_TYPE_SERVER => __('context.context'),
            Application::ASSOC_TYPE_SECTION => __('section.section'),
            Application::ASSOC_TYPE_SUBMISSION => __('common.publication'),
            Application::ASSOC_TYPE_SUBMISSION_FILE => __('submission.galleyFiles')
        ];

        return $objectTypes;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\statistics\StatisticsHelper', '\StatisticsHelper');
}
