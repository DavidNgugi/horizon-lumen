<?php

namespace Laravel\Horizon;

use Illuminate\Queue\QueueManager;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Horizon\Connectors\RedisConnector;
use Laravel\Horizon\Console\WorkCommand;

class HorizonServiceProvider extends ServiceProvider
{
    use EventMap, ServiceBindings;

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerEvents();
        $this->registerRoutes();
        $this->registerRedisAlias();
        $this->registerResources();
        $this->defineAssetPublishing();
    }

    /**
     * Register the Horizon job events.
     *
     * @return void
     */
    protected function registerEvents()
    {
        $events = $this->app->make(Dispatcher::class);

        foreach ($this->events as $event => $listeners) {
            foreach ($listeners as $listener) {
                $events->listen($event, $listener);
            }
        }
    }

    /**
     * Register the Horizon routes.
     *
     * @return void
     */
    protected function registerRoutes()
    {
        Route::group([
            'prefix'     => config('horizon.uri', 'horizon'),
            'namespace'  => 'Laravel\Horizon\Http\Controllers',
            'middleware' => config('horizon.middleware'),
        ], function () {
            require __DIR__ . '/../routes/web.php';
        });
    }

    /**
     * Register the Horizon resources.
     *
     * @return void
     */
    protected function registerResources()
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'horizon');
    }

    /**
     * Register redis factory.
     *
     * @return void
     */
    protected function registerRedisAlias()
    {
        $this->app->alias('redis', \Illuminate\Contracts\Redis\Factory::class);

        $this->app->make('redis');
    }

    /**
     * Define the asset publishing configuration.
     *
     * @return void
     */
    public function defineAssetPublishing()
    {
        $this->publishes([
            HORIZON_PATH . '/public' => base_path('public/vendor/horizon'),
        ], 'horizon-assets');
    }

    /**
     * Register the custom queue connectors for Horizon.
     *
     * @return void
     */
    protected function registerQueueConnectors()
    {
        $this->app->resolving(QueueManager::class, function ($manager) {
            $manager->addConnector('redis', function () {
                return new RedisConnector($this->app['redis']);
            });
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        if (!defined('HORIZON_PATH')) {
            define('HORIZON_PATH', realpath(__DIR__ . '/../'));
        }

        $this->configure();
        $this->offerPublishing();
        $this->registerServices();
        $this->registerQueueWorkCommand();
        $this->registerCommands();
        $this->registerQueueConnectors();
    }

    /**
     * Setup the configuration for Horizon.
     *
     * @return void
     */
    protected function configure()
    {
        $this->app->configure('horizon');
        $this->mergeConfigFrom(
            __DIR__ . '/../config/horizon.php', 'horizon'
        );

        Horizon::use(config('horizon.use'));
    }

    /**
     * Setup the resource publishing groups for Horizon.
     *
     * @return void
     */
    protected function offerPublishing()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/horizon.php' => app()->basePath('config/horizon.php'),
            ], 'horizon-config');
        }
    }

    /**
     * Register the command instance.
     *
     * @return void
     */
    protected function registerQueueWorkCommand()
    {
        $this->app->singleton(WorkCommand::class, function ($app) {
            return new WorkCommand($app['queue.worker']);
        });
    }

    /**
     * Register Horizon's services in the container.
     *
     * @return void
     */
    protected function registerServices()
    {
        foreach ($this->serviceBindings as $key => $value) {
            is_numeric($key)
                ? $this->app->singleton($value)
                : $this->app->singleton($key, $value);
        }
    }

    /**
     * Register the Horizon Artisan commands.
     *
     * @return void
     */
    protected function registerCommands()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\AssetsCommand::class,
                Console\HorizonCommand::class,
                Console\ListCommand::class,
                Console\PurgeCommand::class,
                Console\PauseCommand::class,
                Console\ContinueCommand::class,
                Console\SupervisorCommand::class,
                Console\SupervisorsCommand::class,
                Console\TerminateCommand::class,
                Console\TimeoutCommand::class,
                Console\WorkCommand::class,
            ]);
        }

        $this->commands([Console\SnapshotCommand::class]);
    }
}
