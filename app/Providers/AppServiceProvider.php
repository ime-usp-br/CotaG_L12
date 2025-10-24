<?php

namespace App\Providers;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Cota;
use App\Observers\PermissionObserver;
use App\Observers\RoleObserver;
use App\Policies\AuditPolicy;
use App\Policies\PermissionPolicy;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
use App\Policies\CotaPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use OwenIt\Auditing\Models\Audit;

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
        Password::defaults(function () {
            $rule = Password::min(8);

            return $rule->letters()
                ->mixedCase()
                ->numbers()
                ->symbols();
        });

        // Register policies for Filament
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);
        Gate::policy(Audit::class, AuditPolicy::class);
        Gate::policy(Cota::class, CotaPolicy::class);

        // Register observers for auditing Spatie models
        Role::observe(RoleObserver::class);
        Permission::observe(PermissionObserver::class);
    }
}
