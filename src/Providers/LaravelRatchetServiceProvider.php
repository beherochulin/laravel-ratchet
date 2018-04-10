<?php
namespace Askedio\LaravelRatchet\Providers;

use Illuminate\Support\ServiceProvider;
use GrahamCampbell\Throttle\ThrottleServiceProvider;
use Askedio\LaravelRatchet\Console\Commands\RatchetServerCommand;

class LaravelRatchetServiceProvider extends ServiceProvider {
    public function register() {
        $this->app->register(ThrottleServiceProvider::class);

        $this->app->singleton('command.ratchet.serve', function () {
            return new RatchetServerCommand();
        });

        $this->commands('command.ratchet.serve');

        $this->mergeConfigFrom(__DIR__.'/../config/ratchet.php', 'ratchet');
    }
    public function boot() {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'ratchet');

        $this->publishes([
            __DIR__.'/../lang' => resource_path('lang/askedio/ratchet'),
        ]);
        $this->publishes([
             __DIR__.'/../config/ratchet.php' => config_path('ratchet.php'),
        ]);
    }
    public function provides() {
        return ['command.ratchet.serve'];
    }
}
