<?php


namespace Modules\GitlabIntegration\Console;

use Illuminate\Console\Command;
use Modules\GitlabIntegration\Services\TimeSynchronizer;

class SynchronizeTime extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'gitlab:sync-time';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize time for Gitlab Tasks for all users, who activate the Gitlab integration.';

    /**
     * Execute the console command.
     */
    public function handle(TimeSynchronizer $timeSynchronizer): void
    {
        $timeSynchronizer->synchronize();
    }
}
