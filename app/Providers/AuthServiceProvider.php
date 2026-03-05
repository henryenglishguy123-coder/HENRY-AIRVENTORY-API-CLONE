<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use App\Models\Sales\Order\SalesOrder;
use App\Policies\Sales\Order\SalesOrderPolicy;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        SalesOrder::class => SalesOrderPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
        ResetPassword::createUrlUsing(function ($notifiable, string $token) {
            return route('admin.password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);
        });
    }
}
