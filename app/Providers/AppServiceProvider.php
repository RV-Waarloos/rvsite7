<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Providers\MailerSend\MailerSendBulkTransport;
use MailerSend\MailerSend;
use Illuminate\Support\Arr;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\URL;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(MailerSend::class, function(Application $app) {
            $config = $this->app['config']->get('mailersend-driver', []);

            return new MailerSend([
                'api_key' => Arr::get($config, 'api_key'),
                'host' => Arr::get($config, 'host'),
                'protocol' => Arr::get($config, 'protocol'),
                'api_path' => Arr::get($config, 'api_path'),
            ]);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        $this->app->make(MailManager::class)->extend('mailersendbulk', function () {
            $config = $this->app['config']->get('mailersend-driver', []);

            $mailersend = new MailerSend([
                'api_key' => Arr::get($config, 'api_key'),
                'host' => Arr::get($config, 'host'),
                'protocol' => Arr::get($config, 'protocol'),
                'api_path' => Arr::get($config, 'api_path'),
            ]);

            return new MailerSendBulkTransport($mailersend);
        });            
    }
}
