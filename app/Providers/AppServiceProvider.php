<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        $this->configureDefaults();
        $this->configureLocalServer();
    }

    /**
     * Let `artisan serve` keep the environment PHP needs to accept uploads.
     *
     * ServeCommand strips every environment variable that is not on its
     * passthrough list. On Windows that leaves the PHP child process with no
     * TMP or TEMP, so PHP cannot create the temporary file that every upload
     * needs and fails it with UPLOAD_ERR_NO_TMP_DIR — whatever the file's
     * size, and long before validation runs. Nginx/php-fpm in production is
     * unaffected, which is why this only ever bites locally.
     */
    protected function configureLocalServer(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        foreach (['TMP', 'TEMP'] as $variable) {
            if (! in_array($variable, ServeCommand::$passthroughVariables, true)) {
                ServeCommand::$passthroughVariables[] = $variable;
            }
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
