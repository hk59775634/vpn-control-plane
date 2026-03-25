<?php

use App\Http\Controllers\Admin\AdminViewController;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

Route::get('/install_agent.sh', function (): Response {
    $path = base_path('agent/scripts/install_agent.sh');
    if (! is_readable($path)) {
        abort(404, 'install_agent.sh missing');
    }

    $body = file_get_contents($path);
    $body = $body === false ? '' : $body;

    return response($body, 200, [
        'Content-Type' => 'text/x-shellscript; charset=UTF-8',
        'Cache-Control' => 'public, max-age=120',
    ]);
});

Route::get('/', function () {
    return redirect('/reseller');
});

// 管理后台（Blade + Tailwind + Alpine）
Route::get('/admin/login', [AdminViewController::class, 'login'])->name('admin.login');
Route::get('/admin', [AdminViewController::class, 'dashboard'])->name('admin.dashboard');
Route::get('/admin/', [AdminViewController::class, 'dashboard']);

// 用户中心（Blade）
Route::get('/user', fn () => view('user.dashboard'))->name('user.dashboard');
Route::get('/user/', fn () => redirect('/user'));

// 分销商门户（A 站 Web 页面：注册/登录/控制台）
Route::prefix('reseller')->group(function (): void {
    Route::get('/login', fn () => view('reseller.auth.login'));
    Route::get('/register', fn () => view('reseller.auth.register'));
    Route::get('/', fn () => view('reseller.dashboard'));
    Route::get('/dashboard', fn () => view('reseller.dashboard'));
});
