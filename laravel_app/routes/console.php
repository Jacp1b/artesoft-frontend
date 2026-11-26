<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
Artisan::command('artesoft:version', function (): void {
    $this->info('ARTESOFT sobre Laravel ' . app()->version());
})->purpose('Muestra la versión de Laravel usada por ARTESOFT');
