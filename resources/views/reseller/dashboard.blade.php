@extends('layouts.app')

@section('title', '分销商门户')

@section('body')
<div class="flex min-h-screen flex-col bg-slate-50 font-sans" x-data="resellerPortal()" x-init="init()">
    <x-admin.topbar brand="分销商门户" :show-search="false">
        <x-admin.topbar-user-menu>
            <button type="button" @click="userMenuOpen = false; setTab('security')" class="block w-full px-3 py-2 text-left text-sm text-slate-300 hover:bg-slate-700 hover:text-white">
                修改密码
            </button>
            <button type="button" @click="userMenuOpen = false; logout()" class="block w-full border-t border-slate-700 px-3 py-2 text-left text-sm text-slate-300 hover:bg-slate-700 hover:text-white">
                退出登录
            </button>
        </x-admin.topbar-user-menu>
    </x-admin.topbar>
    <div class="flex min-h-0 flex-1 pt-14">
        {{-- 左侧栏 --}}
        <aside class="console-sidebar">
            <div class="console-sidebar-header">
                <span class="block text-white text-sm font-semibold">分销商门户</span>
                <p class="mt-0.5 text-xs text-slate-400" x-text="reseller?.name || '—'"></p>
            </div>

            <nav class="console-sidebar-nav">
                <a href="#"
                   class="console-sidebar-link block"
                   :class="{ 'active': tab === 'overview' }"
                   @click.prevent="setTab('overview'); loadOverview()">
                    <span>概览</span>
                </a>
                <a href="#"
                   class="console-sidebar-link block"
                   :class="{ 'active': tab === 'finance' }"
                   @click.prevent="setTab('finance'); loadFinance()">
                    <span>资金管理</span>
                </a>
                <a href="#"
                   class="console-sidebar-link block"
                   :class="{ 'active': tab === 'vpn_users' }"
                   @click.prevent="setTab('vpn_users'); loadVpnUsers()">
                    <span>用户管理</span>
                </a>
                <a href="#"
                   class="console-sidebar-link block"
                   :class="{ 'active': tab === 'api_keys' }"
                   @click.prevent="setTab('api_keys'); loadApiKeys()">
                    <span>API Key 管理</span>
                </a>
                <a href="#"
                   class="console-sidebar-link block"
                   :class="{ 'active': tab === 'security' }"
                   @click.prevent="setTab('security')">
                    <span>账户安全</span>
                </a>
            </nav>

        </aside>

        {{-- 主内容 --}}
        <div class="console-main">
            <header class="console-header">
                <h1 class="text-lg font-semibold text-slate-900" x-text="headerTitle()"></h1>
                <div class="flex items-center gap-3">
                    <a href="/reseller-portal/b/download" class="console-btn-secondary text-sm" style="padding:8px 12px; display:none;">
                        下载源码
                    </a>
                </div>
            </header>

            <div class="console-content">
                {{-- Toast --}}
                <x-admin.toast />

                {{-- 刚创建的 API Key 提示 --}}
                <div x-show="justCreatedApiKey" class="console-card p-4 mb-6" style="border-color:#bae6fd;">
                    <h3 class="font-semibold text-slate-900">已为你生成 API Key</h3>
                    <p class="mt-1 text-sm text-slate-600">
                        部署 B 站时请填入：`VPN_A_URL = A 站 URL`，`VPN_A_RESELLER_API_KEY = 此 API Key`。请妥善保存，后续列表只会显示预览。
                    </p>
                    <div class="mt-3 rounded-md border border-slate-200 bg-white p-3">
                        <div class="text-xs font-medium text-slate-500">A 站 URL</div>
                        <div class="mt-1 text-sm text-slate-900" x-text="aUrl"></div>
                    </div>
                    <div class="mt-3 flex items-center gap-2">
                        <input type="text" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" readonly :value="justCreatedApiKey">
                        <button type="button" class="console-btn-primary" @click="copyApiKey()">复制</button>
                    </div>
                    <button type="button" class="mt-3 console-link text-sm" @click="clearJustCreatedApiKey()">我已保存</button>
                </div>

                {{-- 概览 --}}
                <div x-show="tab === 'overview'" class="space-y-6">
                    <div class="console-card p-5">
                        <h2 class="text-lg font-semibold text-slate-900">个人信息</h2>
                        <p class="mt-1 text-sm text-slate-500">余额、销量与成本等关键财务指标（按你的 A 站分销商账号维度统计）。</p>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                        <div class="console-stat-card">
                            <p class="console-stat-label">余额</p>
                            <p class="console-stat-value accent" x-text="formatMoney(stats.balance_cents ?? 0)"></p>
                        </div>
                        <div class="console-stat-card">
                            <p class="console-stat-label">销量（收入流水）</p>
                            <p class="console-stat-value accent" x-text="(stats.income_records_count ?? 0) + ''"></p>
                            <p class="mt-1 text-[11px] text-zinc-500">
                                新购 <span x-text="stats.purchase_count ?? 0"></span> · 续费 <span x-text="stats.renew_count ?? 0"></span>
                            </p>
                            <p class="mt-1 text-[11px] text-zinc-500">
                                总订单数：<span x-text="stats.total_a_orders_count ?? 0"></span>
                            </p>
                        </div>
                        <div class="console-stat-card">
                            <p class="console-stat-label">总用户数</p>
                            <p class="console-stat-value accent" x-text="stats.total_vpn_users_count ?? 0"></p>
                            <p class="mt-1 text-[11px] text-zinc-500">当前有效 <span x-text="stats.active_vpn_users_count ?? 0"></span></p>
                        </div>
                        <div class="console-stat-card">
                            <p class="console-stat-label">总成本</p>
                            <p class="console-stat-value" x-text="formatMoney(stats.total_cost_cents ?? 0)"></p>
                        </div>
                        <div class="console-stat-card">
                            <p class="console-stat-label">总充值</p>
                            <p class="console-stat-value accent" x-text="formatMoney(stats.recharge_total_cents ?? 0)"></p>
                        </div>
                    </div>
                </div>

                {{-- 资金管理 --}}
                <div x-show="tab === 'finance'" class="space-y-6">
                    <div class="console-card p-5">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <h2 class="text-lg font-semibold text-slate-900">资金管理</h2>
                                <p class="mt-1 text-sm text-slate-500">查看余额、充值与资金流水。</p>
                            </div>
                            <div class="text-right">
                                <p class="text-xs font-medium uppercase tracking-wider text-slate-500">当前余额</p>
                                <p class="text-2xl font-semibold text-sky-600" x-text="formatMoney(stats.balance_cents ?? 0)"></p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                        <div class="console-card p-5 lg:col-span-1 space-y-6">
                            <div>
                                <h3 class="font-semibold text-slate-900">账户充值</h3>
                                <p class="mt-1 text-sm text-slate-600">填写金额（分）与备注；在线支付跳转易支付收银台，支付成功后由系统异步入账。</p>
                            </div>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700">金额（分，amount_cents）</label>
                                    <input type="number" min="1" step="1"
                                           class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                                           x-model.number="rechargeForm.amount_cents"
                                           placeholder="在线支付至少 100（1.00 元）">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700">备注（可选）</label>
                                    <input type="text"
                                           class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                                           x-model="rechargeForm.note"
                                           placeholder="订单/渠道/备注">
                                </div>
                                <div x-show="stats.epay_enabled && stats.epay_configured">
                                    <label class="block text-sm font-medium text-slate-700">支付方式</label>
                                    <select class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                                            x-model="rechargeForm.pay_type">
                                        <option value="">聚合收银台（可选微信/支付宝等）</option>
                                        <option value="alipay">支付宝</option>
                                        <option value="wxpay">微信支付</option>
                                        <option value="qqpay">QQ 钱包</option>
                                    </select>
                                </div>
                                <div class="flex flex-col gap-2" x-show="stats.epay_enabled && stats.epay_configured">
                                    <button type="button" class="console-btn-primary w-full" :disabled="rechargeForm.epayLoading"
                                            @click="epayRecharge()">
                                        <span x-show="!rechargeForm.epayLoading">易支付在线充值</span>
                                        <span x-show="rechargeForm.epayLoading">正在创建订单…</span>
                                    </button>
                                </div>
                                <p x-show="stats.epay_enabled === true && stats.epay_configured === false" class="text-xs text-amber-700">
                                    在线支付已开启但未完成配置，请联系管理员检查 EPAY_GATEWAY / EPAY_PID / EPAY_KEY。
                                </p>
                                <form x-show="stats.simulated_recharge_allowed" class="space-y-2 border-t border-slate-200 pt-4" @submit.prevent="recharge()">
                                    <p class="text-xs text-slate-500">以下为模拟入账（仅开发/内测；生产请在 .env 关闭 EPAY_ALLOW_SIMULATED_RECHARGE）。</p>
                                    <button type="submit" class="console-btn-secondary w-full" :disabled="rechargeForm.loading">
                                        <span x-show="!rechargeForm.loading">模拟充值（立即到账）</span>
                                        <span x-show="rechargeForm.loading">处理中…</span>
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="console-card p-5 lg:col-span-2">
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="font-semibold text-slate-900">资金流水</h3>
                                <button type="button" class="console-link text-sm" @click="loadFinance()">刷新</button>
                            </div>
                            <div class="console-table-wrap mt-4">
                                <div class="overflow-x-auto">
                                    <table class="console-table">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>类型</th>
                                                <th>金额</th>
                                                <th>余额后</th>
                                                <th>时间</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="t in transactions" :key="t.id">
                                                <tr>
                                                    <td x-text="t.id"></td>
                                                    <td x-text="t.type"></td>
                                                    <td x-text="formatMoney(t.amount_cents)"></td>
                                                    <td x-text="formatMoney(t.balance_after_cents)"></td>
                                                    <td x-text="t.created_at ? new Date(t.created_at).toLocaleString() : '-'"></td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                                <p x-show="transactions.length === 0" class="py-8 text-center text-slate-500">暂无流水</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 用户管理 --}}
                <div x-show="tab === 'vpn_users'" class="space-y-6">
                    <div class="console-card p-5">
                        <h2 class="text-lg font-semibold text-slate-900">用户管理（简版）</h2>
                        <p class="mt-1 text-sm text-slate-500">查看名下 VPN 用户信息；暂停/恢复为最简单的管理操作。</p>
                    </div>

                    <div class="console-card p-5">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <h3 class="font-semibold text-slate-900">VPN 用户列表</h3>
                                <select class="rounded border border-slate-300 bg-white px-2 py-1 text-sm"
                                        x-model="vpnUsersFilter.status"
                                        @change="loadVpnUsers()">
                                    <option value="">全部</option>
                                    <option value="active">active</option>
                                    <option value="suspended">suspended</option>
                                </select>
                            </div>
                            <button type="button" class="console-link text-sm" @click="loadVpnUsers()">刷新</button>
                        </div>

                        <div class="console-table-wrap mt-4">
                            <div class="overflow-x-auto">
                                <table class="console-table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>邮箱</th>
                                            <th>名称</th>
                                            <th>状态</th>
                                            <th>区域</th>
                                            <th>到期</th>
                                            <th>操作</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="u in vpnUsers" :key="u.id">
                                            <tr>
                                                <td x-text="u.id"></td>
                                                <td x-text="u.email"></td>
                                                <td x-text="u.name"></td>
                                                <td x-text="u.status"></td>
                                                <td x-text="u.region ?? '-'"></td>
                                                <td x-text="u.latest_order?.expires_at ? new Date(u.latest_order.expires_at).toLocaleString() : '-'"></td>
                                                <td>
                                                    <button type="button"
                                                            class="console-btn-secondary text-xs"
                                                            @click="setVpnUserStatus(u.id, u.status === 'active' ? 'suspended' : 'active')">
                                                        <span x-text="u.status === 'active' ? '暂停' : '恢复'"></span>
                                                    </button>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                            <p x-show="vpnUsers.length === 0" class="py-8 text-center text-slate-500">暂无 VPN 用户</p>
                        </div>
                    </div>
                </div>

                {{-- API Key 管理 --}}
                <div x-show="tab === 'api_keys'" class="space-y-6">
                    <div class="console-card p-5">
                        <h2 class="text-lg font-semibold text-slate-900">API Key 管理</h2>
                        <p class="mt-1 text-sm text-slate-500">创建用于销售/调用的 Key（完整值只返回一次）。</p>
                        <p class="mt-2 text-sm text-slate-600">
                            部署 B 站时：`VPN_A_URL = 本 A 站 URL`（页面当前域名）+ `VPN_A_RESELLER_API_KEY = 选择的 Key`。
                        </p>
                    </div>

                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                        <div class="console-card p-5 lg:col-span-1">
                            <h3 class="font-semibold text-slate-900">创建 API Key</h3>
                            <p class="mt-1 text-sm text-slate-600">可选：给 Key 起一个名称，便于区分。</p>

                            <form class="mt-4 space-y-4" @submit.prevent="createApiKey()">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700">名称（可选）</label>
                                    <input type="text"
                                           class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                                           x-model="apiKeyForm.name"
                                           placeholder="default / prod / channel-a">
                                </div>
                                <button type="submit" class="console-btn-primary w-full" :disabled="apiKeyForm.loading">
                                    <span x-show="!apiKeyForm.loading">生成</span>
                                    <span x-show="apiKeyForm.loading">生成中…</span>
                                </button>
                            </form>

                            <div x-show="lastCreatedApiKey" class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                                <h4 class="font-semibold text-emerald-900">新 Key 已生成</h4>
                                <p class="mt-1 text-sm text-emerald-800">请立即保存：</p>
                                <div class="mt-2 flex items-center gap-2">
                                    <input type="text" readonly class="w-full rounded-md border border-emerald-300 px-3 py-2 text-sm"
                                           :value="lastCreatedApiKey">
                                    <button type="button" class="console-btn-secondary" @click="copy(lastCreatedApiKey)">复制</button>
                                </div>
                            </div>

                            <div class="mt-4">
                                <h4 class="font-semibold text-slate-900">下载 B 站源码</h4>
                                <p class="mt-1 text-sm text-slate-600">用于部署你的分销商站点。</p>
                                <button type="button" class="console-btn-secondary w-full mt-3" @click="downloadB()">下载 ZIP</button>
                            </div>
                        </div>

                        <div class="console-card p-5 lg:col-span-2">
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="font-semibold text-slate-900">Key 列表</h3>
                                <button type="button" class="console-link text-sm" @click="loadApiKeys()">刷新</button>
                            </div>

                            <div class="console-table-wrap mt-4">
                                <div class="overflow-x-auto">
                                    <table class="console-table">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>名称</th>
                                                <th>API Key</th>
                                                <th>创建时间</th>
                                                <th>操作</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="k in apiKeys" :key="k.id">
                                                <tr>
                                                    <td x-text="k.id"></td>
                                                    <td x-text="k.name"></td>
                                                    <td class="max-w-[360px]">
                                                        <div class="flex items-center gap-2">
                                                            <input type="text"
                                                                   class="w-full rounded-md border border-slate-300 px-2 py-1 text-xs"
                                                                   readonly
                                                                   :value="k.api_key || ''"
                                                            />
                                                            <button type="button"
                                                                    class="console-btn-secondary text-xs whitespace-nowrap"
                                                                    @click="copy(k.api_key)">
                                                                复制
                                                            </button>
                                                        </div>
                                                    </td>
                                                    <td x-text="k.created_at ? new Date(k.created_at).toLocaleString() : '-'"></td>
                                                    <td>
                                                        <button type="button"
                                                                class="console-btn-danger text-xs"
                                                                @click="deleteApiKey(k.id)">
                                                            删除
                                                        </button>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                                <p x-show="apiKeys.length === 0" class="py-8 text-center text-slate-500">暂无 API Key</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 账户安全 / 修改密码 --}}
                <div x-show="tab === 'security'" class="space-y-6">
                    <div class="console-card p-5">
                        <h2 class="text-lg font-semibold text-slate-900">修改密码</h2>
                        <p class="mt-1 text-sm text-slate-500">请使用至少 8 位新密码；修改成功后，其他设备上的门户登录将失效（当前会话保留）。</p>
                    </div>

                    <div class="console-card max-w-lg p-5">
                        <form class="space-y-4" @submit.prevent="changePassword()">
                            <div>
                                <label class="block text-sm font-medium text-slate-700">当前密码</label>
                                <input type="password" autocomplete="current-password"
                                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                                       x-model="passwordForm.current_password"
                                       placeholder="请输入当前密码">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700">新密码</label>
                                <input type="password" autocomplete="new-password"
                                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                                       x-model="passwordForm.password"
                                       placeholder="至少 8 位">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700">确认新密码</label>
                                <input type="password" autocomplete="new-password"
                                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                                       x-model="passwordForm.password_confirmation"
                                       placeholder="再次输入新密码">
                            </div>
                            <button type="submit" class="console-btn-primary" :disabled="passwordForm.loading">
                                <span x-show="!passwordForm.loading">保存新密码</span>
                                <span x-show="passwordForm.loading">保存中…</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        if (typeof window.Alpine === 'undefined') return;
        window.Alpine.data('resellerPortal', () => ({
            tab: 'overview',
            userEmail: '',
            userMenuOpen: false,
            reseller: null,
            stats: {},
            transactions: [],
            vpnUsers: [],
            apiKeys: [],
            aUrl: '',

            justCreatedApiKey: null,
            lastCreatedApiKey: null,

            rechargeForm: { amount_cents: 1000, note: '', pay_type: '', loading: false, epayLoading: false },
            vpnUsersFilter: { status: '' },
            apiKeyForm: { name: '', loading: false },
            passwordForm: {
                current_password: '',
                password: '',
                password_confirmation: '',
                loading: false,
            },

            init() {
                const token = localStorage.getItem('reseller_token');
                if (!token) {
                    window.location.href = '/reseller/login';
                    return;
                }

                this.aUrl = window.location.origin;
                this.userEmail = localStorage.getItem('reseller_email') || '';

                this.justCreatedApiKey = localStorage.getItem('reseller_api_key_just_created');
                if (this.justCreatedApiKey) {
                    localStorage.removeItem('reseller_api_key_just_created');
                }

                this.loadOverview().then(() => {
                    const params = new URLSearchParams(window.location.search);
                    if (params.get('pay_return') === '1') {
                        this.setTab('finance');
                        this.loadFinance().then(() => {
                            this.showToast('支付返回', '若已付款，余额将由易支付异步通知入账，请稍后点击「刷新」查看。', 'info');
                        });
                        params.delete('pay_return');
                        const q = params.toString();
                        window.history.replaceState({}, '', window.location.pathname + (q ? ('?' + q) : ''));
                    }
                });
                // 默认懒加载：只在切换 tab 时加载对应数据
            },

            setTab(t) {
                this.tab = t;
            },

            headerTitle() {
                if (this.tab === 'overview') return '个人概览';
                if (this.tab === 'finance') return '资金管理';
                if (this.tab === 'vpn_users') return '用户管理';
                if (this.tab === 'api_keys') return 'API Key 管理';
                if (this.tab === 'security') return '账户安全';
                return '分销商门户';
            },

            api(path, { method = 'GET', body = null } = {}) {
                const token = localStorage.getItem('reseller_token');
                return fetch('/api/v1/reseller-portal' + path, {
                    method,
                    headers: {
                        'Accept': 'application/json',
                        'Authorization': 'Bearer ' + token,
                        'Content-Type': 'application/json',
                    },
                    body: body ? JSON.stringify(body) : null,
                }).then(async (res) => {
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        let msg = data.message || ('请求失败: ' + res.status);
                        if (data.errors && typeof data.errors === 'object') {
                            const first = Object.values(data.errors).flat().find(Boolean);
                            if (first) {
                                msg = first;
                            }
                        }
                        throw new Error(msg);
                    }
                    if (data && typeof data === 'object'
                        && Object.prototype.hasOwnProperty.call(data, 'success')
                        && Object.prototype.hasOwnProperty.call(data, 'data')) {
                        return data.data;
                    }
                    return data;
                });
            },

            formatMoney(cents) {
                const v = Number(cents || 0);
                // 口径：直接以 cents / 100 显示两位小数（币种由业务侧定义）
                return (v / 100).toFixed(2);
            },

            showToast(title, message, type = 'success') {
                try {
                    window.Alpine.store('toast')?.show(title, message, type);
                } catch (e) {}
            },

            async logout() {
                this.userMenuOpen = false;
                const token = localStorage.getItem('reseller_token');
                if (token) {
                    try {
                        await this.api('/logout', { method: 'POST' });
                    } catch (e) {
                        /* 仍清理本地 */
                    }
                }
                localStorage.removeItem('reseller_token');
                localStorage.removeItem('reseller_name');
                localStorage.removeItem('reseller_email');
                window.location.href = '/reseller/login';
            },

            async loadOverview() {
                try {
                    const [me, stats] = await Promise.all([
                        this.api('/me'),
                        this.api('/stats'),
                    ]);
                    this.reseller = me;
                    this.userEmail = (me && me.email) ? me.email : (this.userEmail || '');
                    this.stats = stats || {};
                } catch (e) {
                    this.showToast('加载失败', e.message, 'error');
                }
            },

            async loadFinance() {
                try {
                    const [stats, tx] = await Promise.all([
                        this.api('/stats'),
                        this.api('/balance/transactions?limit=50'),
                    ]);
                    this.stats = stats || {};
                    this.transactions = tx || [];
                } catch (e) {
                    this.showToast('加载失败', e.message, 'error');
                }
            },

            async recharge() {
                if (!this.rechargeForm.amount_cents || this.rechargeForm.amount_cents < 1) {
                    this.showToast('参数错误', '请输入正确的 amount_cents', 'error');
                    return;
                }
                this.rechargeForm.loading = true;
                try {
                    await this.api('/recharge', {
                        method: 'POST',
                        body: {
                            amount_cents: Math.floor(Number(this.rechargeForm.amount_cents)),
                            note: this.rechargeForm.note || null,
                        }
                    });
                    this.rechargeForm.loading = false;
                    this.rechargeForm.note = '';
                    await this.loadFinance();
                    this.showToast('充值成功', '余额已更新（模拟入账）。', 'success');
                } catch (e) {
                    this.rechargeForm.loading = false;
                    this.showToast('充值失败', e.message, 'error');
                }
            },

            async epayRecharge() {
                const c = Math.floor(Number(this.rechargeForm.amount_cents));
                if (!c || c < 100) {
                    this.showToast('参数错误', '在线充值金额至少为 100 分（1.00 元）', 'error');
                    return;
                }
                this.rechargeForm.epayLoading = true;
                try {
                    const data = await this.api('/recharge/epay', {
                        method: 'POST',
                        body: {
                            amount_cents: c,
                            note: this.rechargeForm.note || null,
                            pay_type: this.rechargeForm.pay_type || null,
                        },
                    });
                    if (data.pay_url) {
                        window.location.href = data.pay_url;
                        return;
                    }
                    throw new Error('未返回支付地址');
                } catch (e) {
                    this.rechargeForm.epayLoading = false;
                    this.showToast('创建支付失败', e.message, 'error');
                }
            },

            async loadVpnUsers() {
                try {
                    const qs = this.vpnUsersFilter.status ? ('?status=' + this.vpnUsersFilter.status) : '';
                    const url = '/vpn_users' + qs + (qs ? '&limit=200' : '?limit=200');
                    this.vpnUsers = await this.api(url).catch(() => []);
                } catch (e) {
                    this.showToast('加载失败', e.message, 'error');
                }
            },

            async setVpnUserStatus(vpnUserId, status) {
                try {
                    await this.api('/vpn_users/' + vpnUserId + '/status', {
                        method: 'PATCH',
                        body: { status }
                    });
                    await this.loadVpnUsers();
                    this.showToast('操作完成', 'VPN 用户状态已更新', 'success');
                } catch (e) {
                    this.showToast('操作失败', e.message, 'error');
                }
            },

            async loadApiKeys() {
                try {
                    this.apiKeys = await this.api('/api_keys').catch(() => []);
                } catch (e) {
                    this.showToast('加载失败', e.message, 'error');
                }
            },

            async createApiKey() {
                this.apiKeyForm.loading = true;
                this.lastCreatedApiKey = null;
                try {
                    const data = await this.api('/api_keys', {
                        method: 'POST',
                        body: { name: this.apiKeyForm.name || null }
                    });
                    this.apiKeyForm.loading = false;
                    this.lastCreatedApiKey = data.api_key || null;
                    await this.loadApiKeys();
                    this.showToast('生成成功', '请妥善保存该密钥（只返回一次）。', 'success');
                } catch (e) {
                    this.apiKeyForm.loading = false;
                    this.showToast('生成失败', e.message, 'error');
                }
            },

            async deleteApiKey(id) {
                try {
                    await this.api('/api_keys/' + id, { method: 'DELETE' });
                    await this.loadApiKeys();
                    this.showToast('删除成功', 'API Key 已删除。', 'success');
                } catch (e) {
                    this.showToast('删除失败', e.message, 'error');
                }
            },

            async downloadB() {
                try {
                    const token = localStorage.getItem('reseller_token');
                    const res = await fetch('/api/v1/reseller-portal/b/download', {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/octet-stream',
                            'Authorization': 'Bearer ' + token,
                        }
                    });
                    if (!res.ok) throw new Error('下载失败：HTTP ' + res.status);
                    const blob = await res.blob();
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'b_site_source.zip';
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                    window.URL.revokeObjectURL(url);
                } catch (e) {
                    this.showToast('下载失败', e.message, 'error');
                }
            },

            async copy(text) {
                try {
                    await navigator.clipboard.writeText(text);
                    this.showToast('已复制', '已复制到剪贴板', 'success');
                } catch (e) {
                    this.showToast('复制失败', e.message, 'error');
                }
            },

            async copyApiKey() {
                await this.copy(this.justCreatedApiKey || '');
            },

            clearJustCreatedApiKey() {
                this.justCreatedApiKey = null;
            },

            async changePassword() {
                const f = this.passwordForm;
                if (!f.current_password || !f.password || !f.password_confirmation) {
                    this.showToast('请填写完整', '请填写当前密码与新密码', 'error');
                    return;
                }
                if (f.password !== f.password_confirmation) {
                    this.showToast('校验失败', '两次输入的新密码不一致', 'error');
                    return;
                }
                f.loading = true;
                try {
                    await this.api('/password', {
                        method: 'PATCH',
                        body: {
                            current_password: f.current_password,
                            password: f.password,
                            password_confirmation: f.password_confirmation,
                        },
                    });
                    f.current_password = '';
                    f.password = '';
                    f.password_confirmation = '';
                    this.showToast('已更新', '密码已修改，其他设备的门户登录已失效。', 'success');
                } catch (e) {
                    this.showToast('修改失败', e.message, 'error');
                } finally {
                    f.loading = false;
                }
            },
        }));
    });
</script>
@endsection

