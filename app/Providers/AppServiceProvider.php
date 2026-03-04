<?php

namespace App\Providers;

use App\Models\OperacionAsistencia;
use App\Models\Transaccion;
use App\Observers\OperacionAsistenciaObserver;
use App\Observers\TransaccionObserver;
use Illuminate\Support\ServiceProvider;

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
        // Registrar observers para recalcular planillas automáticamente
        OperacionAsistencia::observe(OperacionAsistenciaObserver::class);
        Transaccion::observe(TransaccionObserver::class);
    }
}
