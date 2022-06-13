<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I6782_RemovePlugins.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I6782_RemovePlugins
 * @brief Remove the usageStats and counter (R4) report plugin.
 *
 * This script has to be called after I6782_Metrics, i.e. after usageStats plugin settings were successfully migrated.
 */

namespace APP\migration\upgrade\v3_4_0;

use Illuminate\Support\Facades\DB;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

class I6782_RemovePlugins extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        // Remove usageStats plugin and counter report plugin
        // Differently to versionDao->disableVersion, we will remove the entry from the table 'versions' and 'plugin_settings'
        // because the plugins cannot be used any more
        DB::table('versions')
            ->where('product_type', '=', 'plugins.generic')
            ->where('product', '=', 'usageStats')
            ->delete();
        DB::table('plugin_settings')
            ->where('plugin_name', '=', 'usagestatsplugin')
            ->delete();
        DB::table('versions')
            ->where('product_type', '=', 'plugins.reports')
            ->where('product', '=', 'counterReport')
            ->delete();

        // It is not needed to remove usageStats plugin scheduled task from the Acron plugin, because
        // PKPAcronPlugin function _parseCrontab() will be called at the end of update, that
        // will overwrite the old crontab setting.

        // Remove the old scheduled task from the table scheduled_tasks
        DB::table('scheduled_tasks')->where('class_name', '=', 'plugins.generic.usageStats.UsageStatsLoader')->delete();
    }

    /**
     * Reverse the downgrades
     *
     * @throws DowngradeNotSupportedException
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}
