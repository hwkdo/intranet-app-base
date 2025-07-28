<?php

namespace Hwkdo\IntranetAppBase\Commands;

use Illuminate\Console\Command;

class IntranetAppBaseCommand extends Command
{
    public $signature = 'intranet-app-base';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
