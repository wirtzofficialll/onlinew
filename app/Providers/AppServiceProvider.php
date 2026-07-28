<?php

namespace App\Providers;

use League\Flysystem\Filesystem;
use League\Flysystem\Sftp\SftpAdapter;
use Illuminate\Support\Facades\View;
use App\Models\Settings;
use App\Models\SettingsCont;
use App\Models\TermsPrivacy;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage as FacadesStorage;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Mail; // Import Mail facade

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // 1. Force HTTPS and Trust Proxies for Render/Load Balancers
        if ($this->app->environment('production')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
            $this->app['request']->server->set('HTTPS', 'on');
            
            \Illuminate\Support\Facades\Request::setTrustedProxies(
                ['127.0.0.1', '10.0.0.0/8'], 
                \Illuminate\Http\Request::HEADER_X_FORWARDED_ALL
            );
        }

        // 2. Register the Resend API Transport
        Mail::extend('resend-api', function ($app) {
            return new class extends \Illuminate\Mail\Transport\Transport {
                public function send(\Swift_Mime_SimpleMessage $message, &$failedRecipients = null)
                {
                    $this->beforeSendPerformed($message);

                    $response = Http::withToken(env('RESEND_API_KEY'))
                        ->post('https://api.resend.com/emails', [
                            'from' => env('MAIL_FROM_ADDRESS', 'noreply@your-domain.com'),
                            'to' => array_keys($message->getTo()),
                            'subject' => $message->getSubject(),
                            'html' => $message->getBody(),
                        ]);

                    if ($response->failed()) {
                        throw new \Exception('Resend API Error: ' . $response->body());
                    }

                    $this->sendPerformed($message);
                    return $this->numberOfRecipients($message);
                }
            };
        });

        FacadesStorage::extend('sftp', function ($app, $config) {
            return new Filesystem(new SftpAdapter($config));
        });

        Paginator::useBootstrap();

        try {
            $settings = Settings::where('id', '1')->first();
            $terms =  TermsPrivacy::find(1);
            $moreset =  SettingsCont::find(1);

            View::share('settings', $settings);
            View::share('terms', $terms);
            View::share('moresettings', $moreset);
            View::share('mod', $settings ? $settings->modules : null);
        } catch (\Exception $e) {
            // Database might not be migrated yet, ignore during build
        }
    }
}