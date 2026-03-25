@extends('layouts.app')

@section('title', '分销商登录')

@section('body')
<div class="min-h-screen flex items-center justify-center bg-slate-50 px-4">
    <div class="w-full max-w-md">
        <div class="mb-8 text-center">
            <h1 class="text-2xl font-semibold text-slate-900">VPN SaaS</h1>
            <p class="mt-1 text-sm text-slate-500">分销商门户 · 登录</p>
        </div>

        <div class="console-card p-6 shadow-sm sm:p-8">
            <h2 class="mb-6 text-lg font-semibold text-slate-900">登录</h2>

            <form x-data="{ email: '', password: '', error: '', loading: false }"
                  @submit.prevent="
                    error = '';
                    if (!email || !password) { error = '请输入邮箱和密码'; return; }
                    loading = true;
                    fetch('/api/v1/reseller-portal/login', {
                      method: 'POST',
                      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                      body: JSON.stringify({ email, password })
                    })
                    .then(r => r.json().then(d => ({ ok: r.ok, data: d })))
                    .then(({ ok, data }) => {
                      loading = false;
                      const payload = (data && typeof data === 'object' && Object.prototype.hasOwnProperty.call(data, 'success') && Object.prototype.hasOwnProperty.call(data, 'data'))
                        ? data.data
                        : data;
                      if (ok) {
                        localStorage.setItem('reseller_token', payload?.token || '');
                        localStorage.setItem('reseller_name', payload?.reseller?.name || '');
                        localStorage.setItem('reseller_email', payload?.reseller?.email || '');
                        window.location.href = '/reseller';
                        return;
                      }
                      error = data.message || '登录失败';
                    })
                    .catch(e => { loading = false; error = e.message || '网络错误'; });
                  "
                  class="space-y-5">
                @csrf
                <div>
                    <label for="email" class="form-label">邮箱</label>
                    <input type="email" id="email" name="email" x-model="email" autocomplete="email"
                           placeholder="reseller@example.com"
                           class="console-input-field bg-white">
                </div>
                <div>
                    <label for="password" class="form-label">密码</label>
                    <input type="password" id="password" name="password" x-model="password" autocomplete="current-password"
                           placeholder="••••••••"
                           class="console-input-field bg-white">
                </div>

                <p x-show="error" x-text="error" class="text-sm text-red-600"></p>

                <button type="submit" :disabled="loading"
                        class="console-btn-primary w-full disabled:opacity-50">
                    <span x-show="!loading">登录</span>
                    <span x-show="loading">登录中…</span>
                </button>
            </form>

            <p class="mt-4 text-sm text-slate-600">
                还没有账号？
                <a href="{{ url('/reseller/register') }}" class="console-link font-medium">去注册</a>
            </p>
        </div>
    </div>
</div>

<script>
    // 若已登录，直接跳转
    (function () {
        const token = localStorage.getItem('reseller_token');
        if (token) {
            window.location.href = '/reseller';
        }
    })();
</script>
@endsection

