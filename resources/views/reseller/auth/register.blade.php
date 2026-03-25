@extends('layouts.app')

@section('title', '分销商注册')

@section('body')
<div class="min-h-screen flex items-center justify-center bg-slate-50 px-4">
    <div class="w-full max-w-md">
        <div class="mb-8 text-center">
            <h1 class="text-2xl font-semibold text-slate-900">VPN SaaS</h1>
            <p class="mt-1 text-sm text-slate-500">分销商门户 · 注册</p>
        </div>

        <div class="console-card p-6 shadow-sm sm:p-8">
            <h2 class="mb-6 text-lg font-semibold text-slate-900">创建分销商账号</h2>

            <form x-data="{ name: '', email: '', password: '', error: '', loading: false }"
                  @submit.prevent="
                    error = '';
                    if (!name || !email || !password) { error = '请完整填写信息'; return; }
                    loading = true;
                    fetch('/api/v1/reseller-portal/register', {
                      method: 'POST',
                      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                      body: JSON.stringify({ name, email, password })
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
                        if (payload?.api_key) localStorage.setItem('reseller_api_key_just_created', payload.api_key);
                        window.location.href = '/reseller?just_created=1';
                        return;
                      }
                      error = data.message || '注册失败';
                    })
                    .catch(e => { loading = false; error = e.message || '网络错误'; });
                  "
                  class="space-y-5">
                @csrf
                <div>
                    <label for="name" class="form-label">分销商名称</label>
                    <input type="text" id="name" name="name" x-model="name" autocomplete="organization"
                           placeholder="例如：XX代理"
                           class="console-input-field bg-white">
                </div>
                <div>
                    <label for="email" class="form-label">邮箱</label>
                    <input type="email" id="email" name="email" x-model="email" autocomplete="email"
                           placeholder="reseller@example.com"
                           class="console-input-field bg-white">
                </div>
                <div>
                    <label for="password" class="form-label">密码</label>
                    <input type="password" id="password" name="password" x-model="password" autocomplete="new-password"
                           placeholder="至少 8 位"
                           class="console-input-field bg-white">
                </div>

                <p x-show="error" x-text="error" class="text-sm text-red-600"></p>

                <button type="submit" :disabled="loading"
                        class="console-btn-primary w-full disabled:opacity-50">
                    <span x-show="!loading">注册并生成 API Key</span>
                    <span x-show="loading">创建中…</span>
                </button>
            </form>

            <p class="mt-4 text-sm text-slate-600">
                已有账号？
                <a href="{{ url('/reseller/login') }}" class="console-link font-medium">去登录</a>
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

