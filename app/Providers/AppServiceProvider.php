<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Filesystem\FilesystemAdapter;
use League\Flysystem\Filesystem;
use App\Filesystem\CloudinaryStorageAdapter;
use App\Mail\Transport\GmailApiTransport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Storage::extend('cloudinary', function ($app, $config) {
            $adapter = new CloudinaryStorageAdapter($config);
            return new FilesystemAdapter(
                new Filesystem($adapter, $config),
                $adapter,
                $config
            );
        });

        Mail::extend('gmail', function (array $config) {
            $gmailConfig = $this->app['config']->get('services.gmail');
            
            return new GmailApiTransport(
                $gmailConfig['client_id'] ?? '',
                $gmailConfig['client_secret'] ?? '',
                $gmailConfig['refresh_token'] ?? ''
            );
        });
    }
}
