<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{

    protected $commands = [
        //
    ];

    protected function schedule(Schedule $schedule)
    {
        $schedule->command('messages:cron')
                ->everyTwoMinutes()
             //   ->onOneServer()
             //   ->timezone('America/Sao_Paulo')
                ->withoutOverlapping()
             //   ->environments(['production'])
                ->runInBackground();

        $schedule->command('sessions:cron')
        ->everyFiveMinutes()
     //   ->onOneServer()
       // ->timezone('America/Sao_Paulo')
        ->withoutOverlapping()
       // ->environments(['production'])
        ->runInBackground();

    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
