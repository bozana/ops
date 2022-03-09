<?php

/**
 * @file classes/tasks/UsageStatsMonthly.inc.php
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UsageStatsMonthly
 * @ingroup tasks
 *
 * @brief Class responsible to aggregate monthly usage stats.
 */

namespace APP\tasks;

use APP\core\Application;
use Illuminate\Support\Facades\DB;
use PKP\config\Config;
use PKP\scheduledTask\ScheduledTask;

class UsageStatsMonthly extends ScheduledTask
{
    /**
     * @copydoc ScheduledTask::getName()
     */
    public function getName(): string
    {
        return __('usageStats.usageStatsMonthly');
    }

    /**
     * @copydoc ScheduledTask::executeActions()
     */
    public function executeActions(): bool
    {
        $currentMonth = date('Ym');
        $application = Application::get();
        $request = $application->getRequest();
        $site = $request->getSite();

        if (substr(Config::getVar('database', 'driver'), 0, strlen('postgres')) === 'postgres') {
            $dateFormatting = "to_char(sd.date, 'YYYYMM')";
        } else {
            $dateFormatting = "DATE_FORMAT(sd.date, '%Y%m')";
        }

        // geo
        DB::statement(
            "
			INSERT INTO metrics_submission_geo_monthly (context_id, submission_id, country, region, city, month, metric, metric_unique)
			SELECT sd.context_id, sd.submission_id, COALESCE(sd.country, ''), COALESCE(sd.region, ''), COALESCE(sd.city, ''), {$dateFormatting} as month, SUM(sd.metric), SUM(sd.metric_unique)) FROM metrics_submission_geo_daily sd WHERE {$dateFormatting} <> ? GROUP BY sd.context_id, sd.submission_id, sd.country, sd.region, sd.city, {$dateFormatting}
			",
            [$currentMonth]
        );
        if ($site->getData('usageStatsKeepDaily') == 0) {
            DB::statement("DELETE FROM metrics_submission_geo_daily sd WHERE {$dateFormatting} <> ?", [$currentMonth]);
        }

        // submissions
        DB::statement(
            "
            INSERT INTO metrics_counter_submission_monthly (context_id, submission_id, month, metric_investigations, metric_investigations_unique, metric_requests, metric_requests_unique)
            SELECT sd.context_id, sd.submission_id, {$dateFormatting} as month, SUM(sd.metric_investigations), SUM(sd.metric_investigations_unique), SUM(sd.metric_requests), SUM(sd.metric_requests_unique) FROM metrics_counter_submission_daily sd WHERE {$dateFormatting} <> ? GROUP BY sd.context_id, sd.submission_id, {$dateFormatting}
            ",
            [$currentMonth]
        );
        if ($site->getData('usageStatsKeepDaily') == 0) {
            DB::statement("DELETE FROM metrics_counter_submission_daily sd WHERE {$dateFormatting} <> ?", [$currentMonth]);
        }

        //institutions
        DB::statement(
            "
			INSERT INTO metrics_counter_submission_institution_monthly (context_id, submission_id, institution_id, month, metric_investigations, metric_investigations_unique, metric_requests, metric_requests_unique)
			SELECT sd.context_id, sd.submission_id, sd.institution_id, {$dateFormatting} as month, SUM(sd.metric_investigations), SUM(sd.metric_investigations_unique), SUM(sd.metric_requests), SUM(sd.metric_requests_unique) FROM metrics_counter_submission_institution_daily id WHERE {$dateFormatting} <> ? GROUP BY sd.context_id, sd.submission_id, sd.institution_id, {$dateFormatting}
			",
            [$currentMonth]
        );
        if ($site->getData('usageStatsKeepDaily') == 0) {
            DB::statement("DELETE FROM metrics_counter_submission_institution_daily sd WHERE {$dateFormatting} <> ?", [$currentMonth]);
        }

        return true;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\tasks\UsageStatsMonthly', '\UsageStatsMonthly');
}
