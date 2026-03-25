@extends('layouts.admin')

@section('title', '管理后台')

@section('sidebar')
<div class="mt-1 space-y-1">
    <button type="button" class="flex w-full items-center justify-between px-3 py-1.5 text-xs font-medium uppercase tracking-wider text-slate-400 hover:text-slate-200" @click="toggleSidebarGroup('overview')">
        <span>概览</span><span x-text="sidebarGroupsOpen.overview ? '−' : '+'"></span>
    </button>
    <div x-show="sidebarGroupsOpen.overview">
        <a href="#" class="console-sidebar-link" :class="{ 'active': tab === 'overview' }" @click.prevent="setTab('overview'); loadOverview()">控制台</a>
        <a href="#" class="console-sidebar-link" :class="{ 'active': tab === 'analytics' }" @click.prevent="setTab('analytics'); loadAnalytics()">数据分析</a>
    </div>

    <button type="button" class="mt-2 flex w-full items-center justify-between px-3 py-1.5 text-xs font-medium uppercase tracking-wider text-slate-400 hover:text-slate-200" @click="toggleSidebarGroup('business')">
        <span>业务</span><span x-text="sidebarGroupsOpen.business ? '−' : '+'"></span>
    </button>
    <div x-show="sidebarGroupsOpen.business">
        <a href="#" class="console-sidebar-link" :class="{ 'active': tab === 'products' }" @click.prevent="setTab('products'); loadProducts()">产品与定价</a>
        <a href="#" class="console-sidebar-link" :class="{ 'active': tab === 'orders' }" @click.prevent="setTab('orders'); loadOrders(); loadUsers(); loadProducts()">订单</a>
    </div>

    <button type="button" class="mt-2 flex w-full items-center justify-between px-3 py-1.5 text-xs font-medium uppercase tracking-wider text-slate-400 hover:text-slate-200" @click="toggleSidebarGroup('infra')">
        <span>基础设施</span><span x-text="sidebarGroupsOpen.infra ? '−' : '+'"></span>
    </button>
    <div x-show="sidebarGroupsOpen.infra">
        <a href="#" class="console-sidebar-link" :class="{ 'active': tab === 'servers' }" @click.prevent="setTab('servers'); loadServers(); editingServer = null">接入服务器</a>
        <a href="#" class="console-sidebar-link" :class="{ 'active': tab === 'ip_pool' }" @click.prevent="setTab('ip_pool'); loadIPPool()">IP 池</a>
        <a href="#" class="console-sidebar-link" :class="{ 'active': tab === 'snat_maps' }" @click.prevent="setTab('snat_maps'); loadSnatMaps()">SNAT 映射表</a>
        <a href="#" class="console-sidebar-link" :class="{ 'active': tab === 'provision_audit' }" @click.prevent="setTab('provision_audit'); loadProvisionAuditLogs()">资源审计</a>
    </div>

    <button type="button" class="mt-2 flex w-full items-center justify-between px-3 py-1.5 text-xs font-medium uppercase tracking-wider text-slate-400 hover:text-slate-200" @click="toggleSidebarGroup('reseller')">
        <span>分销</span><span x-text="sidebarGroupsOpen.reseller ? '−' : '+'"></span>
    </button>
    <div x-show="sidebarGroupsOpen.reseller">
        <a href="#" class="console-sidebar-link" :class="{ 'active': tab === 'resellers' }" @click.prevent="setTab('resellers'); loadResellers()">分销商</a>
        <a href="#" class="console-sidebar-link" :class="{ 'active': tab === 'vpn_accounts' }" @click.prevent="setTab('vpn_accounts'); loadResellers(); loadVpnAccounts()">用户管理</a>
        <a href="#" class="console-sidebar-link" :class="{ 'active': tab === 'purchased_products' }" @click.prevent="setTab('purchased_products'); loadResellers(); loadPurchasedProducts()">已购产品</a>
    </div>

    <button type="button" class="mt-2 flex w-full items-center justify-between px-3 py-1.5 text-xs font-medium uppercase tracking-wider text-slate-400 hover:text-slate-200" @click="toggleSidebarGroup('support')">
        <span>支持</span><span x-text="sidebarGroupsOpen.support ? '−' : '+'"></span>
    </button>
    <div x-show="sidebarGroupsOpen.support">
        <a href="#" class="console-sidebar-link" :class="{ 'active': tab === 'users' }" @click.prevent="setTab('users'); loadUsers()">管理员列表</a>
        <a href="#" class="console-sidebar-link" :class="{ 'active': tab === 'security' }" @click.prevent="setTab('security')">账户安全</a>
        <a href="#" class="console-sidebar-link" :class="{ 'active': tab === 'payment_settings' }" @click.prevent="setTab('payment_settings'); loadPaymentSettings()">支付设置</a>
        <a href="#" class="console-sidebar-link" :class="{ 'active': tab === 'runtime_settings' }" @click.prevent="setTab('runtime_settings'); loadRuntimeSettings()">安全与限流</a>
        <a href="#" class="console-sidebar-link" :class="{ 'active': tab === 'help' }" @click.prevent="setTab('help')">帮助与常见问题</a>
    </div>
</div>
@endsection

@section('header_title', '')
@section('header_actions', '')

@section('content')
<div class="space-y-6">
    {{-- 控制台概览（参考 VPN Reseller 控制台） --}}
    <div x-show="tab === 'overview'" class="space-y-6">
        <div class="console-card p-5">
            <h2 class="text-lg font-semibold text-slate-900">云控制台概览</h2>
            <p class="mt-1 text-sm text-slate-500">监控服务器、用户、带宽与会话等关键运行指标。</p>
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="console-stat-card">
                <p class="console-stat-label">服务器总数</p>
                <p class="console-stat-value accent" x-text="summary.server_count ?? summary.serverCount ?? '0'"></p>
            </div>
            <div class="console-stat-card">
                <p class="console-stat-label">业务流水（唯一单号）</p>
                <p class="console-stat-value accent" x-text="summary.income_records_count ?? '0'"></p>
                <p class="mt-1 text-[11px] text-zinc-500">新购 <span x-text="summary.income_purchase_count ?? 0"></span> · 续费 <span x-text="summary.income_renew_count ?? 0"></span></p>
            </div>
            <div class="console-stat-card">
                <p class="console-stat-label">管理员 / 平台账号数</p>
                <p class="console-stat-value accent" x-text="summary.active_user_count ?? summary.activeUsers ?? summary.user_count ?? summary.userCount ?? '0'"></p>
            </div>
            <div class="console-stat-card">
                <p class="console-stat-label">平台成本（服务器/NAT，本月累计）</p>
                <p class="console-stat-value" x-text="((Number(analytics?.stats?.platform_cost_cents ?? 0)/100).toFixed(2)) + ' 元'"></p>
            </div>
            <div class="console-stat-card">
                <p class="console-stat-label">销售金额（扣款口径，本月累计）</p>
                <p class="console-stat-value accent" x-text="((Number(analytics?.stats?.sales_total_cents ?? 0)/100).toFixed(2)) + ' 元'"></p>
            </div>
            <div class="console-stat-card">
                <p class="console-stat-label">利润（本月累计）</p>
                <p class="console-stat-value" x-text="((Number(analytics?.stats?.profit_total_cents ?? 0)/100).toFixed(2)) + ' 元'"></p>
                <p class="mt-1 text-[11px] text-zinc-500" x-text="((Number(analytics?.stats?.profit_margin_rate ?? 0) * 100).toFixed(2)) + '%'"></p>
            </div>
            <div class="console-stat-card">
                <p class="console-stat-label">现金覆盖（充值 - 平台成本，本月累计）</p>
                <p class="console-stat-value accent" x-text="((Number(analytics?.stats?.cash_coverage_total_cents ?? 0)/100).toFixed(2)) + ' 元'"></p>
                <p class="mt-1 text-[11px] text-zinc-500" x-text="(Number(analytics?.stats?.cash_coverage_total_cents ?? 0) >= 0 ? '现金覆盖' : '现金不足')"></p>
            </div>
        </div>
        <div class="flex flex-wrap gap-3">
            <button type="button" @click="tab = 'vpn_accounts'; loadResellers(); loadVpnAccounts()" class="console-btn-primary">用户管理</button>
            <button type="button" @click="tab = 'users'; loadUsers()" class="console-btn-secondary">管理员列表</button>
            <button type="button" @click="tab = 'products'; loadProducts()" class="console-btn-secondary">产品与定价</button>
            <button type="button" @click="tab = 'servers'; loadServers(); editingServer = null" class="console-btn-secondary">接入服务器列表</button>
            <button type="button" @click="tab = 'orders'; loadOrders()" class="console-btn-secondary">订单</button>
        </div>
        <div class="console-table-wrap">
            <div class="console-card-header">
                <h3 class="console-card-title">最近服务器</h3>
                <button type="button" @click="tab = 'servers'; loadServers(); editingServer = null" class="console-link text-sm">查看全部</button>
            </div>
            <div class="overflow-x-auto">
                <table class="console-table">
                    <thead>
                        <tr>
                            <th>主机名</th>
                            <th>区域</th>
                            <th>角色</th>
                            <th>Agent</th>
                            <th>版本</th>
                            <th>心跳</th>
                            <th>配置</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="s in servers.slice(0, 5)" :key="s.id">
                            <tr>
                                <td x-text="s.hostname"></td>
                                <td x-text="s.region"></td>
                                <td x-text="s.role"></td>
                                <td x-text="agentRuntimeLabel(s)"></td>
                                <td class="font-mono text-xs" x-text="s.agent_version || '—'"></td>
                                <td x-text="formatServerHeartbeat(s)"></td>
                                <td class="text-xs" x-text="configSyncUi(s).badge"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <p x-show="servers.length === 0 && !loading" class="py-8 text-center text-slate-500">暂无接入服务器，请先在「接入服务器」中添加节点。</p>
        </div>
    </div>

    {{-- 数据分析 --}}
    <div x-show="tab === 'analytics'" class="space-y-6">
        <div class="console-card p-5">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">数据分析</h2>
                    <p class="mt-1 text-sm text-slate-500">基于当前 A 站数据统计：<strong>管理员（平台账号）数</strong>、<strong>分销商终端用户（按邮箱+分销商去重）</strong>、订单、成本与充值、Top 排名。</p>
                </div>
                <button type="button" class="console-btn-secondary text-sm" @click="loadAnalytics()">
                    刷新
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="console-stat-card">
                <p class="console-stat-label">服务器总数</p>
                <p class="console-stat-value accent" x-text="analytics?.stats?.server_count ?? 0"></p>
            </div>
            <div class="console-stat-card">
                <p class="console-stat-label">管理员 / 平台账号数</p>
                <p class="console-stat-value accent" x-text="analytics?.stats?.platform_user_count ?? 0"></p>
            </div>
            <div class="console-stat-card">
                <p class="console-stat-label">VPN 用户数（B 终端·去重）</p>
                <p class="console-stat-value accent" x-text="analytics?.stats?.vpn_user_count ?? 0"></p>
                <p class="mt-1 text-[11px] text-zinc-500">
                    其中活跃 <span x-text="analytics?.stats?.active_vpn_user_count ?? 0"></span>
                </p>
            </div>
            <div class="console-stat-card">
                <p class="console-stat-label">订单总数</p>
                <p class="console-stat-value accent" x-text="analytics?.stats?.orders_total_count ?? 0"></p>
                <p class="mt-1 text-[11px] text-zinc-500">
                    活跃 <span x-text="analytics?.stats?.active_orders_count ?? 0"></span>
                </p>
            </div>
            <div class="console-stat-card">
                <p class="console-stat-label">平台成本（服务器/NAT，本月累计）</p>
                <p class="console-stat-value" x-text="((Number(analytics?.stats?.platform_cost_cents ?? 0)/100).toFixed(2)) + ' 元'"></p>
            </div>
            <div class="console-stat-card">
                <p class="console-stat-label">本月充值（入账口径）</p>
                <p class="console-stat-value accent" x-text="((Number(analytics?.stats?.recharge_total_cents ?? 0)/100).toFixed(2)) + ' 元'"></p>
            </div>
            <div class="console-stat-card">
                <p class="console-stat-label">收入流水（新购）</p>
                <p class="console-stat-value accent" x-text="analytics?.stats?.income_purchase_count ?? 0"></p>
            </div>
            <div class="console-stat-card">
                <p class="console-stat-label">收入流水（续费）</p>
                <p class="console-stat-value accent" x-text="analytics?.stats?.income_renew_count ?? 0"></p>
            </div>

            <div class="console-stat-card">
                <p class="console-stat-label">销售金额（扣款口径，本月累计）</p>
                <p class="console-stat-value accent" x-text="((Number(analytics?.stats?.sales_total_cents ?? 0)/100).toFixed(2)) + ' 元'"></p>
            </div>
            <div class="console-stat-card">
                <p class="console-stat-label">利润（销售 - 平台成本，本月累计）</p>
                <p class="console-stat-value" x-text="((Number(analytics?.stats?.profit_total_cents ?? 0)/100).toFixed(2)) + ' 元'"></p>
                <p class="mt-1 text-[11px] text-zinc-500" x-text="(Number(analytics?.stats?.profit_total_cents ?? 0) >= 0 ? '盈利' : '亏损')"></p>
                <p class="mt-0.5 text-[11px] text-zinc-500" x-text="((Number(analytics?.stats?.profit_margin_rate ?? 0) * 100).toFixed(2)) + '%'"></p>
            </div>
            <div class="console-stat-card">
                <p class="console-stat-label">现金覆盖（充值 - 平台成本，本月累计）</p>
                <p class="console-stat-value accent" x-text="((Number(analytics?.stats?.cash_coverage_total_cents ?? 0)/100).toFixed(2)) + ' 元'"></p>
                <p class="mt-1 text-[11px] text-zinc-500" x-text="(Number(analytics?.stats?.cash_coverage_total_cents ?? 0) >= 0 ? '现金覆盖' : '现金不足')"></p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div class="console-card p-5">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="console-card-title">Top 分销商（按销售金额）</h3>
                    <button type="button" class="console-link text-sm" @click="loadAnalytics()">刷新</button>
                </div>
                <div class="console-table-wrap mt-4">
                    <div class="overflow-x-auto">
                        <table class="console-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>名称</th>
                                    <th>销售金额</th>
                                    <th>充值</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="r in (analytics?.top_resellers || [])" :key="r.reseller_id">
                                    <tr>
                                        <td class="py-3 px-4" x-text="r.reseller_id"></td>
                                        <td class="py-3 px-4" x-text="r.name"></td>
                                        <td class="py-3 px-4" x-text="((Number(r.sales_cents||0)/100).toFixed(2)) + ' 元'"></td>
                                        <td class="py-3 px-4" x-text="((Number(r.recharge_cents||0)/100).toFixed(2)) + ' 元'"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                    <p x-show="(analytics?.top_resellers || []).length === 0" class="py-8 text-center text-slate-500">
                        暂无数据
                    </p>
                </div>
            </div>

            <div class="console-card p-5">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="console-card-title">Top 产品（按开通次数）</h3>
                    <button type="button" class="console-link text-sm" @click="loadAnalytics()">刷新</button>
                </div>
                <div class="console-table-wrap mt-4">
                    <div class="overflow-x-auto">
                        <table class="console-table">
                            <thead>
                                <tr>
                                    <th>产品ID</th>
                                    <th>名称</th>
                                    <th>开通次数</th>
                                    <th>销售金额</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="p in (analytics?.top_products || [])" :key="p.product_id">
                                    <tr>
                                        <td class="py-3 px-4" x-text="p.product_id"></td>
                                        <td class="py-3 px-4" x-text="p.name"></td>
                                        <td class="py-3 px-4" x-text="p.open_count"></td>
                                        <td class="py-3 px-4" x-text="((Number(p.sales_cents||0)/100).toFixed(2)) + ' 元'"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                    <p x-show="(analytics?.top_products || []).length === 0" class="py-8 text-center text-slate-500">
                        暂无数据
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- 管理员列表（登录本控制面的平台账号） --}}
    <div x-show="tab === 'users'" class="space-y-4">
        <div class="console-card p-4">
            <p class="text-sm text-zinc-500">管理可登录本控制面的<strong>平台账号</strong>及其角色（user / admin）。可修改角色、重置密码；通过「订单」可为平台用户开通 VPN 套餐（与分销商终端「用户管理」中的记录不同）。</p>
        </div>
        <div class="console-card overflow-hidden">
            <div class="px-5 py-3 border-b border-zinc-100 flex items-center justify-between gap-3">
                <h3 class="font-medium text-zinc-800">管理员列表</h3>
                <button type="button" class="console-btn-primary text-sm" @click="addAdminUser()">
                    添加管理员账户
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-zinc-50 text-zinc-600">
                        <tr>
                            <th class="text-left py-3 px-4">ID</th>
                            <th class="text-left py-3 px-4">邮箱</th>
                            <th class="text-left py-3 px-4">角色</th>
                            <th class="text-left py-3 px-4">创建时间</th>
                            <th class="text-left py-3 px-4">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="u in users" :key="u.id">
                            <tr class="border-t border-zinc-100 hover:bg-zinc-50/50">
                                <td class="py-3 px-4" x-text="u.id"></td>
                                <td class="py-3 px-4" x-text="u.email"></td>
                                <td class="py-3 px-4">
                                    <select class="rounded border border-zinc-300 text-sm py-1 px-2" :value="u.role || 'user'" @change="updateUserRole(u.id, $event.target.value)">
                                        <option value="user">user</option>
                                        <option value="admin">admin</option>
                                    </select>
                                </td>
                                <td class="py-3 px-4" x-text="u.created_at ? new Date(u.created_at).toLocaleString() : '-'"></td>
                                <td class="py-3 px-4">
                                    <div class="flex gap-2">
                                        <button type="button"
                                                class="console-link text-xs"
                                                @click="resetUserPassword(u.id)">
                                            编辑/重置密码
                                        </button>
                                        <button type="button"
                                                class="console-link text-xs text-red-600"
                                                @click="deleteUser(u.id)">
                                            删除
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- 用户管理：仅 B 站（分销商）终端注册用户，同一邮箱+分销商多订阅不重复列出 --}}
    <div x-show="tab === 'vpn_accounts'" class="space-y-4">
        <div class="console-card p-5">
            <h2 class="text-lg font-semibold text-slate-900">用户管理</h2>
            <p class="mt-1 text-sm text-slate-500">
                仅展示通过分销商同步/开通的终端用户（<code class="rounded bg-slate-100 px-1 text-xs">reseller_id</code> 非空），并按 <strong>邮箱 + 分销商</strong> 去重；不因多笔已购订阅而重复出现。最近购买/到期取该用户下最新一条分销订单。
            </p>
        </div>
        <div class="console-card p-4">
            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <div class="text-xs text-zinc-500 mb-1">搜索（邮箱/昵称）</div>
                    <input type="text" class="console-filter-input max-w-xs"
                           placeholder="例如：demo123@test.com"
                           x-model="vpnFilter.q">
                </div>
                <div>
                    <div class="text-xs text-zinc-500 mb-1">区域</div>
                    <select class="rounded border border-zinc-300 text-sm py-2 px-2"
                            x-model="vpnFilter.region">
                        <option value="">全部</option>
                        <template x-for="r in vpnRegions()" :key="r">
                            <option :value="r" x-text="r"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <div class="text-xs text-zinc-500 mb-1">分销商</div>
                    <select class="rounded border border-zinc-300 text-sm py-2 px-2"
                            x-model="vpnFilter.reseller_id">
                        <option value="">全部</option>
                        <template x-for="r in resellers" :key="r.id">
                            <option :value="String(r.id)" x-text="r.name + ' (#' + r.id + ')'"></option>
                        </template>
                    </select>
                </div>
                <div class="flex-1"></div>
                <button type="button" class="console-btn-secondary"
                        @click="vpnFilter = { q: '', region: '', reseller_id: '' }">
                    清空筛选
                </button>
            </div>
        </div>
        <div class="console-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm" id="vpn-table">
                    <thead class="bg-zinc-50 text-zinc-600">
                        <tr>
                            <th class="text-left py-3 px-4">用户</th>
                            <th class="text-left py-3 px-4">注册时间</th>
                            <th class="text-left py-3 px-4">区域</th>
                            <th class="text-left py-3 px-4">最近订单</th>
                            <th class="text-left py-3 px-4">最近购买产品</th>
                            <th class="text-left py-3 px-4">到期时间</th>
                            <th class="text-left py-3 px-4">状态</th>
                            <th class="text-left py-3 px-4">分销商</th>
                            <th class="text-left py-3 px-4">账号数</th>
                            <th class="text-left py-3 px-4">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="v in filteredVpnAccounts()" :key="v.vpn_user_id || v.user_id">
                            <tr class="border-t border-zinc-100 hover:bg-zinc-50/50"
                                :data-id="v.vpn_user_id || ''"
                                :data-user_id="v.user_id">
                                <td class="py-3 px-4">
                                    <div class="flex flex-col">
                                        <span x-text="v.user_name || v.user_email || (v.user_id ? ('UID:' + v.user_id) : '—')"></span>
                                        <span class="mt-0.5 text-[11px] text-zinc-500" x-text="v.user_email || '-'"></span>
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <span x-text="v.registered_at ? new Date(v.registered_at).toLocaleString() : '-'"></span>
                                </td>
                                <td class="py-3 px-4" x-text="v.region || '-'"></td>
                                <td class="py-3 px-4">
                                    <div class="flex flex-col">
                                        <span x-text="v.last_order_id ? ('#' + v.last_order_id) : '—'"></span>
                                        <span class="mt-0.5 text-[11px] text-zinc-500" x-text="v.last_order_status || ''"></span>
                                    </div>
                                </td>
                                <td class="py-3 px-4" x-text="v.last_product_name || '—'"></td>
                                <td class="py-3 px-4" x-text="v.last_expires_at ? new Date(v.last_expires_at).toLocaleString() : '-'"></td>
                                <td class="py-3 px-4">
                                    <span x-show="v.entitled === true" class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700">
                                        在用
                                    </span>
                                    <span x-show="v.entitled === false" class="inline-flex items-center rounded-full bg-zinc-50 px-2 py-0.5 text-[11px] font-medium text-zinc-500">
                                        已过期
                                    </span>
                                    <span x-show="v.entitled === null" class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-700">
                                        未购买
                                    </span>
                                </td>
                                <td class="py-3 px-4" x-text="v.reseller_name || '-'"></td>
                                <td class="py-3 px-4" x-text="v.sibling_count || 1"></td>
                                <td class="py-3 px-4 flex gap-2">
                                    <button type="button"
                                            class="console-link text-xs vpn-view"
                                            x-show="v.vpn_user_id">
                                        查看/编辑
                                    </button>
                                    <button type="button"
                                            class="console-link text-xs text-red-600 vpn-delete"
                                            :data-id="v.vpn_user_id || ''"
                                            :data-user_id="v.user_id || 0"
                                            x-show="v.vpn_user_id">
                                        删除
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <p x-show="filteredVpnAccounts().length === 0 && tab === 'vpn_accounts' && !loading" class="py-6 text-center text-zinc-500 text-sm">暂无匹配的终端用户</p>
        </div>
    </div>

    {{-- 已购产品：按 A 站分销订阅订单（与 B 站 a_order_id 一一对应），对齐 B 站已购列表含义 --}}
    <div x-show="tab === 'purchased_products'" class="space-y-4">
        <div class="console-card p-5">
            <h2 class="text-lg font-semibold text-slate-900">已购产品</h2>
            <p class="mt-1 text-sm text-slate-500">
                每条记录对应一条通过分销商开通的 <strong>A 站订阅订单</strong>（B 站「已购产品」中的 A 站订单号即下列「A 订单 ID」）。详情中可查看该终端账号并复制 WireGuard 配置。
            </p>
        </div>
        <div class="console-card p-4">
            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <div class="text-xs text-zinc-500 mb-1">搜索</div>
                    <input type="text" class="console-filter-input max-w-xs"
                           placeholder="邮箱、产品、业务单号、订单号…"
                           x-model="vpnFilter.q">
                </div>
                <div>
                    <div class="text-xs text-zinc-500 mb-1">区域</div>
                    <select class="rounded border border-zinc-300 text-sm py-2 px-2"
                            x-model="vpnFilter.region">
                        <option value="">全部</option>
                        <template x-for="r in purchasedRegions()" :key="'p-'+r">
                            <option :value="r" x-text="r"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <div class="text-xs text-zinc-500 mb-1">分销商</div>
                    <select class="rounded border border-zinc-300 text-sm py-2 px-2"
                            x-model="vpnFilter.reseller_id">
                        <option value="">全部</option>
                        <template x-for="r in resellers" :key="r.id">
                            <option :value="String(r.id)" x-text="r.name + ' (#' + r.id + ')'"></option>
                        </template>
                    </select>
                </div>
                <div class="flex-1"></div>
                <button type="button" class="console-btn-secondary"
                        @click="vpnFilter = { q: '', region: '', reseller_id: '' }">
                    清空筛选
                </button>
            </div>
        </div>
        <div class="console-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm" id="purchased-table">
                    <thead class="bg-zinc-50 text-zinc-600">
                        <tr>
                            <th class="text-left py-3 px-4">A 订单 ID</th>
                            <th class="text-left py-3 px-4">终端用户</th>
                            <th class="text-left py-3 px-4">产品</th>
                            <th class="text-left py-3 px-4">区域</th>
                            <th class="text-left py-3 px-4">B 业务单号</th>
                            <th class="text-left py-3 px-4">订单状态</th>
                            <th class="text-left py-3 px-4">到期</th>
                            <th class="text-left py-3 px-4">在用</th>
                            <th class="text-left py-3 px-4">收入流水</th>
                            <th class="text-left py-3 px-4">分销商</th>
                            <th class="text-left py-3 px-4">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="o in filteredPurchasedProducts()" :key="'po-'+o.id">
                            <tr class="border-t border-zinc-100 hover:bg-zinc-50/50"
                                :data-id="o.vpn_user_id || ''"
                                :data-user_id="(o.vpn_user && o.vpn_user.user_id) ? o.vpn_user.user_id : 0">
                                <td class="py-3 px-4 font-mono text-xs" x-text="'#' + o.id"></td>
                                <td class="py-3 px-4 max-w-[200px]">
                                    <div class="flex flex-col">
                                        <span x-text="o.vpn_user ? (o.vpn_user.name || '—') : '—'"></span>
                                        <span class="text-[11px] text-zinc-500 truncate" x-text="o.vpn_user ? (o.vpn_user.email || '') : ''"></span>
                                    </div>
                                </td>
                                <td class="py-3 px-4" x-text="o.product ? o.product.name : '—'"></td>
                                <td class="py-3 px-4" x-text="(o.vpn_user && o.vpn_user.region) ? o.vpn_user.region : '—'"></td>
                                <td class="py-3 px-4 font-mono text-[11px] max-w-[140px] truncate" :title="o.biz_order_no || ''" x-text="shortBizOrderNo(o.biz_order_no)"></td>
                                <td class="py-3 px-4" x-text="o.status || '—'"></td>
                                <td class="py-3 px-4 whitespace-nowrap" x-text="o.expires_at ? new Date(o.expires_at).toLocaleString() : '—'"></td>
                                <td class="py-3 px-4">
                                    <span x-show="o.entitled === true" class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700">是</span>
                                    <span x-show="o.entitled === false" class="inline-flex items-center rounded-full bg-zinc-50 px-2 py-0.5 text-[11px] font-medium text-zinc-500">否</span>
                                    <span x-show="o.entitled === null || o.entitled === undefined" class="text-zinc-400">—</span>
                                </td>
                                <td class="py-3 px-4" x-text="o.income_records_count != null ? o.income_records_count : '0'"></td>
                                <td class="py-3 px-4" x-text="o.reseller ? o.reseller.name : '—'"></td>
                                <td class="py-3 px-4">
                                    <button type="button" class="console-link text-xs vpn-view" x-show="o.vpn_user_id"
                                            @click.stop.prevent="openVpnDetailModal(o.vpn_user_id)">终端详情</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <p x-show="filteredPurchasedProducts().length === 0 && tab === 'purchased_products' && !loading" class="py-6 text-center text-zinc-500 text-sm">暂无分销侧已购订阅</p>
        </div>
    </div>

    {{-- 接入服务器 --}}
    <div x-show="tab === 'servers'" class="space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">接入服务器</h2>
                <p class="mt-1 text-sm text-slate-500">
                    可与 <strong>NAT 服务器</strong> 同机一体（双公网口：如 eth0=CN、eth1=HK），也可分体：接入机 CN 公网 + 与 NAT 机互联（内网 / WireGuard 等），NAT 机 HK 公网 + 互联口。
                    请在编辑中填写 <strong>NAT 拓扑</strong> 与各网卡角色，便于运维与 Agent 默认出口推断。
                    <span class="block mt-1 text-slate-600">「配置同步」列对比 <strong>A 站配置修订时间戳</strong>（保存服务器时递增）与 <strong>节点 agent.env 中的 CONFIG_REVISION_TS</strong>（心跳上报）；一致为已同步，否则需重新「部署 Agent」下发最新 env。接入机与 NAT 机各占一行、各自对比。</span>
                </p>
            </div>
            <button type="button" class="console-btn-primary" @click.prevent="openServerCreatePage()">新建接入服务器（独立页面）</button>
        </div>
        <div class="console-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm" id="as-server-table">
                    <thead class="bg-zinc-50 text-zinc-600">
                        <tr>
                            <th class="text-left py-3 px-4">ID</th>
                            <th class="text-left py-3 px-4">主机名</th>
                            <th class="text-left py-3 px-4">区域</th>
                            <th class="text-left py-3 px-4">NAT 拓扑</th>
                            <th class="text-left py-3 px-4">成本(分)</th>
                            <th class="text-left py-3 px-4">协议</th>
                            <th class="text-left py-3 px-4">域名/IP</th>
                            <th class="text-left py-3 px-4">WG 配置</th>
                            <th class="text-left py-3 px-4">SSH</th>
                            <th class="text-left py-3 px-4">配置检查</th>
                            <th class="text-left py-3 px-4 min-w-[9rem]">部署进度</th>
                            <th class="text-left py-3 px-4">Agent</th>
                            <th class="text-left py-3 px-4">版本</th>
                            <th class="text-left py-3 px-4">最后心跳</th>
                            <th class="text-left py-3 px-4 min-w-[11rem]">配置同步</th>
                            <th class="text-left py-3 px-4">备注</th>
                            <th class="text-left py-3 px-4">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="s in servers" :key="s.id">
                            <tr class="border-t border-zinc-100 hover:bg-zinc-50/50"
                                :data-id="s.id"
                                :data-hostname="s.hostname"
                                :data-region="s.region || ''"
                                :data-role="s.role || 'access'"
                                :data-cost-cents="s.cost_cents || 0"
                                :data-protocol="s.protocol || ''"
                                :data-vpn-ip-cidrs="s.vpn_ip_cidrs || ''"
                                :data-wg-public-key="s.wg_public_key || ''"
                                :data-wg-port="s.wg_port || ''"
                                :data-wg-dns="s.wg_dns || ''"
                                :data-ocserv-radius-host="s.ocserv_radius_host || ''"
                                :data-ocserv-radius-auth-port="s.ocserv_radius_auth_port || ''"
                                :data-ocserv-radius-acct-port="s.ocserv_radius_acct_port || ''"
                                :data-ocserv-port="s.ocserv_port || ''"
                                :data-ocserv-domain="s.ocserv_domain || ''"
                                :data-ocserv-tls-cert-pem="s.ocserv_tls_cert_pem || ''"
                                :data-ocserv-tls-key-pem="s.ocserv_tls_key_pem || ''"
                                :data-host="s.host || ''"
                                :data-ssh-port="s.ssh_port || 22"
                                :data-ssh-user="s.ssh_user || 'root'"
                                :data-ssh-password="s.ssh_password || ''"
                                :data-agent-enabled="s.agent_enabled ? '1' : '0'"
                                :data-node-nat-interface="s.node_nat_interface || 'eth0'"
                                :data-node-bandwidth-interface="s.node_bandwidth_interface || 'eth0'"
                                :data-nat-topology="s.nat_topology || 'combined'"
                                :data-cn-public-iface="s.cn_public_iface || ''"
                                :data-hk-public-iface="s.hk_public_iface || ''"
                                :data-peer-link-iface="s.peer_link_iface || ''"
                                :data-peer-link-local-ip="s.peer_link_local_ip || ''"
                                :data-peer-link-remote-ip="s.peer_link_remote_ip || ''"
                                :data-link-tunnel-type="s.link_tunnel_type || ''"
                                :data-split-nat-host="s.split_nat_host || ''"
                                :data-split-nat-ssh-port="s.split_nat_ssh_port || 22"
                                :data-split-nat-ssh-user="s.split_nat_ssh_user || 'root'"
                                :data-split-nat-ssh-password="s.split_nat_ssh_password || ''"
                                :data-split-nat-hk-public-iface="s.split_nat_hk_public_iface || ''"
                                :data-notes="s.notes || ''">
                                <td class="py-3 px-4" x-text="s.id"></td>
                                <td class="py-3 px-4" x-text="s.hostname"></td>
                                <td class="py-3 px-4" x-text="s.region"></td>
                                <td class="py-3 px-4">
                                    <span class="text-[11px] rounded bg-zinc-100 px-1.5 py-0.5 text-zinc-700"
                                          x-text="s.nat_topology === 'split_access' ? '分体·接入' : (s.nat_topology === 'split_nat' ? '分体·NAT' : '一体')"></span>
                                    <span class="mt-0.5 block text-[10px] text-zinc-500"
                                          x-show="s.cn_public_iface || s.hk_public_iface"
                                          x-text="[s.cn_public_iface ? 'CN:' + s.cn_public_iface : '', s.hk_public_iface ? 'HK:' + s.hk_public_iface : ''].filter(Boolean).join(' ')"></span>
                                </td>
                                <td class="py-3 px-4" x-text="((Number(s.cost_cents||0)/100).toFixed(2)) + ' 元'"></td>
                                <td class="py-3 px-4" x-text="s.protocol || '-'"></td>
                                <td class="py-3 px-4" x-text="s.host || '-'"></td>
                                <td class="py-3 px-4">
                                    <div class="flex flex-col">
                                        <span class="text-[11px] text-zinc-600" x-text="s.vpn_ip_cidrs ? ('CIDR: ' + s.vpn_ip_cidrs) : 'CIDR: -'"></span>
                                        <span class="mt-0.5 text-[11px] text-zinc-600" x-text="s.wg_public_key ? ('PubKey: ' + String(s.wg_public_key).slice(0, 10) + '…') : 'PubKey: -'"></span>
                                    </div>
                                </td>
                                <td class="py-3 px-4" x-text="(s.ssh_user || 'root') + '@' + (s.host || '-') + ':' + (s.ssh_port || 22)"></td>
                                <td class="py-3 px-4">
                                    <span :class="serverHealthClass(s)" x-text="serverHealthText(s)"></span>
                                </td>
                                <td class="py-3 px-4 align-top">
                                    <span :class="agentDeployClass(s)" :title="agentDeployTitle(s)" class="block max-w-[28rem] text-[11px] leading-snug whitespace-pre-wrap break-words" x-text="agentDeploySummary(s)"></span>
                                </td>
                                <td class="py-3 px-4 align-top">
                                    <span :class="agentRuntimeClass(s)" class="block text-[11px] leading-snug" x-text="agentRuntimeLabel(s)"></span>
                                </td>
                                <td class="py-3 px-4 font-mono text-[11px]" x-text="s.agent_version || '—'"></td>
                                <td class="py-3 px-4 text-[11px] text-zinc-600 whitespace-nowrap" x-text="formatServerHeartbeat(s)"></td>
                                <td class="py-3 px-4 align-top max-w-[15rem]">
                                    <span class="block text-[11px] leading-tight" :class="configSyncUi(s).badgeClass" x-text="configSyncUi(s).badge"></span>
                                    <span class="mt-1 block text-[10px] leading-snug text-zinc-500">A <span x-text="configSyncUi(s).aLine"></span></span>
                                    <span class="block text-[10px] leading-snug text-zinc-500">节点 <span x-text="configSyncUi(s).bLine"></span></span>
                                </td>
                                <td class="py-3 px-4" x-text="s.notes || '-'"></td>
                                <td class="py-3 px-4 flex gap-2">
                                    <button type="button" class="console-link text-xs" @click.prevent="openServerEditPage(s)">编辑</button>
                                    <button type="button" class="console-link text-xs text-indigo-600" @click.prevent="installServerAgent(s.id)">部署Agent</button>
                                    <button type="button" class="console-link text-xs text-red-600" @click.prevent="deleteServerAndReload(s.id)">删除</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- 接入服务器：独立配置页 --}}
    <div x-show="tab === 'server_form'" class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-lg font-semibold text-slate-900" x-text="serverFormMode === 'edit' ? '编辑接入服务器' : '新建接入服务器'"></h2>
                <p class="mt-1 text-sm text-slate-500">按四象限分区填写：左上基础、右上协议、左下拓扑、右下分体/NAT与运维。</p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" class="console-btn-secondary" @click.prevent="setTab('servers'); loadServers()">返回列表</button>
                <button type="button" class="console-btn-primary" @click.prevent="submitServerFormPage()">保存配置</button>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <section class="console-card p-4 space-y-3 min-h-[320px]">
                <h3 class="text-sm font-semibold text-slate-900">左上：接入服务器基础信息</h3>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div><label class="text-xs text-slate-500">名称</label><input class="console-filter-input mt-1 w-full" x-model="formServer.hostname" type="text"></div>
                    <div><label class="text-xs text-slate-500">区域</label><input class="console-filter-input mt-1 w-full" x-model="formServer.region" type="text"></div>
                    <div><label class="text-xs text-slate-500">成本（分）</label><input class="console-filter-input mt-1 w-full" x-model="formServer.cost_cents" type="number" min="0" step="1"></div>
                    <div><label class="text-xs text-slate-500">域名 / IP</label><input class="console-filter-input mt-1 w-full" x-model="formServer.host" type="text"></div>
                    <div><label class="text-xs text-slate-500">SSH 端口</label><input class="console-filter-input mt-1 w-full" x-model="formServer.ssh_port" type="number" min="1" max="65535"></div>
                    <div><label class="text-xs text-slate-500">SSH 用户</label><input class="console-filter-input mt-1 w-full" x-model="formServer.ssh_user" type="text"></div>
                    <div><label class="text-xs text-slate-500">SSH 密码（可选更新）</label><input class="console-filter-input mt-1 w-full" x-model="formServer.ssh_password" type="password"></div>
                </div>
            </section>

            <section class="console-card p-4 space-y-3 min-h-[320px]">
                <h3 class="text-sm font-semibold text-slate-900">右上：协议与服务配置</h3>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label class="text-xs text-slate-500">协议类型</label>
                        <select class="console-filter-input mt-1 w-full" x-model="formServer.protocol">
                            <option value="wireguard">WireGuard</option>
                            <option value="ocserv">OCServ</option>
                        </select>
                    </div>
                    <div><label class="text-xs text-slate-500">VPN 内网 CIDR</label><input class="console-filter-input mt-1 w-full" x-model="formServer.vpn_ip_cidrs" type="text"></div>
                    <template x-if="formServer.protocol === 'wireguard'">
                        <div><label class="text-xs text-slate-500">WG 端口</label><input class="console-filter-input mt-1 w-full" x-model="formServer.wg_port" type="number" min="1" max="65535"></div>
                    </template>
                    <template x-if="formServer.protocol === 'wireguard'">
                        <div><label class="text-xs text-slate-500">WG DNS</label><input class="console-filter-input mt-1 w-full" x-model="formServer.wg_dns" type="text"></div>
                    </template>
                    <div class="sm:col-span-2" x-show="formServer.protocol === 'wireguard'">
                        <label class="text-xs text-slate-500">WireGuard 私钥（留空自动生成）</label>
                        <textarea class="console-filter-input mt-1 w-full min-h-[80px]" x-model="formServer.wg_private_key"></textarea>
                    </div>
                    <div x-show="formServer.protocol === 'ocserv'">
                        <label class="text-xs text-slate-500">RADIUS Host（OCServ）</label>
                        <input class="console-filter-input mt-1 w-full" x-model="formServer.ocserv_radius_host" type="text">
                    </div>
                    <div x-show="formServer.protocol === 'ocserv'">
                        <label class="text-xs text-slate-500">OCServ 端口</label>
                        <input class="console-filter-input mt-1 w-full" x-model="formServer.ocserv_port" type="number" min="1" max="65535">
                    </div>
                    <div x-show="formServer.protocol === 'ocserv'"><label class="text-xs text-slate-500">RADIUS Auth 端口</label><input class="console-filter-input mt-1 w-full" x-model="formServer.ocserv_radius_auth_port" type="number" min="1" max="65535"></div>
                    <div x-show="formServer.protocol === 'ocserv'"><label class="text-xs text-slate-500">RADIUS Acct 端口</label><input class="console-filter-input mt-1 w-full" x-model="formServer.ocserv_radius_acct_port" type="number" min="1" max="65535"></div>
                    <div x-show="formServer.protocol === 'ocserv'"><label class="text-xs text-slate-500">绑定域名</label><input class="console-filter-input mt-1 w-full" x-model="formServer.ocserv_domain" type="text"></div>
                    <div x-show="formServer.protocol === 'ocserv'"><label class="text-xs text-slate-500">RADIUS Secret</label><input class="console-filter-input mt-1 w-full" x-model="formServer.ocserv_radius_secret" type="password"></div>
                    <div class="sm:col-span-2" x-show="formServer.protocol === 'ocserv'"><label class="text-xs text-slate-500">TLS 证书 PEM</label><textarea class="console-filter-input mt-1 w-full min-h-[90px]" x-model="formServer.ocserv_tls_cert_pem"></textarea></div>
                    <div class="sm:col-span-2" x-show="formServer.protocol === 'ocserv'"><label class="text-xs text-slate-500">TLS 私钥 PEM</label><textarea class="console-filter-input mt-1 w-full min-h-[90px]" x-model="formServer.ocserv_tls_key_pem"></textarea></div>
                </div>
            </section>

            <section class="console-card p-4 space-y-3 min-h-[320px]">
                <h3 class="text-sm font-semibold text-slate-900">左下：拓扑与启用选项</h3>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label class="text-xs text-slate-500">NAT 拓扑</label>
                        <select class="console-filter-input mt-1 w-full" x-model="formServer.nat_topology">
                            <option value="combined">一体</option>
                            <option value="split_access">分体·接入</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">启用 Agent</label>
                        <select class="console-filter-input mt-1 w-full" x-model="formServer.agent_enabled">
                            <option :value="true">启用</option>
                            <option :value="false">禁用</option>
                        </select>
                    </div>
                    <div><label class="text-xs text-slate-500">CN 公网网卡</label><input class="console-filter-input mt-1 w-full" x-model="formServer.cn_public_iface" type="text"></div>
                    <div x-show="formServer.nat_topology === 'combined'"><label class="text-xs text-slate-500">HK 公网网卡</label><input class="console-filter-input mt-1 w-full" x-model="formServer.hk_public_iface" type="text"></div>
                    <div x-show="formServer.nat_topology !== 'combined'"><label class="text-xs text-slate-500">互联网卡</label><input class="console-filter-input mt-1 w-full" x-model="formServer.peer_link_iface" type="text"></div>
                    <div x-show="formServer.nat_topology !== 'combined'"><label class="text-xs text-slate-500">互联类型</label>
                        <select class="console-filter-input mt-1 w-full" x-model="formServer.link_tunnel_type">
                            <option value="">直连/静态路由</option>
                            <option value="wireguard">WireGuard</option>
                            <option value="gre">GRE</option>
                            <option value="vxlan">VXLAN</option>
                        </select>
                    </div>
                    <div x-show="formServer.nat_topology !== 'combined'"><label class="text-xs text-slate-500">接入侧互联 IP</label><input class="console-filter-input mt-1 w-full" x-model="formServer.peer_link_local_ip" type="text"></div>
                    <div class="sm:col-span-2 rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs text-slate-500" x-show="formServer.nat_topology !== 'combined'">
                        互联网卡可填物理网卡（如 eth1）或虚拟网卡（如 wg-link0）。若互联类型选择 WireGuard，Agent 会按下方参数自动创建该虚拟网卡并配置。
                    </div>
                    <template x-if="formServer.nat_topology !== 'combined' && formServer.link_tunnel_type === 'wireguard'">
                        <div><label class="text-xs text-slate-500">互联网卡 WG 私钥</label><input class="console-filter-input mt-1 w-full" x-model="formServer.peer_link_wg_private_key" type="password"></div>
                    </template>
                    <template x-if="formServer.nat_topology !== 'combined' && formServer.link_tunnel_type === 'wireguard'">
                        <div><label class="text-xs text-slate-500">对端 WG 公钥</label><input class="console-filter-input mt-1 w-full" x-model="formServer.peer_link_wg_peer_public_key" type="text"></div>
                    </template>
                    <template x-if="formServer.nat_topology !== 'combined' && formServer.link_tunnel_type === 'wireguard'">
                        <div><label class="text-xs text-slate-500">对端 Endpoint(host:port)</label><input class="console-filter-input mt-1 w-full" x-model="formServer.peer_link_wg_endpoint" type="text" placeholder="203.0.113.10:51820"></div>
                    </template>
                    <template x-if="formServer.nat_topology !== 'combined' && formServer.link_tunnel_type === 'wireguard'">
                        <div><label class="text-xs text-slate-500">AllowedIPs（可选）</label><input class="console-filter-input mt-1 w-full" x-model="formServer.peer_link_wg_allowed_ips" type="text" placeholder="10.0.0.2/32"></div>
                    </template>
                    <div x-show="formServer.nat_topology === 'combined'">
                        <label class="text-xs text-slate-500">可绑定多个公网IP（对接IP池）</label>
                        <select class="console-filter-input mt-1 w-full" x-model="formServer.split_nat_multi_public_ip_enabled">
                            <option :value="false">否</option>
                            <option :value="true">是</option>
                        </select>
                    </div>
                </div>
            </section>

            <section class="console-card p-4 space-y-3 min-h-[320px]">
                <h3 class="text-sm font-semibold text-slate-900">右下：分体 NAT 与备注</h3>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <template x-if="formServer.nat_topology !== 'combined'">
                        <div><label class="text-xs text-slate-500">分体 NAT 服务器 IP/域名</label><input class="console-filter-input mt-1 w-full" x-model="formServer.split_nat_host" type="text"></div>
                    </template>
                    <template x-if="formServer.nat_topology !== 'combined'">
                        <div><label class="text-xs text-slate-500">分体 NAT SSH 端口</label><input class="console-filter-input mt-1 w-full" x-model="formServer.split_nat_ssh_port" type="number" min="1" max="65535"></div>
                    </template>
                    <template x-if="formServer.nat_topology !== 'combined'">
                        <div><label class="text-xs text-slate-500">分体 NAT SSH 用户</label><input class="console-filter-input mt-1 w-full" x-model="formServer.split_nat_ssh_user" type="text"></div>
                    </template>
                    <template x-if="formServer.nat_topology !== 'combined'">
                        <div><label class="text-xs text-slate-500">分体 NAT SSH 密码</label><input class="console-filter-input mt-1 w-full" x-model="formServer.split_nat_ssh_password" type="password"></div>
                    </template>
                    <template x-if="formServer.nat_topology !== 'combined'">
                        <div><label class="text-xs text-slate-500">NAT 侧互联 IP</label><input class="console-filter-input mt-1 w-full" x-model="formServer.peer_link_remote_ip" type="text"></div>
                    </template>
                    <template x-if="formServer.nat_topology !== 'combined'">
                        <div><label class="text-xs text-slate-500">分体 NAT 网卡</label><input class="console-filter-input mt-1 w-full" x-model="formServer.split_nat_hk_public_iface" type="text"></div>
                    </template>
                    <template x-if="formServer.nat_topology !== 'combined'">
                        <div>
                            <label class="text-xs text-slate-500">可绑定多个公网IP（对接IP池）</label>
                            <select class="console-filter-input mt-1 w-full" x-model="formServer.split_nat_multi_public_ip_enabled">
                                <option :value="false">否</option>
                                <option :value="true">是</option>
                            </select>
                        </div>
                    </template>
                    <div class="sm:col-span-2 rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs text-slate-500" x-show="formServer.nat_topology === 'combined'">
                        当前为一体模式，不需要填写 NAT 服务器信息。
                    </div>
                    <div class="sm:col-span-2"><label class="text-xs text-slate-500">备注</label><textarea class="console-filter-input mt-1 w-full min-h-[90px]" x-model="formServer.notes"></textarea></div>
                </div>
            </section>
        </div>
    </div>

    {{-- 订单（A 站订阅）+ 详情内展示 B 站业务流水 --}}
    <div x-show="tab === 'orders'" class="space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">订单</h2>
                <p class="mt-1 text-sm text-slate-500">一行对应一条 <strong>A 站订阅</strong>（续费会更新到期时间）。B 站每笔收入的<strong>完整业务单号</strong>在「详情 → B 站业务流水」中查看；控制台概览仍显示流水总笔数。</p>
            </div>
            <button type="button" class="console-btn-primary" id="ord-add-btn">创建订单</button>
        </div>
        <div class="console-filter-bar">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <input type="text" placeholder="搜索（邮箱/产品/业务单号/分销商/状态）..." class="console-filter-input max-w-sm"
                           x-model="ordersFilter.q">
                    <select class="console-filter-input sm:max-w-[220px]" x-model="ordersFilter.reseller_id">
                        <option value="">全部分销商</option>
                        <template x-for="r in resellers" :key="'ord-r-' + r.id">
                            <option :value="String(r.id)" x-text="r.name"></option>
                        </template>
                    </select>
                    <select class="console-filter-input sm:max-w-[220px]" x-model="ordersFilter.region">
                        <option value="">全部区域</option>
                        <template x-for="rg in orderRegions()" :key="'ord-rg-' + rg">
                            <option :value="rg" x-text="rg"></option>
                        </template>
                    </select>
                </div>
                <div class="text-xs text-slate-500" x-text="'共 ' + (filteredOrders().length) + ' 条'"></div>
            </div>
        </div>
        <div class="console-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm" id="ord-table">
                    <thead class="bg-zinc-50 text-zinc-600">
                        <tr>
                            <th class="text-left py-3 px-4">首笔业务号</th>
                            <th class="text-left py-3 px-4">流水</th>
                            <th class="text-left py-3 px-4">用户</th>
                            <th class="text-left py-3 px-4">产品</th>
                            <th class="text-left py-3 px-4">状态</th>
                            <th class="text-left py-3 px-4">到期</th>
                            <th class="text-left py-3 px-4">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="o in filteredOrders()" :key="o.id">
                            <tr class="border-t border-zinc-100 hover:bg-zinc-50/50">
                                <td class="py-3 px-4 font-mono text-xs" :title="o.biz_order_no || ''" x-text="shortBizOrderNo(o.biz_order_no)"></td>
                                <td class="py-3 px-4 whitespace-nowrap text-zinc-600" x-text="(o.income_records_count != null ? o.income_records_count : (o.income_records || []).length) + ' 笔'"></td>
                                <td class="py-3 px-4 max-w-[200px] truncate" :title="o.vpn_user ? o.vpn_user.email : (o.user ? o.user.email : '')" x-text="o.vpn_user ? (o.vpn_user.email || '-') : (o.user ? (o.user.email || '-') : (o.user_id || '-'))"></td>
                                <td class="py-3 px-4" x-text="o.product ? (o.product.name || '-') : (o.product_id || '-')"></td>
                                <td class="py-3 px-4" x-text="o.status"></td>
                                <td class="py-3 px-4 whitespace-nowrap" x-text="o.expires_at ? new Date(o.expires_at).toLocaleString() : '-'"></td>
                                <td class="py-3 px-4 flex flex-wrap gap-2">
                                    <button type="button" class="console-link text-xs" @click="orderDetailId = orderDetailId === o.id ? null : o.id" x-text="orderDetailId === o.id ? '收起' : '详情'"></button>
                                    <button type="button" class="console-link text-xs text-red-600 ord-delete" :data-id="o.id" @click="deleteOrder(o.id)">删除</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <p x-show="orders.length === 0 && tab === 'orders'" class="py-6 text-center text-zinc-500 text-sm">暂无订单，请先选择用户与产品创建订单。</p>
            <div x-show="orderDetailId && tab === 'orders'" x-cloak class="border-t border-zinc-100 bg-zinc-50/80 px-4 py-4 text-sm">
                <template x-if="selectedOrderRow()">
                    <div>
                    <dl class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        <div><dt class="text-zinc-500">内部 ID</dt><dd class="font-mono" x-text="selectedOrderRow().id"></dd></div>
                        <div class="sm:col-span-2"><dt class="text-zinc-500">首笔业务单号（A 订单字段，一般为首购）</dt><dd class="font-mono text-xs break-all" x-text="selectedOrderRow().biz_order_no || '—'"></dd></div>
                        <div><dt class="text-zinc-500">用户邮箱</dt><dd x-text="selectedOrderRow().vpn_user ? (selectedOrderRow().vpn_user.email || '—') : (selectedOrderRow().user ? selectedOrderRow().user.email : '—')"></dd></div>
                        <div><dt class="text-zinc-500">区域</dt><dd x-text="selectedOrderRow().vpn_user ? (selectedOrderRow().vpn_user.region || '—') : '—'"></dd></div>
                        <div><dt class="text-zinc-500">分销商</dt><dd x-text="selectedOrderRow().reseller ? (selectedOrderRow().reseller.name || '—') : (selectedOrderRow().reseller_id || '—')"></dd></div>
                        <div><dt class="text-zinc-500">产品</dt><dd x-text="selectedOrderRow().product ? selectedOrderRow().product.name : '—'"></dd></div>
                        <div><dt class="text-zinc-500">开通时间</dt><dd x-text="selectedOrderRow().activated_at ? new Date(selectedOrderRow().activated_at).toLocaleString() : '—'"></dd></div>
                        <div><dt class="text-zinc-500">最后续费</dt><dd x-text="selectedOrderRow().last_renewed_at ? new Date(selectedOrderRow().last_renewed_at).toLocaleString() : '—'"></dd></div>
                        <div><dt class="text-zinc-500">到期</dt><dd x-text="selectedOrderRow().expires_at ? new Date(selectedOrderRow().expires_at).toLocaleString() : '—'"></dd></div>
                    </dl>
                    <div class="mt-4 border-t border-zinc-200 pt-4">
                        <h3 class="mb-2 text-sm font-semibold text-zinc-800">B 站业务流水（完整业务单号）</h3>
                        <p class="mb-2 text-xs text-zinc-500">每笔收入一行；与上表「首笔业务号」对应首购，续费为后续各行。</p>
                        <template x-if="selectedOrderRow() && (selectedOrderRow().income_records || []).length">
                            <div class="overflow-x-auto rounded border border-zinc-200">
                                <table class="w-full text-xs">
                                    <thead class="bg-zinc-100 text-zinc-600">
                                        <tr>
                                            <th class="text-left py-2 px-3">完整业务单号</th>
                                            <th class="text-left py-2 px-3">类型</th>
                                            <th class="text-left py-2 px-3">记录时间</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="row in selectedOrderRow().income_records" :key="row.id">
                                            <tr class="border-t border-zinc-100">
                                                <td class="py-2 px-3 font-mono break-all" x-text="row.biz_order_no || '—'"></td>
                                                <td class="py-2 px-3 whitespace-nowrap" x-text="row.kind === 'renew' ? '续费' : (row.kind === 'purchase' ? '新购' : row.kind)"></td>
                                                <td class="py-2 px-3 whitespace-nowrap text-zinc-600" x-text="row.created_at ? new Date(row.created_at).toLocaleString() : '—'"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </template>
                        <p x-show="selectedOrderRow() && !(selectedOrderRow().income_records || []).length" class="text-xs text-zinc-500">暂无同步流水（旧数据或尚未经分销商接口写入）。</p>
                    </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- 产品与定价 --}}
    <div x-show="tab === 'products'" class="space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">产品与定价</h2>
                <p class="mt-1 text-sm text-slate-500">配置可售套餐：如 3 天体验、7 天、15 天、30 日月付等。</p>
            </div>
            <button type="button" class="console-btn-primary" id="prod-add-btn">添加产品</button>
        </div>
        <div class="console-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm" id="prod-table">
                    <thead class="bg-zinc-50 text-zinc-600">
                        <tr>
                            <th class="text-left py-3 px-4">ID</th>
                            <th class="text-left py-3 px-4">名称</th>
                            <th class="text-left py-3 px-4">价格(元)</th>
                            <th class="text-left py-3 px-4">时长(天)</th>
                            <th class="text-left py-3 px-4">协议</th>
                            <th class="text-left py-3 px-4">带宽</th>
                            <th class="text-left py-3 px-4">流量额度</th>
                            <th class="text-left py-3 px-4">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="p in products" :key="p.id">
                            <tr class="border-t border-zinc-100 hover:bg-zinc-50/50"
                                :data-id="p.id"
                                :data-name="p.name"
                                :data-price="p.price_cents ?? p.priceCents"
                                :data-days="p.duration_days ?? p.durationDays"
                                :data-enable-radius="(p.enable_radius === undefined ? 1 : (p.enable_radius ? 1 : 0))"
                                :data-enable-wireguard="(p.enable_wireguard === undefined ? 1 : (p.enable_wireguard ? 1 : 0))"
                                :data-requires-dedicated-public-ip="(p.requires_dedicated_public_ip ? 1 : 0)"
                                :data-bandwidth-kbps="p.bandwidth_limit_kbps != null ? p.bandwidth_limit_kbps : ''"
                                :data-traffic-gb="(p.traffic_quota_bytes != null && p.traffic_quota_bytes > 0) ? (p.traffic_quota_bytes / 1073741824) : ''">
                                <td class="py-3 px-4" x-text="p.id"></td>
                                <td class="py-3 px-4" x-text="p.name"></td>
                                <td class="py-3 px-4"><span x-text="(function(){ var c = Number(p.price_cents !== undefined ? p.price_cents : (p.priceCents !== undefined ? p.priceCents : 0)); if (Number.isNaN(c)) c = 0; return (c/100).toFixed(2) + ' 元'; })()"></span></td>
                                <td class="py-3 px-4" x-text="p.duration_days ?? p.durationDays"></td>
                                <td class="py-3 px-4">
                                    <span class="text-xs text-zinc-700"
                                          x-text="(function(){ var r = (p.enable_radius === undefined ? true : !!p.enable_radius); var w = (p.enable_wireguard === undefined ? true : !!p.enable_wireguard); if (r && w) return 'RADIUS + WireGuard'; if (w) return 'WireGuard'; if (r) return 'RADIUS'; return '—'; })()"></span>
                                    <span class="ml-2 inline-flex rounded bg-indigo-50 px-1.5 py-0.5 text-[10px] text-indigo-700" x-show="!!p.requires_dedicated_public_ip">独立公网IP</span>
                                </td>
                                <td class="py-3 px-4 text-xs text-zinc-600" x-text="p.bandwidth_limit_kbps ? (p.bandwidth_limit_kbps + ' Kbps') : '不限'"></td>
                                <td class="py-3 px-4 text-xs text-zinc-600" x-text="p.traffic_quota_bytes ? ((p.traffic_quota_bytes / 1073741824).toFixed(2) + ' GiB/周期') : '不限'"></td>
                                <td class="py-3 px-4 flex gap-2">
                                    <button type="button" class="console-link text-xs prod-edit">编辑</button>
                                    <button type="button" class="console-link text-xs text-red-600 prod-delete" :data-id="p.id">删除</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- 分销商 --}}
    <div x-show="tab === 'resellers'" class="space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">分销商</h2>
                <p class="mt-1 text-sm text-slate-500">添加分销商后可为对方生成 API Key，用于 B 站登录或通过 API 管理其下属客户。</p>
            </div>
            <button type="button" class="console-btn-primary" id="reseller-add-btn">添加分销商</button>
        </div>
        <div class="console-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm" id="reseller-table">
                    <thead class="bg-zinc-50 text-zinc-600">
                        <tr>
                            <th class="text-left py-3 px-4">ID</th>
                            <th class="text-left py-3 px-4">名称</th>
                            <th class="text-left py-3 px-4">邮箱</th>
                            <th class="text-left py-3 px-4">余额</th>
                            <th class="text-left py-3 px-4">扣费</th>
                            <th class="text-left py-3 px-4">API Key</th>
                            <th class="text-left py-3 px-4">创建时间</th>
                            <th class="text-left py-3 px-4">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="r in resellers" :key="r.id">
                            <tr class="border-t border-zinc-100 hover:bg-zinc-50/50"
                                :data-id="r.id"
                                :data-name="r.name"
                                :data-email="r.email"
                                :data-balance-cents="r.balance_cents"
                                :data-balance-enforced="r.balance_enforced"
                                :data-status="r.status">
                                <td class="py-3 px-4" x-text="r.id"></td>
                                <td class="py-3 px-4" x-text="r.name"></td>
                                <td class="py-3 px-4" x-text="r.email || '-'"></td>
                                <td class="py-3 px-4" x-text="(function(){var c=Number(r.balance_cents||0); if (Number.isNaN(c)) c=0; return (c/100).toFixed(2)+' 元';})()"></td>
                                <td class="py-3 px-4" x-text="r.balance_enforced ? '已启用' : '未启用'"></td>
                                <td class="py-3 px-4">
                                    <code class="rounded bg-zinc-100 px-2 py-0.5 text-[11px] font-mono text-zinc-700"
                                          x-text="r.latest_api_key_preview || '尚未生成'"></code>
                                </td>
                                <td class="py-3 px-4" x-text="r.created_at ? new Date(r.created_at).toLocaleString() : '-'"></td>
                                <td class="py-3 px-4 flex gap-2">
                                    <button type="button" class="console-link text-xs reseller-edit">编辑</button>
                                    <button type="button" class="console-link text-xs text-red-600 reseller-delete" :data-id="r.id">删除</button>
                                    <button type="button" class="console-link text-xs reseller-api" :data-id="r.id">重置 API Key</button>
                                    <button type="button"
                                            class="console-link text-xs reseller-tx"
                                            @click.prevent="openResellerBalanceTx(r.id)">
                                        余额流水
                                    </button>
                                    <button type="button" class="console-link text-xs reseller-balance-adjust" :data-id="r.id">资金调整</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- 分销商余额流水（展开/分页/搜索） --}}
        <div class="console-card p-5"
             x-show="txPanelResellerId"
             x-cloak
        >
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">余额流水</h3>
                    <p class="mt-1 text-sm text-slate-500">分销商 ID：<span x-text="txPanelResellerId"></span></p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" class="console-link text-sm" @click.prevent="closeResellerBalanceTx()">关闭</button>
                </div>
            </div>

            <div class="console-filter-bar mt-4">
                <input type="text"
                       class="console-filter-input"
                       style="max-width: 240px;"
                       placeholder="搜索：id 或 meta(备注)"
                       x-model.trim="txSearch"
                       @keydown.enter.prevent="loadResellerBalanceTx(1)">

                <select class="console-filter-input"
                        style="max-width: 220px;"
                        x-model="txType"
                        @change="loadResellerBalanceTx(1)">
                    <option value="">全部类型</option>
                        <option value="recharge">充值</option>
                        <option value="provision_purchase">开通（新购）</option>
                        <option value="provision_renew">续费</option>
                        <option value="admin_adjust">后台调整</option>
                </select>

                <select class="console-filter-input"
                        style="max-width: 140px;"
                        x-model.number="txLimit"
                        @change="loadResellerBalanceTx(1)">
                    <option :value="10">10</option>
                    <option :value="20">20</option>
                    <option :value="50">50</option>
                </select>

                <button type="button" class="console-btn-secondary" @click.prevent="loadResellerBalanceTx(1)" :disabled="txLoading">
                    <span x-show="!txLoading">搜索</span>
                    <span x-show="txLoading">加载中…</span>
                </button>
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
                                <th>备注</th>
                                <th>时间</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="t in txItems" :key="t.id">
                                <tr>
                                    <td class="py-3 px-4" x-text="t.id"></td>
                                    <td class="py-3 px-4" x-text="txTypeLabel(t.type)"></td>
                                    <td class="py-3 px-4" x-text="((Number(t.amount_cents||0)/100).toFixed(2))"></td>
                                    <td class="py-3 px-4" x-text="((Number(t.balance_after_cents||0)/100).toFixed(2))"></td>
                                    <td class="py-3 px-4 text-slate-700 max-w-[260px]" x-text="t.note || '-'"></td>
                                    <td class="py-3 px-4" x-text="t.created_at ? new Date(t.created_at).toLocaleString() : '-'"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <p x-show="!txLoading && txItems.length === 0" class="py-8 text-center text-slate-500">暂无流水</p>

                <div class="flex items-center justify-between gap-3 mt-4">
                    <div class="text-sm text-slate-500">
                        <span x-text="txTotal"></span> 条 · 第 <span x-text="txPage"></span> 页 / <span x-text="txTotalPages"></span> 页
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button"
                                class="console-pagination-btn"
                                @click.prevent="loadResellerBalanceTx(txPage - 1)"
                                :disabled="txPage <= 1 || txLoading">
                            上一页
                        </button>
                        <button type="button"
                                class="console-pagination-btn"
                                @click.prevent="loadResellerBalanceTx(txPage + 1)"
                                :disabled="txPage >= txTotalPages || txLoading">
                            下一页
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>


    {{-- IP 池 --}}
    <div x-show="tab === 'ip_pool'" class="space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">IP 池</h2>
                <p class="mt-1 text-sm text-slate-500">管理用于 NAT 服务器的 IP 地址资源。</p>
            </div>
            <div class="flex items-center gap-3">
                <button type="button" class="console-btn-secondary" id="ip-batch-delete-btn">批量删除</button>
                <button type="button" class="console-btn-primary" id="ip-add-btn">添加 IP</button>
            </div>
        </div>
        <div class="console-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm" id="ip-table">
                    <thead class="bg-zinc-50 text-zinc-600">
                        <tr>
                            <th class="text-left py-3 px-4">
                                <input type="checkbox" id="ip-select-all" class="as-checkbox">
                            </th>
                            <th class="text-left py-3 px-4">ID</th>
                            <th class="text-left py-3 px-4">IP</th>
                            <th class="text-left py-3 px-4">区域</th>
                            <th class="text-left py-3 px-4">绑定服务器</th>
                            <th class="text-left py-3 px-4">状态</th>
                            <th class="text-left py-3 px-4">添加时间</th>
                            <th class="text-left py-3 px-4">添加账号</th>
                            <th class="text-left py-3 px-4">绑定 VPN 账号</th>
                            <th class="text-left py-3 px-4">上次解除绑定</th>
                            <th class="text-left py-3 px-4">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="ip in ipPool" :key="ip.id">
                            <tr class="border-t border-zinc-100 hover:bg-zinc-50/50"
                                :data-id="ip.id"
                                :data-ip="ip.ip_address"
                                :data-region="ip.region"
                                :data-server-id="ip.server_id || ''">
                                <td class="py-3 px-4">
                                    <input type="checkbox" class="as-checkbox ip-select" :value="ip.id">
                                </td>
                                <td class="py-3 px-4" x-text="ip.id"></td>
                                <td class="py-3 px-4" x-text="ip.ip_address"></td>
                                <td class="py-3 px-4" x-text="ip.region"></td>
                                <td class="py-3 px-4" x-text="ip.server ? ((ip.server.hostname || ('#' + ip.server.id)) + (ip.server.region ? (' / ' + ip.server.region) : '')) : '全部'"></td>
                                <td class="py-3 px-4" x-text="ip.status"></td>
                                <td class="py-3 px-4" x-text="ip.created_at ? new Date(ip.created_at).toLocaleString() : '-'"></td>
                                <td class="py-3 px-4" x-text="ip.creator ? (ip.creator.email || ('#' + ip.creator.id)) : '-'"></td>
                                <td class="py-3 px-4" x-text="ip.vpn_user ? (ip.vpn_user.name || ('#' + ip.vpn_user.id)) : '未使用'"></td>
                                <td class="py-3 px-4"
                                    x-text="ip.last_unbound_at ? (function(){ const d = new Date(ip.last_unbound_at); const days = Math.floor((Date.now() - d.getTime())/86400000); return d.toLocaleString() + ' (' + days + ' 天)'; })() : '-'"></td>
                                <td class="py-3 px-4 flex gap-2">
                                    <button type="button" class="console-link text-xs ip-release" :data-id="ip.id">释放</button>
                                    <button type="button" class="console-link text-xs text-red-600 ip-delete" :data-id="ip.id">删除</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- SNAT 映射表（只读） --}}
    <div x-show="tab === 'snat_maps'" class="space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">SNAT 映射表</h2>
                <p class="mt-1 text-sm text-slate-500">只读查看用户内网 IP 到公网 IP 的 SNAT 下发记录与状态；支持按用户、服务器、状态与关键字筛选。</p>
            </div>
            <button type="button" class="console-btn-secondary shrink-0" @click.prevent="snatMapsPage = 1; loadSnatMaps()">刷新</button>
        </div>
        <div class="console-card p-4 space-y-3">
            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-xs font-medium text-slate-500">状态</label>
                    <select class="mt-1 rounded-md border border-slate-300 px-2 py-1.5 text-sm" x-model="snatFilter.status" @change="snatMapsPage = 1; loadSnatMaps()">
                        <option value="">全部</option>
                        <option value="active">active</option>
                        <option value="released">released</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500">VPN 用户 ID</label>
                    <input type="number" min="1" class="mt-1 w-28 rounded-md border border-slate-300 px-2 py-1.5 text-sm font-mono" placeholder="可选" x-model="snatFilter.vpn_user_id" @keydown.enter.prevent="snatMapsPage = 1; loadSnatMaps()">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500">服务器 ID</label>
                    <input type="number" min="1" class="mt-1 w-28 rounded-md border border-slate-300 px-2 py-1.5 text-sm font-mono" placeholder="可选" x-model="snatFilter.server_id" @keydown.enter.prevent="snatMapsPage = 1; loadSnatMaps()">
                </div>
                <div class="min-w-[12rem] flex-1">
                    <label class="block text-xs font-medium text-slate-500">关键字（邮箱 / IP）</label>
                    <input type="text" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm" placeholder="模糊匹配" x-model="snatFilter.q" @keydown.enter.prevent="snatMapsPage = 1; loadSnatMaps()">
                </div>
                <button type="button" class="console-btn-secondary text-sm" @click.prevent="snatMapsPage = 1; loadSnatMaps()">查询</button>
            </div>
        </div>
        <div class="console-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-zinc-50 text-zinc-600">
                        <tr>
                            <th class="text-left py-3 px-4">ID</th>
                            <th class="text-left py-3 px-4">用户</th>
                            <th class="text-left py-3 px-4">服务器</th>
                            <th class="text-left py-3 px-4">NAT 网卡</th>
                            <th class="text-left py-3 px-4">Source IP</th>
                            <th class="text-left py-3 px-4">Public IP</th>
                            <th class="text-left py-3 px-4">状态</th>
                            <th class="text-left py-3 px-4">生效时间</th>
                            <th class="text-left py-3 px-4">释放时间</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="m in snatMaps" :key="m.id">
                            <tr class="border-t border-zinc-100 hover:bg-zinc-50/50">
                                <td class="py-3 px-4" x-text="m.id"></td>
                                <td class="py-3 px-4" x-text="(m.vpn_user_email || '-') + (m.vpn_user_name ? (' / ' + m.vpn_user_name) : '')"></td>
                                <td class="py-3 px-4" x-text="(m.server_hostname || ('#' + m.server_id)) + (m.server_region ? (' / ' + m.server_region) : '')"></td>
                                <td class="py-3 px-4" x-text="m.nat_interface || '-'"></td>
                                <td class="py-3 px-4 font-mono text-xs" x-text="m.source_ip"></td>
                                <td class="py-3 px-4 font-mono text-xs" x-text="m.public_ip"></td>
                                <td class="py-3 px-4" x-text="m.status"></td>
                                <td class="py-3 px-4" x-text="m.applied_at ? new Date(m.applied_at).toLocaleString() : '-'"></td>
                                <td class="py-3 px-4" x-text="m.released_at ? new Date(m.released_at).toLocaleString() : '-'"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <p x-show="snatMaps.length === 0" class="py-6 text-center text-zinc-500 text-sm">暂无 SNAT 映射记录。</p>
            <div x-show="snatMapsTotal > snatMapsPerPage" class="flex flex-wrap items-center justify-between gap-2 border-t border-zinc-100 px-4 py-3 text-sm text-slate-600">
                <span>共 <span x-text="snatMapsTotal"></span> 条，第 <span x-text="snatMapsPage"></span> / <span x-text="Math.max(1, Math.ceil(snatMapsTotal / snatMapsPerPage))"></span> 页</span>
                <div class="flex gap-2">
                    <button type="button" class="console-btn-secondary text-xs px-3 py-1" :disabled="snatMapsPage <= 1" @click.prevent="snatMapsPage--; loadSnatMaps()">上一页</button>
                    <button type="button" class="console-btn-secondary text-xs px-3 py-1" :disabled="snatMapsPage * snatMapsPerPage >= snatMapsTotal" @click.prevent="snatMapsPage++; loadSnatMaps()">下一页</button>
                </div>
            </div>
        </div>
    </div>

    {{-- 资源审计（IP 池绑定 / SNAT 下发与后台释放） --}}
    <div x-show="tab === 'provision_audit'" class="space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">资源审计</h2>
                <p class="mt-1 text-sm text-slate-500">分销商开通触发的 IP 池首次绑定、SNAT 应用/替换，以及管理端释放 IP / 移除 SNAT 的记录（含订单、产品维度）。</p>
            </div>
            <button type="button" class="console-btn-secondary shrink-0" @click.prevent="provisionAuditPage = 1; loadProvisionAuditLogs()">刷新</button>
        </div>
        <div class="console-card p-4 space-y-3">
            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-xs font-medium text-slate-500">事件类型</label>
                    <select class="mt-1 rounded-md border border-slate-300 px-2 py-1.5 text-sm min-w-[11rem]" x-model="provisionAuditFilter.event" @change="provisionAuditPage = 1; loadProvisionAuditLogs()">
                        <option value="">全部</option>
                        <option value="ip_pool_bind">ip_pool_bind（首次绑定池 IP）</option>
                        <option value="snat_applied">snat_applied</option>
                        <option value="snat_replaced">snat_replaced</option>
                        <option value="ip_pool_admin_release">ip_pool_admin_release</option>
                        <option value="snat_admin_remove">snat_admin_remove</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500">VPN 用户 ID</label>
                    <input type="number" min="1" class="mt-1 w-28 rounded-md border border-slate-300 px-2 py-1.5 text-sm font-mono" placeholder="可选" x-model="provisionAuditFilter.vpn_user_id" @keydown.enter.prevent="provisionAuditPage = 1; loadProvisionAuditLogs()">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500">订单 ID</label>
                    <input type="number" min="1" class="mt-1 w-28 rounded-md border border-slate-300 px-2 py-1.5 text-sm font-mono" placeholder="可选" x-model="provisionAuditFilter.order_id" @keydown.enter.prevent="provisionAuditPage = 1; loadProvisionAuditLogs()">
                </div>
                <button type="button" class="console-btn-secondary text-sm" @click.prevent="provisionAuditPage = 1; loadProvisionAuditLogs()">查询</button>
            </div>
        </div>
        <div class="console-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-zinc-50 text-zinc-600">
                        <tr>
                            <th class="text-left py-3 px-4">时间</th>
                            <th class="text-left py-3 px-4">事件</th>
                            <th class="text-left py-3 px-4">用户</th>
                            <th class="text-left py-3 px-4">订单</th>
                            <th class="text-left py-3 px-4">产品</th>
                            <th class="text-left py-3 px-4">分销商</th>
                            <th class="text-left py-3 px-4">详情</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="row in provisionAuditLogs" :key="row.id">
                            <tr class="border-t border-zinc-100 hover:bg-zinc-50/50">
                                <td class="py-3 px-4 whitespace-nowrap" x-text="row.created_at ? new Date(row.created_at).toLocaleString() : '-'"></td>
                                <td class="py-3 px-4 font-mono text-xs" x-text="row.event"></td>
                                <td class="py-3 px-4">
                                    <span x-text="row.vpn_user_id ? ('#' + row.vpn_user_id) : '-'"></span>
                                    <span class="text-slate-500" x-show="row.vpn_user_email" x-text="' ' + (row.vpn_user_email || '')"></span>
                                </td>
                                <td class="py-3 px-4">
                                    <span x-show="row.order_id">
                                        <span class="font-mono text-xs" x-text="'#' + row.order_id"></span>
                                        <span class="text-slate-500 text-xs" x-text="row.biz_order_no ? (' ' + row.biz_order_no) : ''"></span>
                                    </span>
                                    <span x-show="!row.order_id">—</span>
                                </td>
                                <td class="py-3 px-4">
                                    <span x-show="row.product_id" x-text="(row.product_name || '') + ' (#' + row.product_id + ')'"></span>
                                    <span x-show="!row.product_id">—</span>
                                </td>
                                <td class="py-3 px-4 font-mono text-xs" x-text="row.reseller_id != null ? row.reseller_id : '—'"></td>
                                <td class="py-3 px-4 max-w-md"><pre class="text-xs font-mono whitespace-pre-wrap break-all text-slate-600" x-text="formatAuditMeta(row.meta)"></pre></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <p x-show="provisionAuditLogs.length === 0" class="py-6 text-center text-zinc-500 text-sm">暂无审计记录（新数据在开通/释放操作之后才会出现）。</p>
            <div x-show="provisionAuditTotal > provisionAuditPerPage" class="flex flex-wrap items-center justify-between gap-2 border-t border-zinc-100 px-4 py-3 text-sm text-slate-600">
                <span>共 <span x-text="provisionAuditTotal"></span> 条，第 <span x-text="provisionAuditPage"></span> / <span x-text="Math.max(1, Math.ceil(provisionAuditTotal / provisionAuditPerPage))"></span> 页</span>
                <div class="flex gap-2">
                    <button type="button" class="console-btn-secondary text-xs px-3 py-1" :disabled="provisionAuditPage <= 1" @click.prevent="provisionAuditPage--; loadProvisionAuditLogs()">上一页</button>
                    <button type="button" class="console-btn-secondary text-xs px-3 py-1" :disabled="provisionAuditPage * provisionAuditPerPage >= provisionAuditTotal" @click.prevent="provisionAuditPage++; loadProvisionAuditLogs()">下一页</button>
                </div>
            </div>
        </div>
    </div>

    {{-- 支付设置（易支付 / 分销商充值） --}}
    <div x-show="tab === 'payment_settings'" class="space-y-6 max-w-3xl" x-cloak>
        <div class="console-card p-5">
            <h2 class="text-lg font-semibold text-slate-900">支付设置</h2>
            <p class="mt-1 text-sm text-slate-500">配置彩虹易支付（分销商门户在线充值）。此处保存后优先于 <code class="text-xs bg-slate-100 px-1 rounded">.env</code> 中的 EPAY_*；密钥使用应用加密后写入数据库。</p>
        </div>
        <div class="console-card p-5">
            <div class="flex items-center justify-between gap-3 mb-4">
                <h3 class="font-semibold text-slate-900">易支付</h3>
                <button type="button" class="console-btn-secondary text-sm" @click="loadPaymentSettings()" :disabled="paymentSettings.loading">重新加载</button>
            </div>
            <form class="space-y-4" @submit.prevent="savePaymentSettings()">
                <div class="flex flex-wrap items-center gap-6">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" class="rounded border-slate-300" x-model="paymentSettings.epay_enabled">
                        <span>开启在线支付（分销商可跳转易支付充值）</span>
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" class="rounded border-slate-300" x-model="paymentSettings.epay_allow_simulated_recharge">
                        <span>允许模拟充值（仅建议开发环境）</span>
                    </label>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">API 地址（站点根 URL，不含 submit.php）</label>
                    <input type="url" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                           x-model="paymentSettings.epay_gateway"
                           placeholder="https://pay.example.com">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">商户 ID（pid）</label>
                    <input type="text" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                           x-model="paymentSettings.epay_pid"
                           placeholder="1000">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">MD5 密钥</label>
                    <input type="password" autocomplete="new-password"
                           class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                           x-model="paymentSettings.epay_key"
                           placeholder="留空表示不修改已保存的密钥">
                    <p class="mt-1 text-xs text-slate-500" x-show="paymentSettings.epay_key_set">
                        当前已配置密钥：<span class="font-mono" x-text="paymentSettings.epay_key_hint || '已加密存储'"></span>
                    </p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">异步通知地址（回调 URL）</label>
                    <input type="url" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                           x-model="paymentSettings.epay_notify_url"
                           placeholder="留空则使用默认（见下方）">
                    <p class="mt-1 text-xs text-slate-500 break-all">实际生效：<span x-text="paymentSettings.epay_notify_url_effective || '—'"></span></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">同步跳转地址（支付完成回站）</label>
                    <input type="url" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                           x-model="paymentSettings.epay_return_url"
                           placeholder="留空则使用默认（见下方）">
                    <p class="mt-1 text-xs text-slate-500 break-all">实际生效：<span x-text="paymentSettings.epay_return_url_effective || '—'"></span></p>
                </div>
                <div class="flex gap-2 pt-2">
                    <button type="submit" class="console-btn-primary" :disabled="paymentSettings.saving">
                        <span x-show="!paymentSettings.saving">保存设置</span>
                        <span x-show="paymentSettings.saving">保存中…</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Redis / 公开接口限流 --}}
    <div x-show="tab === 'runtime_settings'" class="space-y-6 max-w-3xl" x-cloak>
        <div class="console-card p-5">
            <h2 class="text-lg font-semibold text-slate-900">安全与限流</h2>
            <p class="mt-1 text-sm text-slate-500">Redis 是否在 <code class="text-xs bg-slate-100 px-1 rounded">.env</code> 中显式配置且能连通，由启动时自动检测；下方可调整公开接口限流（<strong>IP + 路径</strong>，次/分钟）。</p>
        </div>
        <div class="console-card p-5">
            <div class="flex items-center justify-between gap-3 mb-3">
                <h3 class="font-semibold text-slate-900">Redis（自动）</h3>
                <button type="button" class="console-btn-secondary text-sm" @click="loadRuntimeSettings()" :disabled="runtimeSettings.loading">重新加载</button>
            </div>
            <p class="text-sm text-slate-600" x-show="runtimeSettings.redis_env_configured && runtimeSettings.redis_connection_ok">
                已在 <code class="rounded bg-slate-100 px-1 text-xs">.env</code> 中配置 Redis 且连接校验通过；当前请求下缓存 / Session / 队列使用 Redis。
            </p>
            <p class="text-sm text-amber-800" x-show="runtimeSettings.redis_env_configured && !runtimeSettings.redis_connection_ok">
                已在 <code class="rounded bg-slate-100 px-1 text-xs">.env</code> 中填写 <code class="rounded bg-amber-50 px-1 text-xs">REDIS_URL</code> 或 <code class="rounded bg-amber-50 px-1 text-xs">REDIS_HOST</code>，但连接失败，已回退为数据库驱动（见服务器日志）。
            </p>
            <p class="text-sm text-slate-600" x-show="!runtimeSettings.redis_env_configured">
                未在 <code class="rounded bg-slate-100 px-1 text-xs">.env</code> 中显式配置 <code class="rounded bg-slate-100 px-1 text-xs">REDIS_URL</code> / <code class="rounded bg-slate-100 px-1 text-xs">REDIS_HOST</code>，不启用 Redis 栈；缓存 / Session / 队列以 <code class="rounded bg-slate-100 px-1 text-xs">.env</code> 中的 <code class="rounded bg-slate-100 px-1 text-xs">CACHE_STORE</code> 等为准。
            </p>
        </div>
        <div class="console-card p-5">
            <h3 class="font-semibold text-slate-900 mb-3">公开接口限流（次/分钟）</h3>
            <form class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm" @submit.prevent="saveRuntimeSettings()">
                <div>
                    <label class="block font-medium text-slate-700">POST /api/v1/auth/login</label>
                    <input type="number" min="1" max="100000" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
                           x-model.number="runtimeSettings.rate_limits.auth_login">
                </div>
                <div>
                    <label class="block font-medium text-slate-700">POST /api/v1/auth/register</label>
                    <input type="number" min="1" max="100000" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
                           x-model.number="runtimeSettings.rate_limits.auth_register">
                </div>
                <div>
                    <label class="block font-medium text-slate-700">POST /api/v1/reseller/validate</label>
                    <input type="number" min="1" max="100000" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
                           x-model.number="runtimeSettings.rate_limits.reseller_validate">
                </div>
                <div>
                    <label class="block font-medium text-slate-700">POST /api/v1/reseller-portal/register</label>
                    <input type="number" min="1" max="100000" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
                           x-model.number="runtimeSettings.rate_limits.reseller_portal_register">
                </div>
                <div>
                    <label class="block font-medium text-slate-700">POST /api/v1/reseller-portal/login</label>
                    <input type="number" min="1" max="100000" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
                           x-model.number="runtimeSettings.rate_limits.reseller_portal_login">
                </div>
                <div>
                    <label class="block font-medium text-slate-700">易支付异步通知 /api/v1/payments/epay/notify</label>
                    <input type="number" min="1" max="100000" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
                           x-model.number="runtimeSettings.rate_limits.epay_notify">
                </div>
                <div class="sm:col-span-2 flex gap-2 pt-2">
                    <button type="submit" class="console-btn-primary" :disabled="runtimeSettings.saving">
                        <span x-show="!runtimeSettings.saving">保存设置</span>
                        <span x-show="runtimeSettings.saving">保存中…</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- 账户安全：修改密码（Laravel validate + Hash::check + hashed cast） --}}
    <div x-show="tab === 'security'" class="space-y-6">
        <div class="console-card p-5">
            <h2 class="text-lg font-semibold text-slate-900">修改密码</h2>
            <p class="mt-1 text-sm text-slate-500">使用当前密码验证后设置新密码（至少 8 位）。成功后其他设备上的管理后台登录将失效，当前会话保留。</p>
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

    {{-- 帮助与 FAQ（与当前 A 站管理后台功能对齐） --}}
    <div x-show="tab === 'help'" class="space-y-6 max-w-3xl">
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b border-zinc-100 bg-zinc-50">
                <h3 class="font-medium text-zinc-800">入门与导航</h3>
            </div>
            <div class="p-5 text-sm text-zinc-600 space-y-2">
                <p>本页为 <strong>A 站管理后台</strong>：侧栏「分销」下 <strong>用户管理</strong> 列出通过分销商同步/开通的<strong>终端用户</strong>（B 站侧注册用户，按邮箱+分销商去重）；<strong>已购产品</strong> 按每条 A 站订阅订单一行，与 B 站已购列表对齐。「支持」中的 <strong>管理员列表</strong> 维护可登录本控制面的 <code class="text-xs bg-zinc-100 px-1 rounded">users</code> 账号（角色 user/admin）。另有控制台、数据分析、产品与定价、订单、服务器与 IP 池、分销商、支付设置、安全与限流等模块。</p>
                <p>右上角菜单可 <strong>修改登录密码</strong>（验证当前密码后更新；其他设备上的管理后台 Token 会失效）或 <strong>退出登录</strong>。侧栏「账户安全」与右上角改密为同一能力。</p>
                <p>侧栏「<strong>支付设置</strong>」可配置分销商在线充值使用的易支付（API 地址、商户 ID、密钥、回调与跳转 URL、模拟充值开关）；保存后写入数据库并优先于 <code class="text-xs bg-zinc-100 px-1 rounded">.env</code> 中的 EPAY_*。</p>
                <p>侧栏「<strong>安全与限流</strong>」可查看 Redis 是否按环境生效，并配置公开接口（登录、注册、校验 Key、易支付回调等）的限流次数；不写 Redis 配置则沿用缓存/会话的默认驱动。</p>
                <p>业务关系简述：<strong>管理员列表</strong>中的平台账号可通过「订单」直接关联产品与开通；<strong>分销商</strong>通过 API Key 在 B 站调用接口，为其下游维护「用户管理」中的终端用户及「已购产品」中的订阅订单。</p>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b border-zinc-100 bg-zinc-50">
                <h3 class="font-medium text-zinc-800">控制台与数据分析</h3>
            </div>
            <div class="p-5 text-sm text-zinc-600 space-y-2">
                <p><strong>控制台</strong>展示汇总卡片：服务器总数、业务流水（唯一单号及新购/续费笔数）、<strong>管理员/平台账号数</strong>、本月<strong>平台成本</strong>、<strong>销售金额</strong>、<strong>利润</strong>与<strong>现金覆盖</strong>等。快捷按钮可进入「用户管理」（终端用户）与「管理员列表」。下方有「最近服务器」列表。</p>
                <p><strong>数据分析</strong>提供更细统计：<strong>管理员/平台账号数</strong>、<strong>分销商终端用户数（去重）</strong>及活跃数、订单、本月成本与充值、收入流水、销售/利润/现金覆盖；并含 <strong>Top 分销商</strong>与 <strong>Top 产品</strong>，可点击「刷新」重拉。</p>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b border-zinc-100 bg-zinc-50">
                <h3 class="font-medium text-zinc-800">用户管理、已购产品与订单</h3>
            </div>
            <div class="p-5 text-sm text-zinc-600 space-y-2">
                <p><strong>用户管理</strong>（分销）：终端用户列表，数据来自分销商侧同步与开通；同一邮箱+分销商多笔订阅不会在列表中重复出现；「最近购买/到期」取该终端用户名下最新一条分销订单。支持查看/编辑 RADIUS、WireGuard 等。</p>
                <p><strong>已购产品</strong>：每条记录对应一条通过分销商产生的 <strong>A 站订阅订单</strong>（与 B 站 a_order_id 对齐），用于按「已购」维度排查与复制配置。</p>
                <p><strong>订单</strong>：创建或查看平台侧订单，支持筛选；详情中可查看<strong>收入流水</strong>（与分销商扣款/同步相关）。</p>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b border-zinc-100 bg-zinc-50">
                <h3 class="font-medium text-zinc-800">产品与定价</h3>
            </div>
            <div class="p-5 text-sm text-zinc-600 space-y-2">
                <p>在「产品与定价」中新增或编辑产品：名称、价格（元）、时长（天）等。可为产品配置是否启用 <strong>RADIUS</strong>、<strong>WireGuard</strong> 等能力（界面以组合方式展示）。产品创建后，即可在「订单」中与平台用户组合下单。</p>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b border-zinc-100 bg-zinc-50">
                <h3 class="font-medium text-zinc-800">接入服务器、NAT 与 IP 池</h3>
            </div>
            <div class="p-5 text-sm text-zinc-600 space-y-2">
                <p><strong>接入服务器</strong>：登记节点（主机名、区域、角色、SSH 连接信息、备注等）；可维护与成本相关的字段（如月成本，用于分析页成本口径）。列表中可查看最近心跳时间（若节点侧上报）。</p>
                <p><strong>NAT 服务器</strong>：为接入服务器绑定出口 IP、区域等，用于线路与成本统计。</p>
                <p><strong>IP 池</strong>：维护可分配地址，支持释放等操作，供开通或调度使用。</p>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b border-zinc-100 bg-zinc-50">
                <h3 class="font-medium text-zinc-800">分销商</h3>
            </div>
            <div class="p-5 text-sm text-zinc-600 space-y-2">
                <p><strong>分销商</strong>：创建分销商账号后，可为其<strong>生成 / 重置 API Key</strong>（供 B 站或对接系统使用）；查看<strong>余额</strong>、打开<strong>余额流水</strong>（分页/筛选），必要时可通过后台做<strong>余额调整</strong>。分销商亦可通过 A 站「分销商门户」自助注册/登录（与后台「分销商」数据同源逻辑时，以实际部署为准）。</p>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b border-zinc-100 bg-zinc-50">
                <h3 class="font-medium text-zinc-800">常见问题</h3>
            </div>
            <div class="p-5 text-sm text-zinc-600 space-y-4">
                <div>
                    <p class="font-medium text-zinc-800">从哪开始配置一条可售卖的线路？</p>
                    <p class="mt-1">建议顺序：「接入服务器」添加节点 →「NAT 服务器」绑定出口 →「产品与定价」新建套餐 → 在「管理员列表」中有平台账号后，于「订单」中选用户与产品下单；若走分销，则在「分销商」生成 API Key，由 B 站或接口为终端用户开通（见「用户管理」与「已购产品」）。</p>
                </div>
                <div>
                    <p class="font-medium text-zinc-800">「用户管理」和「管理员列表」有什么区别？</p>
                    <p class="mt-1">侧栏分销下的 <strong>用户管理</strong> 面向 <strong>B 站/分销商侧的终端用户</strong>（vpn_users，按邮箱+分销商去重）。「支持」中的 <strong>管理员列表</strong> 面向可登录本控制面的 <strong>平台账号</strong>（users 表，角色 user/admin）。二者数据表与用途不同，请勿混淆。</p>
                </div>
                <div>
                    <p class="font-medium text-zinc-800">控制台上的成本、利润、现金覆盖是什么口径？</p>
                    <p class="mt-1">与分析页一致：成本多为<strong>本月累计</strong>的服务器 / NAT 等摊销；销售金额为扣款口径的本月累计；利润 ≈ 销售 − 平台成本；现金覆盖 ≈ 分销商充值入账 − 平台成本（本月累计）。详细算法以后台接口返回说明为准。</p>
                </div>
                <div>
                    <p class="font-medium text-zinc-800">如何给分销商开通 API？</p>
                    <p class="mt-1">在「分销商」中新增分销商，使用「生成 API Key」或「重置 API Key」将密钥安全交给对方；对方在 B 站配置 A 站地址与该 Key 即可调用开通、同步用户等接口。可在本页「分销商」查阅余额与流水。</p>
                </div>
                <div>
                    <p class="font-medium text-zinc-800">忘记管理员密码怎么办？</p>
                    <p class="mt-1">若仍可登录：使用右上角「修改密码」。若完全无法登录：需由运维在服务器上使用 <code class="text-xs bg-zinc-100 px-1 rounded">php artisan</code> 等方式重置用户表密码或新建管理员（具体命令依部署文档）。</p>
                </div>
            </div>
        </div>
    </div>
</div>

    {{-- 接入服务器模态框样式 --}}
    <style>
        .as-modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.45);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            transition: opacity 0.2s ease-out;
        }
        .as-modal-backdrop.show {
            display: flex;
            opacity: 1;
        }
        .as-modal {
            background: #ffffff;
            border-radius: 10px;
            box-shadow:
                0 18px 45px rgba(15, 23, 42, 0.25),
                0 0 0 1px rgba(148, 163, 184, 0.15);
            width: 100%;
            max-width: 980px;
            max-height: 92vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transform: translateY(12px) scale(0.98);
            opacity: 0;
            transition: opacity 0.18s ease-out, transform 0.18s ease-out;
        }
        .as-modal.open {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
        .as-modal.fullscreen {
            max-width: 1280px;
            max-height: 96vh;
        }
        .as-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 18px;
            border-bottom: 1px solid #e5e7eb;
        }
        .as-modal-title {
            font-size: 15px;
            font-weight: 600;
            color: #0f172a;
        }
        .as-modal-tools {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .as-modal-toggle {
            border: 1px solid #d1d5db;
            background: #fff;
            color: #1f2937;
            border-radius: 6px;
            font-size: 12px;
            padding: 4px 8px;
            cursor: pointer;
        }
        .as-modal-close {
            border: none;
            background: transparent;
            cursor: pointer;
            padding: 4px;
            border-radius: 999px;
            color: #6b7280;
            font-size: 18px;
        }
        .as-modal-close:hover {
            background-color: #e5e7eb;
            color: #111827;
        }
        .as-modal-body {
            padding: 14px 18px 6px;
            overflow-y: auto;
        }
        .as-modal-footer {
            padding: 10px 18px 12px;
            border-top: 1px solid #e5e7eb;
            background-color: #f9fafb;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }
        .as-form-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 10px 14px;
        }
        .as-field {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .as-section-title {
            grid-column: 1 / -1;
            margin-top: 6px;
            padding-top: 8px;
            border-top: 1px solid #e5e7eb;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.02em;
            color: #111827;
        }
        .as-label {
            font-size: 11px;
            font-weight: 500;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            color: #6b7280;
        }
        .as-input {
            border-radius: 6px;
            border: 1px solid #d1d5db;
            padding: 7px 9px;
            font-size: 13px;
        }
        .as-input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 1px rgba(37, 99, 235, 0.4);
        }
        @media (min-width: 640px) {
            .as-form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    {{-- 接入服务器：添加/编辑模态框 --}}
    <div class="as-modal-backdrop" id="as-edit-backdrop">
        <div class="as-modal" id="as-edit-modal" role="dialog" aria-modal="true">
            <div class="as-modal-header">
                <h3 class="as-modal-title" id="as-edit-title">添加接入服务器</h3>
                <div class="as-modal-tools">
                    <button type="button" class="as-modal-toggle" id="as-edit-fullscreen">全屏填写</button>
                    <button type="button" class="as-modal-close" id="as-edit-close" aria-label="关闭">&times;</button>
                </div>
            </div>
            <div class="as-modal-body">
                <form id="as-edit-form">
                    <input type="hidden" name="id" id="as-id">
                    <div class="as-form-grid">
                        <div class="as-section-title">基础信息</div>
                        <div class="as-field">
                            <label class="as-label" for="as-name">名称</label>
                            <input class="as-input" id="as-name" name="hostname" type="text">
                        </div>
                        <div class="as-field">
                            <label class="as-label" for="as-region">区域</label>
                            <input class="as-input" id="as-region" name="region" type="text">
                        </div>
                        <div class="as-field">
                            <label class="as-label" for="as-role">角色</label>
                            <input class="as-input" id="as-role" name="role" type="text">
                        </div>
                        <div class="as-field">
                            <label class="as-label" for="as-cost-cents">成本（分）</label>
                            <input class="as-input" id="as-cost-cents" name="cost_cents" type="number" min="0" step="1" placeholder="例如：500">
                        </div>
                        <div class="as-section-title">协议配置（WireGuard / OCServ）</div>
                        <div class="as-field">
                            <label class="as-label" for="as-protocol">协议类型</label>
                            <select class="as-input" id="as-protocol" name="protocol">
                                <option value="">—</option>
                                <option value="wireguard">WireGuard</option>
                                <option value="ocserv">OCServ</option>
                            </select>
                        </div>
                        <div class="as-field">
                            <label class="as-label" for="as-vpn-cidrs">VPN 内网 IP 范围（CIDR）</label>
                            <textarea class="as-input" id="as-vpn-cidrs" name="vpn_ip_cidrs" rows="3"
                                placeholder="例如：10.66.0.0/24,10.66.1.0/24"></textarea>
                        </div>
                        <div class="as-field" id="as-wg-fields">
                            <label class="as-label" for="as-wg-private-key">WireGuard 服务器私钥（可手动替换）</label>
                            <textarea class="as-input" id="as-wg-private-key" name="wg_private_key" rows="2"
                                placeholder="留空则自动生成；填写后公钥自动根据私钥计算"></textarea>
                        </div>
                        <div class="as-field" id="as-wg-fields-pub">
                            <label class="as-label" for="as-wg-public-key">WireGuard 服务器公钥</label>
                            <textarea class="as-input" id="as-wg-public-key" name="wg_public_key" rows="2"
                                placeholder="由私钥自动计算" readonly></textarea>
                        </div>
                        <div class="as-field">
                            <label class="as-label" for="as-wg-port">WireGuard 端口</label>
                            <input class="as-input" id="as-wg-port" name="wg_port" type="number" min="1" max="65535" placeholder="默认 51820">
                        </div>
                        <div class="as-field">
                            <label class="as-label" for="as-wg-dns">WireGuard 客户端 DNS</label>
                            <input class="as-input" id="as-wg-dns" name="wg_dns" type="text" placeholder="例如：1.1.1.1">
                        </div>
                        <div class="as-field" id="as-oc-radius-host-wrap">
                            <label class="as-label" for="as-oc-radius-host">OCServ RADIUS 服务器</label>
                            <input class="as-input" id="as-oc-radius-host" name="ocserv_radius_host" type="text" placeholder="例如：10.10.0.10">
                        </div>
                        <div class="as-field" id="as-oc-radius-auth-port-wrap">
                            <label class="as-label" for="as-oc-radius-auth-port">RADIUS Auth 端口</label>
                            <input class="as-input" id="as-oc-radius-auth-port" name="ocserv_radius_auth_port" type="number" min="1" max="65535" placeholder="默认 1812">
                        </div>
                        <div class="as-field" id="as-oc-radius-acct-port-wrap">
                            <label class="as-label" for="as-oc-radius-acct-port">RADIUS Acct 端口</label>
                            <input class="as-input" id="as-oc-radius-acct-port" name="ocserv_radius_acct_port" type="number" min="1" max="65535" placeholder="默认 1813">
                        </div>
                        <div class="as-field" id="as-oc-radius-secret-wrap">
                            <label class="as-label" for="as-oc-radius-secret">RADIUS 密钥</label>
                            <input class="as-input" id="as-oc-radius-secret" name="ocserv_radius_secret" type="password" autocomplete="new-password">
                        </div>
                        <div class="as-field" id="as-oc-port-wrap">
                            <label class="as-label" for="as-oc-port">OCServ 服务端口</label>
                            <input class="as-input" id="as-oc-port" name="ocserv_port" type="number" min="1" max="65535" placeholder="默认 443">
                        </div>
                        <div class="as-field" id="as-oc-domain-wrap">
                            <label class="as-label" for="as-oc-domain">绑定域名</label>
                            <input class="as-input" id="as-oc-domain" name="ocserv_domain" type="text" placeholder="例如 vpn.example.com">
                        </div>
                        <div class="as-field" id="as-oc-cert-wrap" style="grid-column:1 / -1;">
                            <label class="as-label" for="as-oc-cert">SSL 证书 PEM</label>
                            <textarea class="as-input" id="as-oc-cert" name="ocserv_tls_cert_pem" rows="3"></textarea>
                        </div>
                        <div class="as-field" id="as-oc-key-wrap" style="grid-column:1 / -1;">
                            <label class="as-label" for="as-oc-key">SSL 私钥 PEM</label>
                            <textarea class="as-input" id="as-oc-key" name="ocserv_tls_key_pem" rows="3"></textarea>
                        </div>
                        <div class="as-section-title">接入服务器 SSH 与基础网络</div>
                        <div class="as-field">
                            <label class="as-label" for="as-host">域名 / IP</label>
                            <input class="as-input" id="as-host" name="host" type="text">
                        </div>
                        <div class="as-field">
                            <label class="as-label" for="as-ssh-port">SSH 端口</label>
                            <input class="as-input" id="as-ssh-port" name="ssh_port" type="number" min="1" max="65535">
                        </div>
                        <div class="as-field">
                            <label class="as-label" for="as-root-user">Root 用户</label>
                            <input class="as-input" id="as-root-user" name="ssh_user" type="text">
                        </div>
                        <div class="as-field">
                            <label class="as-label" for="as-password">密码</label>
                            <input class="as-input" id="as-password" name="ssh_password" type="password" autocomplete="new-password">
                        </div>
                        <div class="as-field">
                            <label class="as-label" for="as-agent-enabled">启用 Agent</label>
                            <select class="as-input" id="as-agent-enabled" name="agent_enabled">
                                <option value="1">启用</option>
                                <option value="0">禁用</option>
                            </select>
                        </div>
                        <div class="as-section-title">NAT 拓扑与网卡角色</div>
                        <div class="as-field">
                            <label class="as-label" for="as-nat-topology">NAT 拓扑</label>
                            <select class="as-input" id="as-nat-topology" name="nat_topology">
                                <option value="combined">一体（本机兼接入+NAT，双公网等）</option>
                                <option value="split_access">分体（接入侧，CN 公网 + 与 NAT 互联）</option>
                            </select>
                        </div>
                        <div class="as-field" id="as-cn-public-iface-wrap">
                            <label class="as-label" for="as-cn-public-iface">CN 公网网卡</label>
                            <input class="as-input" id="as-cn-public-iface" name="cn_public_iface" type="text" placeholder="一体/分体接入：如 eth0">
                        </div>
                        <div class="as-field" id="as-hk-public-iface-wrap">
                            <label class="as-label" for="as-hk-public-iface">HK 公网网卡</label>
                            <input class="as-input" id="as-hk-public-iface" name="hk_public_iface" type="text" placeholder="一体：eth1；分体 NAT：通常 eth0">
                        </div>
                        <div class="as-field" id="as-peer-link-iface-wrap">
                            <label class="as-label" for="as-peer-link-iface">与对端互联网卡</label>
                            <input class="as-input" id="as-peer-link-iface" name="peer_link_iface" type="text" placeholder="分体两侧可都填 eth1 等">
                        </div>
                        <div class="as-field" id="as-peer-link-local-ip-wrap">
                            <label class="as-label" for="as-peer-link-local-ip">本机互联 IP</label>
                            <input class="as-input" id="as-peer-link-local-ip" name="peer_link_local_ip" type="text" placeholder="可选：10.0.0.1/30">
                        </div>
                        <div class="as-field" id="as-peer-link-remote-ip-wrap">
                            <label class="as-label" for="as-peer-link-remote-ip">对端互联 IP</label>
                            <input class="as-input" id="as-peer-link-remote-ip" name="peer_link_remote_ip" type="text" placeholder="可选：10.0.0.2">
                        </div>
                        <div class="as-field" id="as-link-tunnel-type-wrap">
                            <label class="as-label" for="as-link-tunnel-type">互联类型</label>
                            <select class="as-input" id="as-link-tunnel-type" name="link_tunnel_type">
                                <option value="">— 直连二层 / 静态路由</option>
                                <option value="wireguard">WireGuard 隧道</option>
                                <option value="gre">GRE</option>
                                <option value="vxlan">VXLAN</option>
                                
                            </select>
                        </div>
                        <div class="as-section-title">分体模式：NAT 服务器信息</div>
                        <div class="as-field" id="as-split-nat-host-wrap">
                            <label class="as-label" for="as-split-nat-host">分体 NAT 服务器 IP/域名</label>
                            <input class="as-input" id="as-split-nat-host" name="split_nat_host" type="text" placeholder="例如：203.0.113.20">
                        </div>
                        <div class="as-field" id="as-split-nat-ssh-port-wrap">
                            <label class="as-label" for="as-split-nat-ssh-port">分体 NAT SSH 端口</label>
                            <input class="as-input" id="as-split-nat-ssh-port" name="split_nat_ssh_port" type="number" min="1" max="65535" placeholder="默认 22">
                        </div>
                        <div class="as-field" id="as-split-nat-ssh-user-wrap">
                            <label class="as-label" for="as-split-nat-ssh-user">分体 NAT SSH 用户</label>
                            <input class="as-input" id="as-split-nat-ssh-user" name="split_nat_ssh_user" type="text" placeholder="默认 root">
                        </div>
                        <div class="as-field" id="as-split-nat-ssh-password-wrap">
                            <label class="as-label" for="as-split-nat-ssh-password">分体 NAT SSH 密码</label>
                            <input class="as-input" id="as-split-nat-ssh-password" name="split_nat_ssh_password" type="password" autocomplete="new-password">
                        </div>
                        <div class="as-field" id="as-split-nat-hk-public-iface-wrap">
                            <label class="as-label" for="as-split-nat-hk-public-iface">分体 NAT HK 公网网卡</label>
                            <input class="as-input" id="as-split-nat-hk-public-iface" name="split_nat_hk_public_iface" type="text" placeholder="例如：eth0">
                        </div>
                        <div class="as-field">
                            <label class="as-label" for="as-notes">备注</label>
                            <input class="as-input" id="as-notes" name="notes" type="text">
                        </div>
                    </div>
                </form>
            </div>
            <div class="as-modal-footer">
                <button type="button" class="as-btn as-btn-secondary" id="as-edit-cancel">取消</button>
                <button type="button" class="as-btn as-btn-primary" id="as-edit-save">保存</button>
            </div>
        </div>
    </div>

    {{-- 接入服务器：删除确认模态框 --}}
    <div class="as-modal-backdrop" id="as-delete-backdrop">
        <div class="as-modal" id="as-delete-modal" role="dialog" aria-modal="true">
            <div class="as-modal-header">
                <h3 class="as-modal-title">删除确认</h3>
                <button type="button" class="as-modal-close" id="as-delete-close" aria-label="关闭">&times;</button>
            </div>
            <div class="as-modal-body">
                <p>确定要删除此项吗？</p>
            </div>
            <div class="as-modal-footer">
                <button type="button" class="as-btn as-btn-secondary" id="as-delete-cancel">取消</button>
                <button type="button" class="as-btn as-btn-danger" id="as-delete-confirm">确认删除</button>
            </div>
        </div>
    </div>

    {{-- 产品：添加/编辑模态框 --}}
    <div class="as-modal-backdrop" id="prod-edit-backdrop">
        <div class="as-modal" id="prod-edit-modal" role="dialog" aria-modal="true">
            <div class="as-modal-header">
                <h3 class="as-modal-title" id="prod-edit-title">添加产品</h3>
                <button type="button" class="as-modal-close" id="prod-edit-close" aria-label="关闭">&times;</button>
            </div>
            <div class="as-modal-body">
                <form id="prod-edit-form">
                    <input type="hidden" name="id" id="prod-id">
                    <div class="as-form-grid">
                        <div class="as-field">
                            <label class="as-label" for="prod-name">名称</label>
                            <input class="as-input" id="prod-name" name="name" type="text">
                        </div>
                        <div class="as-field">
                            <label class="as-label" for="prod-price">价格（元）</label>
                            <input class="as-input" id="prod-price" name="price_yuan" type="number" step="0.01" min="0">
                        </div>
                        <div class="as-field">
                            <label class="as-label" for="prod-days">时长(天)</label>
                            <input class="as-input" id="prod-days" name="duration_days" type="number">
                        </div>
                        <div class="as-field">
                            <label class="as-label">开通协议</label>
                            <div class="flex flex-col gap-2 text-sm">
                                <label class="inline-flex items-center gap-2">
                                    <input type="checkbox" id="prod-enable-radius" name="enable_radius" value="1" class="rounded border-zinc-300">
                                    <span>SSL VPN / FreeRADIUS（用户下单时填写专用账号密码；登录名为「用户名@分销商ID」）</span>
                                </label>
                                <label class="inline-flex items-center gap-2">
                                    <input type="checkbox" id="prod-enable-wireguard" name="enable_wireguard" value="1" class="rounded border-zinc-300">
                                    <span>WireGuard（需选区域；无需填写 RADIUS 账号密码）</span>
                                </label>
                                <label class="inline-flex items-center gap-2">
                                    <input type="checkbox" id="prod-requires-dedicated-public-ip" name="requires_dedicated_public_ip" value="1" class="rounded border-zinc-300">
                                    <span>需要独立公网 IP（按接入服务器与 IP 池绑定分配）</span>
                                </label>
                            </div>
                            <p class="mt-1 text-[11px] text-slate-500">至少勾选一项；未勾选则不会生成对应配置（仅 SSL、仅 WG 或两者兼有）。</p>
                        </div>
                        <div class="as-field as-field-span-full">
                            <label class="as-label" for="prod-bandwidth-kbps">每用户带宽上限（Kbps）</label>
                            <input class="as-input" id="prod-bandwidth-kbps" name="bandwidth_limit_kbps" type="number" min="1" placeholder="留空表示不限速">
                            <p class="mt-1 text-[11px] text-slate-500">仅对 WireGuard 生效；节点用 tc 按内网 IP 对称限速。</p>
                        </div>
                        <div class="as-field as-field-span-full">
                            <label class="as-label" for="prod-traffic-quota-gb">流量额度（GiB / 当前有效订单周期）</label>
                            <input class="as-input" id="prod-traffic-quota-gb" name="traffic_quota_gb" type="number" step="0.01" min="0" placeholder="留空表示不限流量">
                            <p class="mt-1 text-[11px] text-slate-500">用量由 Agent 上报 WireGuard 计数写入；超额后心跳下发剔除 peer。</p>
                        </div>
                    </div>
                </form>
            </div>
            <div class="as-modal-footer">
                <button type="button" class="as-btn as-btn-secondary" id="prod-edit-cancel">取消</button>
                <button type="button" class="as-btn as-btn-primary" id="prod-edit-save">保存</button>
            </div>
        </div>
    </div>

    {{-- 产品：删除确认模态框 --}}
    <div class="as-modal-backdrop" id="prod-delete-backdrop">
        <div class="as-modal" id="prod-delete-modal" role="dialog" aria-modal="true">
            <div class="as-modal-header">
                <h3 class="as-modal-title">删除确认</h3>
                <button type="button" class="as-modal-close" id="prod-delete-close" aria-label="关闭">&times;</button>
            </div>
            <div class="as-modal-body">
                <p>确定要删除此项吗？</p>
            </div>
            <div class="as-modal-footer">
                <button type="button" class="as-btn as-btn-secondary" id="prod-delete-cancel">取消</button>
                <button type="button" class="as-btn as-btn-danger" id="prod-delete-confirm">确认删除</button>
            </div>
        </div>
    </div>

    {{-- IP池：添加模态框 --}}
    <div class="as-modal-backdrop" id="ip-edit-backdrop">
        <div class="as-modal" id="ip-edit-modal" role="dialog" aria-modal="true">
            <div class="as-modal-header">
                <h3 class="as-modal-title" id="ip-edit-title">添加 IP</h3>
                <button type="button" class="as-modal-close" id="ip-edit-close" aria-label="关闭">&times;</button>
            </div>
            <div class="as-modal-body">
                <form id="ip-edit-form">
                    <input type="hidden" name="id" id="ip-id">
                    <div class="as-form-grid">
                        <div class="as-field">
                            <label class="as-label" for="ip-addr">IP 地址（单个，可选）</label>
                            <input class="as-input" id="ip-addr" name="ip_address" type="text" placeholder="例如：10.0.0.1">
                        </div>
                        <div class="as-field">
                            <label class="as-label" for="ip-region">区域</label>
                            <input class="as-input" id="ip-region" name="region" type="text" placeholder="例如：cn-hongkong">
                        </div>
                        <div class="as-field">
                            <label class="as-label" for="ip-server-id">绑定接入服务器ID（可选）</label>
                            <input class="as-input" id="ip-server-id" name="server_id" type="number" min="1" step="1" placeholder="留空=区域通用IP池">
                        </div>
                        <div class="as-field" style="grid-column: 1 / -1;">
                            <label class="as-label" for="ip-batch">批量添加（每行一个：单 IP / C 段 / IP 范围）</label>
                            <textarea class="as-input" id="ip-batch" rows="4"
                                placeholder="示例：&#10;10.0.0.1&#10;10.0.1.0/24&#10;10.0.2.10-10.0.2.50"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="as-modal-footer">
                <button type="button" class="as-btn as-btn-secondary" id="ip-edit-cancel">取消</button>
                <button type="button" class="as-btn as-btn-primary" id="ip-edit-save">保存</button>
            </div>
        </div>
    </div>

    {{-- IP池：释放确认模态框 --}}
    <div class="as-modal-backdrop" id="ip-release-backdrop">
        <div class="as-modal" id="ip-release-modal" role="dialog" aria-modal="true">
            <div class="as-modal-header">
                <h3 class="as-modal-title">释放确认</h3>
                <button type="button" class="as-modal-close" id="ip-release-close" aria-label="关闭">&times;</button>
            </div>
            <div class="as-modal-body">
                <p>确定要释放该 IP 吗？</p>
            </div>
            <div class="as-modal-footer">
                <button type="button" class="as-btn as-btn-secondary" id="ip-release-cancel">取消</button>
                <button type="button" class="as-btn as-btn-danger" id="ip-release-confirm">确认释放</button>
            </div>
        </div>
    </div>

    {{-- 分销商：添加/编辑模态框 --}}
    <div class="as-modal-backdrop" id="reseller-edit-backdrop">
        <div class="as-modal" id="reseller-edit-modal" role="dialog" aria-modal="true">
            <div class="as-modal-header">
                <h3 class="as-modal-title" id="reseller-edit-title">添加分销商</h3>
                <button type="button" class="as-modal-close" id="reseller-edit-close" aria-label="关闭">&times;</button>
            </div>
            <div class="as-modal-body">
                <form id="reseller-edit-form">
                    <input type="hidden" name="id" id="reseller-id">
                    <div class="as-form-grid">
                        <div class="as-field">
                            <label class="as-label" for="reseller-name">名称</label>
                            <input class="as-input" id="reseller-name" name="name" type="text" placeholder="分销商名称">
                        </div>
                        <div class="as-field">
                            <label class="as-label" for="reseller-email">邮箱（可空）</label>
                            <input class="as-input" id="reseller-email" name="email" type="email" placeholder="reseller@example.com">
                        </div>
                        <div class="as-field">
                            <label class="as-label" for="reseller-password">密码（留空不修改）</label>
                            <input class="as-input" id="reseller-password" name="password" type="password" placeholder="至少 8 位">
                        </div>
                        <div class="as-field">
                            <label class="as-label" for="reseller-balance-cents">余额（分）</label>
                            <input class="as-input" id="reseller-balance-cents" name="balance_cents" type="number" min="0" step="1" placeholder="例如：1000">
                        </div>
                        <div class="as-field">
                            <label class="as-label" for="reseller-balance-enforced">扣费开关</label>
                            <select class="as-input" id="reseller-balance-enforced" name="balance_enforced">
                                <option value="0">未启用</option>
                                <option value="1">已启用</option>
                            </select>
                        </div>
                        <div class="as-field" style="grid-column: 1 / -1;">
                            <label class="as-label" for="reseller-status">状态</label>
                            <select class="as-input" id="reseller-status" name="status">
                                <option value="active">active</option>
                                <option value="suspended">suspended</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="as-modal-footer">
                <button type="button" class="as-btn as-btn-secondary" id="reseller-edit-cancel">取消</button>
                <button type="button" class="as-btn as-btn-primary" id="reseller-edit-save">保存</button>
            </div>
        </div>
    </div>

    {{-- 分销商：删除确认模态框 --}}
    <div class="as-modal-backdrop" id="reseller-delete-backdrop">
        <div class="as-modal" id="reseller-delete-modal" role="dialog" aria-modal="true">
            <div class="as-modal-header">
                <h3 class="as-modal-title">删除确认</h3>
                <button type="button" class="as-modal-close" id="reseller-delete-close" aria-label="关闭">&times;</button>
            </div>
            <div class="as-modal-body">
                <p>确定要删除该分销商吗？</p>
            </div>
            <div class="as-modal-footer">
                <button type="button" class="as-btn as-btn-secondary" id="reseller-delete-cancel">取消</button>
                <button type="button" class="as-btn as-btn-danger" id="reseller-delete-confirm">确认删除</button>
            </div>
        </div>
    </div>

    {{-- 终端用户（用户管理）：查看/编辑模态框 --}}
    <div class="as-modal-backdrop" id="vpn-edit-backdrop">
        <div class="as-modal" id="vpn-edit-modal" role="dialog" aria-modal="true">
            <div class="as-modal-header">
                <h3 class="as-modal-title" id="vpn-edit-title">终端用户详情</h3>
                <button type="button" class="as-modal-close" id="vpn-edit-close" aria-label="关闭">&times;</button>
            </div>
            <div class="as-modal-body">
                <div class="space-y-4">
                    <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-3 text-sm text-zinc-700">
                        <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                            <div><span class="text-zinc-500">所属用户：</span><span id="vpn-owner">-</span></div>
                            <div><span class="text-zinc-500">分销商：</span><span id="vpn-reseller">-</span></div>
                            <div><span class="text-zinc-500">最近订单：</span><span id="vpn-order">-</span></div>
                            <div><span class="text-zinc-500">到期时间：</span><span id="vpn-expire">-</span></div>
                        </div>
                    </div>

                    <div class="rounded-lg border border-violet-200 bg-violet-50/60 p-3 text-sm">
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                            <span class="font-medium text-violet-900">WireGuard 配置</span>
                            <button type="button" class="text-xs text-violet-700 underline" id="vpn-wg-reload">重新加载</button>
                        </div>
                        <p id="vpn-wg-msg" class="text-xs text-amber-800 mb-2 min-h-[1rem]"></p>
                        <textarea id="vpn-wg-config" class="w-full min-h-[200px] font-mono text-[11px] leading-relaxed rounded border border-violet-200 bg-white p-2 text-zinc-800" readonly placeholder="打开详情后自动加载；若无 Peer 或私钥未保存将显示原因。"></textarea>
                    </div>

                    <form id="vpn-edit-form">
                        <input type="hidden" name="id" id="vpn-id">
                        <div class="as-form-grid">
                            <div class="as-field">
                                <label class="as-label" for="vpn-name">VPN 名称</label>
                                <input class="as-input" id="vpn-name" name="name" type="text">
                            </div>
                            <div class="as-field">
                                <label class="as-label" for="vpn-status">状态</label>
                                <select class="as-input" id="vpn-status" name="status">
                                    <option value="active">active</option>
                                    <option value="disabled">disabled</option>
                                    <option value="suspended">suspended</option>
                                </select>
                            </div>
                            <div class="as-field">
                                <label class="as-label" for="vpn-region">区域</label>
                                <input class="as-input" id="vpn-region" name="region" type="text" placeholder="例如：CN-HK">
                            </div>
                            <div class="as-field">
                                <label class="as-label" for="vpn-radius-user">RADIUS 用户名</label>
                                <input class="as-input" id="vpn-radius-user" name="radius_username" type="text">
                            </div>
                            <div class="as-field">
                                <label class="as-label" for="vpn-radius-pass">RADIUS 密码</label>
                                <input class="as-input" id="vpn-radius-pass" name="radius_password" type="text" placeholder="留空则不改">
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <div class="as-modal-footer">
                <button type="button" class="as-btn as-btn-secondary" id="vpn-edit-cancel">关闭</button>
                <button type="button" class="as-btn as-btn-primary" id="vpn-edit-save">保存</button>
            </div>
        </div>
    </div>

    {{-- 终端用户：删除确认模态框 --}}
    <div class="as-modal-backdrop" id="vpn-delete-backdrop">
        <div class="as-modal" id="vpn-delete-modal" role="dialog" aria-modal="true">
            <div class="as-modal-header">
                <h3 class="as-modal-title">删除确认</h3>
                <button type="button" class="as-modal-close" id="vpn-delete-close" aria-label="关闭">&times;</button>
            </div>
            <div class="as-modal-body">
                <p>确定要删除该终端用户记录吗？（若仍存在有效订阅订单，请先处理订单侧数据。）</p>
            </div>
            <div class="as-modal-footer">
                <button type="button" class="as-btn as-btn-secondary" id="vpn-delete-cancel">取消</button>
                <button type="button" class="as-btn as-btn-danger" id="vpn-delete-confirm">确认删除</button>
            </div>
        </div>
    </div>

@push('scripts')
<script>
function adminDashboard() {
    const api = (path, opts = {}) => {
        const token = localStorage.getItem('admin_token');
        const h = { 'Content-Type': 'application/json', 'Accept': 'application/json', ...(opts.headers || {}) };
        if (token) h['Authorization'] = 'Bearer ' + token;
        const url = path.startsWith('http') ? path : (path[0] === '/' ? path : '/api/v1/' + path);
        const { body: rawBody, ...rest } = opts;
        let reqBody = rawBody;
        if (reqBody != null && typeof reqBody === 'object'
            && !(reqBody instanceof FormData)
            && !(reqBody instanceof URLSearchParams)
            && !(typeof Blob !== 'undefined' && reqBody instanceof Blob)) {
            reqBody = JSON.stringify(reqBody);
        }
        return fetch(url, { ...rest, body: reqBody, headers: h })
            .then(async r => {
                const json = await r.json().catch(() => ({}));
                if (r.status === 401 || r.status === 403) {
                    localStorage.removeItem('admin_token');
                    localStorage.removeItem('admin_role');
                    localStorage.removeItem('admin_email');
                    window.location.href = '/admin/login?reason=' + (r.status === 403 ? 'forbidden' : 'unauthorized');
                    throw new Error(json.message || (r.status === 403 ? '需要管理员权限' : '请重新登录'));
                }
                if (!r.ok) {
                    let msg = json.message || json.error || r.statusText;
                    if (json.errors && typeof json.errors === 'object') {
                        const first = Object.values(json.errors).flat().find(Boolean);
                        if (first) {
                            msg = first;
                        }
                    }
                    throw new Error(msg || ('请求失败: ' + r.status));
                }
                if (r.status === 204) return null;
                if (json && typeof json === 'object'
                    && Object.prototype.hasOwnProperty.call(json, 'success')
                    && Object.prototype.hasOwnProperty.call(json, 'data')) {
                    return json.data;
                }
                return json;
            });
    };
    // 供模态脚本复用的全局 API 助手（仅前端，不改后端逻辑）
    window.__adminApi = api;
    return {
        tab: localStorage.getItem('admin_tab') || 'overview',
        tabTitles: { overview: '控制台', analytics: '数据分析', users: '管理员列表', vpn_accounts: '用户管理', purchased_products: '已购产品', products: '产品与定价', orders: '订单', servers: '接入服务器', server_form: '接入服务器配置', ip_pool: 'IP 池', snat_maps: 'SNAT 映射表', provision_audit: '资源审计', resellers: '分销商', payment_settings: '支付设置', runtime_settings: '安全与限流', security: '账户安全', help: '帮助与常见问题' },
        userEmail: '',
        userMenuOpen: false,
        sidebarGroupsOpen: { overview: true, business: true, infra: true, reseller: false, support: false },
        loading: false,
        summary: {},
        analytics: {},
        deployPollTimer: null,
        servers: [], users: [], orders: [], products: [], resellers: [], exitNodes: [], ipPool: [], snatMaps: [], snatMapsTotal: 0, snatMapsPage: 1, snatMapsPerPage: 50,
        snatFilter: { status: '', q: '', vpn_user_id: '', server_id: '' },
        provisionAuditLogs: [], provisionAuditTotal: 0, provisionAuditPage: 1, provisionAuditPerPage: 50,
        provisionAuditFilter: { event: '', vpn_user_id: '', order_id: '' },
        vpnAccounts: [], purchasedProducts: [],
        vpnFilter: { q: '', region: '', reseller_id: '' },
        ordersFilter: { q: '', region: '', reseller_id: '' },
        orderDetailId: null,
        editingServer: null,
        serverFormMode: 'create',
        formServer: { hostname: '', region: '', role: 'access', cost_cents: 0, host: '', ssh_port: 22, ssh_user: 'root', ssh_password: '', agent_enabled: true, nat_topology: 'combined', cn_public_iface: '', hk_public_iface: '', peer_link_iface: '', peer_link_local_ip: '', peer_link_remote_ip: '', link_tunnel_type: '', split_nat_host: '', split_nat_ssh_port: 22, split_nat_ssh_user: 'root', split_nat_ssh_password: '', split_nat_hk_public_iface: '', split_nat_multi_public_ip_enabled: false, protocol: 'wireguard', wg_private_key: '', wg_public_key: '', ocserv_radius_host: '', ocserv_radius_auth_port: 1812, ocserv_radius_acct_port: 1813, ocserv_radius_secret: '', ocserv_port: 443, ocserv_domain: '', ocserv_tls_cert_pem: '', ocserv_tls_key_pem: '', notes: '' },
        formOrder: { user_id: '', product_id: '' },
        formProduct: { name: '', price_yuan: '99', duration_days: '30' },
        formReseller: { name: '' },
        formIpPool: { ip_address: '', region: '' },
        passwordForm: {
            current_password: '',
            password: '',
            password_confirmation: '',
            loading: false,
        },
        paymentSettings: {
            loading: false,
            saving: false,
            epay_enabled: false,
            epay_gateway: '',
            epay_pid: '',
            epay_key: '',
            epay_key_set: false,
            epay_key_hint: '',
            epay_notify_url: '',
            epay_return_url: '',
            epay_notify_url_effective: '',
            epay_return_url_effective: '',
            epay_allow_simulated_recharge: true,
        },
        runtimeSettings: {
            loading: false,
            saving: false,
            redis_env_configured: false,
            redis_connection_ok: false,
            rate_limits: {
                auth_login: 30,
                auth_register: 10,
                reseller_validate: 120,
                reseller_portal_register: 10,
                reseller_portal_login: 30,
                epay_notify: 300,
            },
        },
        // 分销商余额流水（分页/搜索）
        txPanelResellerId: null,
        txItems: [],
        txTotal: 0,
        txPage: 1,
        txLimit: 20,
        txType: '',
        txSearch: '',
        txTotalPages: 1,
        txLoading: false,
        txTypeLabel(type) {
            // 后端枚举值 -> 中文显示
            const m = {
                recharge: '充值',
                provision_purchase: '开通（新购）',
                provision_renew: '续费',
                admin_adjust: '后台调整',
            };
            return m[type] || type || '—';
        },
        renderAnalyticsCharts() {
            // Chart.js 渲染：依赖 CDN 脚本（仅前端，不影响后端）
            try {
                if (!window.Chart) return;

                // 防止 x-show 切换时多次触发导致 destroy/create 竞态
                const jobId = (this._analyticsChartJobId = (this._analyticsChartJobId || 0) + 1);

                const stats = this.analytics?.stats || {};
                const sales = Number(stats.sales_total_cents ?? 0) / 100;
                const cost = Number(stats.platform_cost_cents ?? 0) / 100;
                const profit = Number(stats.profit_total_cents ?? 0) / 100;

                const profitPos = Math.max(profit, 0);
                const lossPos = Math.max(-profit, 0);

                const barCanvas = document.getElementById('salesCostProfitBarChart');
                if (barCanvas) {
                    // x-show 切换时如果尺寸为 0，会导致画布渲染失败/空白
                    const rect = barCanvas.getBoundingClientRect();
                    if (!rect.width || !rect.height) {
                        setTimeout(() => {
                            if (jobId === this._analyticsChartJobId) this.renderAnalyticsCharts();
                        }, 150);
                    } else {
                        if (this.chartInstances.salesCostProfitBarChart) {
                            try {
                                this.chartInstances.salesCostProfitBarChart.stop?.();
                            } catch (e) {}
                            try {
                                this.chartInstances.salesCostProfitBarChart.destroy();
                            } catch (e) {}
                        }
                        const profitColor = profit >= 0 ? 'rgba(16,185,129,0.8)' : 'rgba(239,68,68,0.8)';

                        // Chart.js v4 支持直接传 canvas 元素，避免 getContext 偶发为 null
                        const chart = new window.Chart(
                            barCanvas,
                            {
                                type: 'bar',
                                data: {
                                    labels: ['销售金额', '平台成本', '利润'],
                                    datasets: [
                                        {
                                            label: '金额(元)',
                                            data: [
                                                Number.isFinite(sales) ? sales : 0,
                                                Number.isFinite(cost) ? cost : 0,
                                                Number.isFinite(profit) ? profit : 0,
                                            ],
                                            backgroundColor: ['rgba(59,130,246,0.75)', 'rgba(245,158,11,0.75)', profitColor],
                                            borderColor: [
                                                'rgba(59,130,246,1)',
                                                'rgba(245,158,11,1)',
                                                profit >= 0 ? 'rgba(16,185,129,1)' : 'rgba(239,68,68,1)',
                                            ],
                                            borderWidth: 1,
                                        },
                                    ],
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: { display: false },
                                        tooltip: {
                                            callbacks: {
                                                label: (ctx) => {
                                                    const y = ctx?.parsed?.y;
                                                    const v = Number.isFinite(Number(y)) ? Number(y) : 0;
                                                    return ' ' + v.toFixed(2) + ' 元';
                                                },
                                            },
                                        },
                                    },
                                    scales: {
                                        y: {
                                            ticks: {
                                                callback: (value) => Number(value).toFixed(0),
                                            },
                                        },
                                    },
                                },
                            }
                        );

                        this.chartInstances.salesCostProfitBarChart = chart;
                        setTimeout(() => { try { chart.resize(); } catch (e) {} }, 120);
                    }
                }

                const donutCanvas = document.getElementById('profitSignDonutChart');
                if (donutCanvas) {
                    const rect = donutCanvas.getBoundingClientRect();
                    if (!rect.width || !rect.height) {
                        setTimeout(() => {
                            if (jobId === this._analyticsChartJobId) this.renderAnalyticsCharts();
                        }, 150);
                    } else {
                        if (this.chartInstances.profitSignDonutChart) {
                            try {
                                this.chartInstances.profitSignDonutChart.stop?.();
                            } catch (e) {}
                            try {
                                this.chartInstances.profitSignDonutChart.destroy();
                            } catch (e) {}
                        }

                        const total = profitPos + lossPos;
                        const isEmpty = total <= 0;
                        const profitValue = isEmpty ? 1 : profitPos;
                        const lossValue = isEmpty ? 0 : lossPos;
                        const labels = isEmpty ? ['暂无数据', ''] : ['盈利', '亏损'];

                        const chart = new window.Chart(
                            donutCanvas,
                            {
                                type: 'doughnut',
                                data: {
                                    labels,
                                    datasets: [
                                        {
                                            data: [profitValue, lossValue],
                                            backgroundColor: [
                                                isEmpty ? 'rgba(148,163,184,0.8)' : 'rgba(16,185,129,0.85)',
                                                'rgba(239,68,68,0.85)',
                                            ],
                                            borderWidth: 0,
                                        },
                                    ],
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: {
                                            position: 'bottom',
                                            labels: { boxWidth: 10 },
                                        },
                                        tooltip: {
                                            callbacks: {
                                                label: (ctx) => {
                                                    const raw = ctx?.raw;
                                                    const v = Number.isFinite(Number(raw)) ? Number(raw) : 0;
                                                    return ' ' + v.toFixed(2) + ' 元';
                                                },
                                            },
                                        },
                                    },
                                    cutout: '62%',
                                },
                            }
                        );

                        this.chartInstances.profitSignDonutChart = chart;
                        setTimeout(() => { try { chart.resize(); } catch (e) {} }, 120);
                    }
                }
            } catch (e) {
                console.error('renderAnalyticsCharts failed', e);
            }
        },
        async loadAnalytics() {
            this.loading = true;
            try {
                const data = await api('/api/v1/admin/analytics');
                this.analytics = data || {};
            } catch (e) {
                console.error('loadAnalytics failed', e);
                alert(e.message || '加载数据分析失败');
                this.analytics = {};
            } finally {
                this.loading = false;
            }
        },
        init() {
            const token = localStorage.getItem('admin_token');
            const role = localStorage.getItem('admin_role');
            if (!token) { window.location.href = '/admin/login'; return; }
            if (role !== 'admin') { localStorage.removeItem('admin_token'); localStorage.removeItem('admin_role'); window.location.href = '/admin/login?reason=forbidden'; return; }
            this.userEmail = localStorage.getItem('admin_email') || '';
            // 恢复上次停留的 tab，并按需加载数据
            let t = localStorage.getItem('admin_tab') || this.tab || 'overview';
            if (t === 'income') { t = 'orders'; localStorage.setItem('admin_tab', 'orders'); }
            this.tab = t;
            this.openSidebarGroupForTab(t);
            if (t === 'overview') {
                this.loadOverview();
            } else if (t === 'analytics') {
                this.loadAnalytics();
            } else if (t === 'users') {
                this.loadUsers();
            } else if (t === 'vpn_accounts') {
                this.loadResellers();
                this.loadVpnAccounts();
            } else if (t === 'purchased_products') {
                this.loadResellers();
                this.loadPurchasedProducts();
            } else if (t === 'products') {
                this.loadProducts();
            } else if (t === 'orders') {
                this.loadOrders();
            } else if (t === 'servers') {
                this.loadServers();
            } else if (t === 'ip_pool') {
                this.loadIPPool();
            } else if (t === 'snat_maps') {
                this.loadSnatMaps();
            } else if (t === 'provision_audit') {
                this.loadProvisionAuditLogs();
            } else if (t === 'resellers') {
                this.loadResellers();
            } else if (t === 'payment_settings') {
                this.loadPaymentSettings();
            } else if (t === 'runtime_settings') {
                this.loadRuntimeSettings();
            } else if (t === 'help' || t === 'security' || t === 'server_form') {
                /* 静态页，无需预加载 */
            } else {
                this.loadOverview();
            }
        },
        sidebarGroupForTab(t) {
            if (t === 'overview' || t === 'analytics') return 'overview';
            if (t === 'products' || t === 'orders') return 'business';
            if (t === 'servers' || t === 'server_form' || t === 'ip_pool' || t === 'snat_maps' || t === 'provision_audit') return 'infra';
            if (t === 'resellers' || t === 'vpn_accounts' || t === 'purchased_products') return 'reseller';
            return 'support';
        },
        openSidebarGroupForTab(t) {
            const g = this.sidebarGroupForTab(t);
            if (g && this.sidebarGroupsOpen && Object.prototype.hasOwnProperty.call(this.sidebarGroupsOpen, g)) {
                this.sidebarGroupsOpen[g] = true;
            }
        },
        toggleSidebarGroup(group) {
            if (!this.sidebarGroupsOpen || !Object.prototype.hasOwnProperty.call(this.sidebarGroupsOpen, group)) return;
            this.sidebarGroupsOpen[group] = !this.sidebarGroupsOpen[group];
        },
        setTab(t) {
            this.tab = t;
            localStorage.setItem('admin_tab', t);
            this.openSidebarGroupForTab(t);
        },
        openResellerBalanceTx(resellerId) {
            this.txPanelResellerId = parseInt(resellerId);
            this.txPage = 1;
            this.txItems = [];
            this.txTotal = 0;
            this.txTotalPages = 1;
            this.loadResellerBalanceTx(1);
        },
        closeResellerBalanceTx() {
            this.txPanelResellerId = null;
            this.txItems = [];
            this.txTotal = 0;
            this.txPage = 1;
            this.txTotalPages = 1;
            this.txLoading = false;
        },
        async loadResellerBalanceTx(page = 1) {
            if (!this.txPanelResellerId) return;
            this.txLoading = true;
            try {
                const params = new URLSearchParams();
                params.set('page', String(page));
                params.set('limit', String(this.txLimit));
                if (this.txType) params.set('type', this.txType);
                if (this.txSearch && this.txSearch.trim()) params.set('q', this.txSearch.trim());

                const data = await api(`/api/v1/admin/resellers/${this.txPanelResellerId}/balance/transactions?${params.toString()}`);
                this.txTotal = (data && data.total != null) ? parseInt(data.total) : 0;
                this.txPage = page;
                this.txLimit = (data && data.limit != null) ? parseInt(data.limit) : this.txLimit;
                this.txItems = (data && Array.isArray(data.items)) ? data.items : [];
                this.txTotalPages = Math.max(1, Math.ceil(this.txTotal / (this.txLimit || 1)));
            } catch (e) {
                console.error('load reseller balance tx failed', e);
                alert(e.message || '加载余额流水失败');
            } finally {
                this.txLoading = false;
            }
        },
        async logout() {
            this.userMenuOpen = false;
            const token = localStorage.getItem('admin_token');
            if (token) {
                try {
                    await api('/api/v1/auth/logout', { method: 'POST' });
                } catch (e) {
                    /* 仍清理本地并跳转 */
                }
            }
            localStorage.removeItem('admin_token');
            localStorage.removeItem('admin_role');
            localStorage.removeItem('admin_email');
            window.location.href = '/admin/login';
        },
        applyPaymentSettingsPayload(data) {
            if (!data) return;
            this.paymentSettings.epay_enabled = !!data.epay_enabled;
            this.paymentSettings.epay_gateway = data.epay_gateway || '';
            this.paymentSettings.epay_pid = data.epay_pid || '';
            this.paymentSettings.epay_key = '';
            this.paymentSettings.epay_key_set = !!data.epay_key_set;
            this.paymentSettings.epay_key_hint = data.epay_key_hint || '';
            this.paymentSettings.epay_notify_url = data.epay_notify_url || '';
            this.paymentSettings.epay_return_url = data.epay_return_url || '';
            this.paymentSettings.epay_notify_url_effective = data.epay_notify_url_effective || '';
            this.paymentSettings.epay_return_url_effective = data.epay_return_url_effective || '';
            this.paymentSettings.epay_allow_simulated_recharge = !!data.epay_allow_simulated_recharge;
        },
        async loadPaymentSettings() {
            this.paymentSettings.loading = true;
            try {
                const data = await api('/api/v1/admin/settings/payment');
                this.applyPaymentSettingsPayload(data);
            } catch (e) {
                alert(e.message || '加载支付设置失败');
            } finally {
                this.paymentSettings.loading = false;
            }
        },
        async savePaymentSettings() {
            this.paymentSettings.saving = true;
            try {
                const payload = {
                    epay_enabled: !!this.paymentSettings.epay_enabled,
                    epay_gateway: this.paymentSettings.epay_gateway || '',
                    epay_pid: this.paymentSettings.epay_pid || '',
                    epay_notify_url: this.paymentSettings.epay_notify_url || '',
                    epay_return_url: this.paymentSettings.epay_return_url || '',
                    epay_allow_simulated_recharge: !!this.paymentSettings.epay_allow_simulated_recharge,
                };
                if (this.paymentSettings.epay_key && String(this.paymentSettings.epay_key).trim()) {
                    payload.epay_key = String(this.paymentSettings.epay_key).trim();
                }
                const data = await api('/api/v1/admin/settings/payment', {
                    method: 'PUT',
                    body: JSON.stringify(payload),
                });
                this.applyPaymentSettingsPayload(data);
                try {
                    window.Alpine.store('toast')?.show('已保存', '支付设置已更新', 'success');
                } catch (e) {
                    alert('支付设置已保存');
                }
            } catch (e) {
                try {
                    window.Alpine.store('toast')?.show('保存失败', e.message || '请求失败', 'error');
                } catch (err) {
                    alert(e.message || '保存失败');
                }
            } finally {
                this.paymentSettings.saving = false;
            }
        },
        applyRuntimeSettingsPayload(data) {
            if (!data) return;
            this.runtimeSettings.redis_env_configured = !!data.redis_env_configured;
            this.runtimeSettings.redis_connection_ok = !!data.redis_connection_ok;
            const rl = data.rate_limits || {};
            const keys = ['auth_login', 'auth_register', 'reseller_validate', 'reseller_portal_register', 'reseller_portal_login', 'epay_notify'];
            keys.forEach((k) => {
                if (rl[k] != null && rl[k] !== '') {
                    this.runtimeSettings.rate_limits[k] = parseInt(rl[k], 10) || this.runtimeSettings.rate_limits[k];
                }
            });
        },
        async loadRuntimeSettings() {
            this.runtimeSettings.loading = true;
            try {
                const data = await api('/api/v1/admin/settings/runtime');
                this.applyRuntimeSettingsPayload(data);
            } catch (e) {
                alert(e.message || '加载安全设置失败');
            } finally {
                this.runtimeSettings.loading = false;
            }
        },
        async saveRuntimeSettings() {
            this.runtimeSettings.saving = true;
            try {
                const payload = {
                    rate_limits: { ...this.runtimeSettings.rate_limits },
                };
                const data = await api('/api/v1/admin/settings/runtime', {
                    method: 'PUT',
                    body: JSON.stringify(payload),
                });
                this.applyRuntimeSettingsPayload(data);
                try {
                    window.Alpine.store('toast')?.show('已保存', '安全与限流设置已更新', 'success');
                } catch (e) {
                    alert('设置已保存');
                }
            } catch (e) {
                try {
                    window.Alpine.store('toast')?.show('保存失败', e.message || '请求失败', 'error');
                } catch (err) {
                    alert(e.message || '保存失败');
                }
            } finally {
                this.runtimeSettings.saving = false;
            }
        },
        async changePassword() {
            const f = this.passwordForm;
            if (!f.current_password || !f.password || !f.password_confirmation) {
                try {
                    window.Alpine.store('toast')?.show('请填写完整', '请填写当前密码与新密码', 'error');
                } catch (e) {
                    alert('请填写当前密码与新密码');
                }
                return;
            }
            if (f.password !== f.password_confirmation) {
                try {
                    window.Alpine.store('toast')?.show('校验失败', '两次输入的新密码不一致', 'error');
                } catch (e) {
                    alert('两次输入的新密码不一致');
                }
                return;
            }
            f.loading = true;
            try {
                await api('/api/v1/auth/password', {
                    method: 'PATCH',
                    body: JSON.stringify({
                        current_password: f.current_password,
                        password: f.password,
                        password_confirmation: f.password_confirmation,
                    }),
                });
                f.current_password = '';
                f.password = '';
                f.password_confirmation = '';
                try {
                    window.Alpine.store('toast')?.show('已更新', '密码已修改，其他设备的管理后台登录已失效。', 'success');
                } catch (e) {
                    alert('密码已更新');
                }
            } catch (e) {
                try {
                    window.Alpine.store('toast')?.show('修改失败', e.message || '请求失败', 'error');
                } catch (err) {
                    alert(e.message || '修改失败');
                }
            } finally {
                f.loading = false;
            }
        },
        async loadOverview() {
            this.loading = true;
            try {
                const [s, sum, res, ord, an] = await Promise.all([
                    api('/api/v1/admin/servers'),
                    api('/api/v1/admin/summary').catch(() => ({})),
                    api('/api/v1/resellers').catch(() => []),
                    api('/api/v1/admin/orders').catch(() => []),
                    api('/api/v1/admin/analytics').catch(() => ({})),
                ]);
                this.servers = s || [];
                this.scheduleDeployPoll();
                this.summary = sum || {};
                this.analytics = an || {};
                this.resellers = res || [];
                this.orders = ord || [];
            } catch (e) { this.servers = []; }
            this.loading = false;
        },
        loadServers() {
            return api('/api/v1/admin/servers').then(d => {
                this.servers = d || [];
                this.scheduleDeployPoll();
            }).catch(() => {});
        },
        loadUsers() { api('/api/v1/admin/users').then(d => { this.users = d || []; }).catch(() => {}); },
        loadVpnAccounts() { api('/api/v1/admin/vpn_users').then(d => { this.vpnAccounts = d || []; }).catch(() => {}); },
        loadPurchasedProducts() { api('/api/v1/admin/purchased_products').then(d => { this.purchasedProducts = d || []; }).catch(() => {}); },
        /** 已购产品「详情」：与 B 站 openDetail 一致，显式调用全局模态（不依赖表格委托） */
        openVpnDetailModal(vpnUserId) {
            if (!vpnUserId) return;
            if (typeof window.__openAdminVpnUserDetail === 'function') {
                void window.__openAdminVpnUserDetail(vpnUserId);
            }
        },
        loadOrders() { api('/api/v1/admin/orders').then(d => { this.orders = d || []; }).catch(() => {}); },
        loadProducts() { api('/api/v1/products').then(d => { this.products = d || []; }).catch(() => {}); },
        loadResellers() { api('/api/v1/resellers').then(d => { this.resellers = d || []; }).catch(() => {}); },
        loadIPPool() { api('/api/v1/ip_pool').then(d => { this.ipPool = d || []; }).catch(() => {}); },
        formatAuditMeta(meta) {
            if (meta == null || meta === '') return '—';
            if (typeof meta === 'object') {
                try { return JSON.stringify(meta, null, 2); } catch (e) { return String(meta); }
            }
            if (typeof meta === 'string') {
                try {
                    const o = JSON.parse(meta);
                    return JSON.stringify(o, null, 2);
                } catch (e) {
                    return meta;
                }
            }
            return String(meta);
        },
        loadSnatMaps() {
            const p = new URLSearchParams();
            p.set('page', String(this.snatMapsPage || 1));
            p.set('per_page', String(this.snatMapsPerPage || 50));
            const f = this.snatFilter || {};
            if (f.status) p.set('status', f.status);
            if ((f.q || '').trim()) p.set('q', (f.q || '').trim());
            if (f.vpn_user_id) p.set('vpn_user_id', String(parseInt(f.vpn_user_id, 10) || ''));
            if (f.server_id) p.set('server_id', String(parseInt(f.server_id, 10) || ''));
            api('/api/v1/admin/snat_maps?' + p.toString()).then(d => {
                this.snatMaps = (d && d.data) ? d.data : [];
                this.snatMapsTotal = (d && typeof d.total === 'number') ? d.total : (this.snatMaps.length || 0);
            }).catch(() => {});
        },
        loadProvisionAuditLogs() {
            const p = new URLSearchParams();
            p.set('page', String(this.provisionAuditPage || 1));
            p.set('per_page', String(this.provisionAuditPerPage || 50));
            const f = this.provisionAuditFilter || {};
            if (f.event) p.set('event', f.event);
            if (f.vpn_user_id) p.set('vpn_user_id', String(parseInt(f.vpn_user_id, 10) || ''));
            if (f.order_id) p.set('order_id', String(parseInt(f.order_id, 10) || ''));
            api('/api/v1/admin/provision_audit_logs?' + p.toString()).then(d => {
                this.provisionAuditLogs = (d && d.data) ? d.data : [];
                this.provisionAuditTotal = (d && typeof d.total === 'number') ? d.total : (this.provisionAuditLogs.length || 0);
            }).catch(() => {});
        },
        vpnRegions() {
            const set = new Set();
            (this.vpnAccounts || []).forEach(v => {
                const r = (v && v.region) ? String(v.region).trim() : '';
                if (r) set.add(r);
            });
            return Array.from(set).sort();
        },
        purchasedRegions() {
            const set = new Set();
            (this.purchasedProducts || []).forEach(o => {
                const r = (o && o.vpn_user && o.vpn_user.region) ? String(o.vpn_user.region).trim() : '';
                if (r) set.add(r);
            });
            return Array.from(set).sort();
        },
        orderRegions() {
            const set = new Set();
            (this.orders || []).forEach(o => {
                const r = (o && o.vpn_user && o.vpn_user.region) ? String(o.vpn_user.region).trim() : '';
                if (r) set.add(r);
            });
            return Array.from(set).sort();
        },
        filteredOrders() {
            const q = (this.ordersFilter.q || '').trim().toLowerCase();
            const region = (this.ordersFilter.region || '').trim();
            const resellerId = (this.ordersFilter.reseller_id || '').trim();
            return (this.orders || []).filter(o => {
                if (!o) return false;
                if (resellerId) {
                    const rid = String((o.reseller && o.reseller.id) ? o.reseller.id : (o.reseller_id || ''));
                    if (rid !== resellerId) return false;
                }
                if (region) {
                    const rg = String((o.vpn_user && o.vpn_user.region) ? o.vpn_user.region : '');
                    if (rg !== region) return false;
                }
                if (!q) return true;
                const incHay = (o.income_records || []).map(r => r.biz_order_no || '').join(' ');
                const hay = [
                    (o.vpn_user && o.vpn_user.email) ? o.vpn_user.email : '',
                    (o.user && o.user.email) ? o.user.email : '',
                    (o.product && o.product.name) ? o.product.name : '',
                    (o.reseller && o.reseller.name) ? o.reseller.name : '',
                    o.status || '',
                    String(o.id || ''),
                    o.biz_order_no ? String(o.biz_order_no) : '',
                    incHay,
                ].join(' ').toLowerCase();
                return hay.includes(q);
            });
        },
        shortBizOrderNo(s) {
            s = s ? String(s) : '';
            if (!s) return '—';
            if (s.length <= 16) return s;
            return s.slice(0, 10) + '…' + s.slice(-6);
        },
        selectedOrderRow() {
            const id = this.orderDetailId;
            if (!id) return null;
            return (this.orders || []).find(o => o && o.id === id) || null;
        },
        serverHealthText(s) {
            if (!s) return '未知';
            const p = (s.protocol || '').trim();
            const topo = (s.nat_topology || 'combined').trim();
            if (!s.host || !s.ssh_user) return '待补 SSH';
            if (p !== 'wireguard' && p !== 'ocserv') return '协议未设';
            if (p === 'wireguard' && !s.wg_public_key) return 'WG 公钥缺失';
            if (p === 'ocserv' && (!s.ocserv_radius_host || !s.ocserv_domain || !s.ocserv_tls_cert_pem || !s.ocserv_tls_key_pem)) return 'OCServ 配置缺失';
            if (topo === 'combined') {
                if (!s.cn_public_iface || !s.hk_public_iface) return '一体网卡缺失';
                return '正常';
            }
            if (!s.peer_link_iface || !s.split_nat_host || !s.split_nat_ssh_user) return '分体配置缺失';
            return '正常';
        },
        serverHealthClass(s) {
            const t = this.serverHealthText(s);
            if (t === '正常') return 'inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700';
            if (t.includes('缺失') || t.includes('未设')) return 'inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-700';
            return 'inline-flex items-center rounded-full bg-zinc-100 px-2 py-0.5 text-[11px] font-medium text-zinc-600';
        },
        agentDeployTitle(s) {
            if (!s) return '';
            const d = String(s.agent_deploy_status || '').trim();
            const msg = String(s.agent_deploy_message || '').trim();
            const labels = { queued: '排队', running: '执行中', success: '已下发', failed: '失败' };
            const lb = labels[d] || d;
            if (!d && !msg) return '';
            return msg ? (lb + ' · ' + msg) : lb;
        },
        agentDeploySummary(s) {
            if (!s) return '—';
            const d = String(s.agent_deploy_status || '').trim();
            if (!d) return '—';
            const msg = String(s.agent_deploy_message || '').trim();
            const labels = { queued: '排队', running: '执行中', success: '已下发', failed: '失败' };
            const lb = labels[d] || d;
            const max = d === 'failed' ? 800 : 120;
            const short = msg.length > max ? msg.slice(0, max - 1) + '…' : msg;
            return short ? (lb + ' · ' + short) : lb;
        },
        agentDeployClass(s) {
            const d = String(s && s.agent_deploy_status || '').trim();
            if (d === 'failed') return 'text-red-700 font-medium';
            if (d === 'running' || d === 'queued') return 'text-indigo-700 font-medium';
            if (d === 'success') return 'text-emerald-800';
            return 'text-zinc-600';
        },
        agentRuntimeLabel(s) {
            if (!s) return '—';
            const hb = s.last_heartbeat_at || s.last_seen_at;
            if (!hb) return '未连接';
            const sec = (Date.now() - new Date(hb).getTime()) / 1000;
            if (sec < 0) return '在线';
            if (sec <= 60) return '在线（约 ' + Math.max(1, Math.round(sec)) + 's 前）';
            if (sec <= 180) return '弱（约 ' + Math.round(sec) + 's 前心跳）';
            return '离线（约 ' + Math.round(sec / 60) + ' 分钟前心跳）';
        },
        agentRuntimeClass(s) {
            if (!s) return '';
            const hb = s.last_heartbeat_at || s.last_seen_at;
            if (!hb) return 'text-zinc-500';
            const sec = (Date.now() - new Date(hb).getTime()) / 1000;
            if (sec <= 60) return 'text-emerald-700 font-medium';
            if (sec <= 180) return 'text-amber-700';
            return 'text-red-700';
        },
        formatServerHeartbeat(s) {
            if (!s) return '—';
            const hb = s.last_heartbeat_at || s.last_seen_at;
            return hb ? new Date(hb).toLocaleString() : '—';
        },
        formatUnixConfigTs(ts) {
            const n = Number(ts);
            if (!n || n <= 0) return '—';
            return new Date(n * 1000).toLocaleString() + ' · #' + n;
        },
        configSyncUi(s) {
            if (!s) {
                return { badge: '—', badgeClass: 'text-zinc-500', aLine: '—', bLine: '—' };
            }
            const a = Number(s.config_revision_ts || 0);
            const br = s.agent_reported_config_ts;
            const b = br != null && br !== '' ? Number(br) : null;
            let badge = '—';
            let badgeClass = 'text-zinc-500';
            if (a > 0 && b == null) {
                badge = '节点未上报';
                badgeClass = 'text-amber-700 font-medium';
            } else if (a > 0 && b != null && a === b) {
                badge = '已同步';
                badgeClass = 'text-emerald-700 font-medium';
            } else if (a > 0 && b != null && a !== b) {
                badge = '待下发';
                badgeClass = 'text-red-700 font-medium';
            } else if (a <= 0) {
                badge = '无修订号';
                badgeClass = 'text-zinc-400';
            }
            return {
                badge,
                badgeClass,
                aLine: this.formatUnixConfigTs(a),
                bLine: b != null ? this.formatUnixConfigTs(b) : '未上报',
            };
        },
        scheduleDeployPoll() {
            const pending = (this.servers || []).some(x => {
                const st = x && String(x.agent_deploy_status || '').trim();
                return st === 'queued' || st === 'running';
            });
            if (!pending) {
                if (this.deployPollTimer) {
                    clearInterval(this.deployPollTimer);
                    this.deployPollTimer = null;
                }
                return;
            }
            if (this.deployPollTimer) return;
            this.deployPollTimer = setInterval(() => {
                this.loadServers();
            }, 2500);
        },
        filteredVpnAccounts() {
            const q = (this.vpnFilter.q || '').trim().toLowerCase();
            const region = (this.vpnFilter.region || '').trim();
            const resellerId = (this.vpnFilter.reseller_id || '').trim();
            return (this.vpnAccounts || []).filter(v => {
                if (!v) return false;
                if (region && String(v.region || '') !== region) return false;
                if (resellerId && String(v.reseller_id || '') !== resellerId) return false;
                if (!q) return true;
                const hay = [
                    v.user_email || '',
                    v.user_name || '',
                    v.vpn_name || '',
                    v.radius_username || '',
                ].join(' ').toLowerCase();
                return hay.includes(q);
            });
        },
        filteredPurchasedProducts() {
            const q = (this.vpnFilter.q || '').trim().toLowerCase();
            const region = (this.vpnFilter.region || '').trim();
            const resellerId = (this.vpnFilter.reseller_id || '').trim();
            return (this.purchasedProducts || []).filter(o => {
                if (!o) return false;
                if (region) {
                    const rg = String((o.vpn_user && o.vpn_user.region) ? o.vpn_user.region : '');
                    if (rg !== region) return false;
                }
                if (resellerId) {
                    const rid = String((o.reseller && o.reseller.id) ? o.reseller.id : (o.reseller_id || ''));
                    if (rid !== resellerId) return false;
                }
                if (!q) return true;
                const hay = [
                    (o.vpn_user && o.vpn_user.email) ? o.vpn_user.email : '',
                    (o.vpn_user && o.vpn_user.name) ? o.vpn_user.name : '',
                    (o.product && o.product.name) ? o.product.name : '',
                    (o.reseller && o.reseller.name) ? o.reseller.name : '',
                    o.status || '',
                    String(o.id || ''),
                    o.biz_order_no ? String(o.biz_order_no) : '',
                ].join(' ').toLowerCase();
                return hay.includes(q);
            });
        },
        async updateUserRole(id, role) {
            try { await api('/api/v1/admin/users/' + id + '/role', { method: 'PATCH', body: JSON.stringify({ role }) }); this.loadUsers(); } catch (e) { alert(e.message); }
        },
        async addAdminUser() {
            const email = prompt('请输入新管理员邮箱：');
            if (email == null) return;
            const password = prompt('请输入新管理员密码（至少 6 位）：');
            if (password == null) return;
            const name = prompt('管理员昵称（可选，留空将使用邮箱）：', email) || '';
            const role = 'admin';
            if (!email || !password) {
                alert('邮箱和密码不能为空');
                return;
            }
            try {
                await api('/api/v1/admin/users', {
                    method: 'POST',
                    body: JSON.stringify({ email: email.trim(), password, name: name.trim(), role })
                });
                this.loadUsers();
            } catch (e) {
                alert(e.message || '创建管理员失败');
            }
        },
        async resetUserPassword(id) {
            if (!id) return;
            const p = prompt('请输入新密码（至少 6 位）：');
            if (p == null) return;
            if (String(p).trim().length < 6) {
                alert('密码至少 6 位');
                return;
            }
            try {
                await api('/api/v1/admin/users/' + id + '/password', {
                    method: 'PUT',
                    body: JSON.stringify({ password: p })
                });
                alert('密码已更新');
                this.loadUsers();
            } catch (e) {
                alert(e.message || '更新密码失败');
            }
        },
        async deleteUser(id) {
            if (!id) return;
            if (!confirm('确定删除该用户？（如果有关联 VPN/订单数据将禁止删除）')) return;
            try {
                await api('/api/v1/admin/users/' + id, { method: 'DELETE' });
                this.loadUsers();
            } catch (e) {
                alert(e.message || '删除失败');
            }
        },
        async createServer() {
            const d = this.formServer; if (!d.hostname || !d.region || !d.role) { alert('请填写主机名、区域、角色'); return; }
            const payload = { hostname: d.hostname, region: d.region, role: d.role || 'access', cost_cents: d.cost_cents != null ? parseInt(d.cost_cents, 10) : 0, host: d.host || null, ssh_port: d.ssh_port ? parseInt(d.ssh_port, 10) : 22, ssh_user: d.ssh_user || 'root', agent_enabled: !!d.agent_enabled, nat_topology: d.nat_topology || 'combined', cn_public_iface: d.cn_public_iface || null, hk_public_iface: d.hk_public_iface || null, peer_link_iface: d.peer_link_iface || null, peer_link_local_ip: d.peer_link_local_ip || null, peer_link_remote_ip: d.peer_link_remote_ip || null, link_tunnel_type: d.link_tunnel_type || null, split_nat_host: d.split_nat_host || null, split_nat_ssh_port: d.split_nat_ssh_port ? parseInt(d.split_nat_ssh_port, 10) : 22, split_nat_ssh_user: d.split_nat_ssh_user || null, split_nat_hk_public_iface: d.split_nat_hk_public_iface || null, protocol: d.protocol || null, vpn_ip_cidrs: d.vpn_ip_cidrs || null, wg_private_key: d.wg_private_key || null, wg_public_key: d.wg_public_key || null, wg_port: d.wg_port ? parseInt(d.wg_port, 10) : null, wg_dns: d.wg_dns || null, ocserv_radius_host: d.ocserv_radius_host || null, ocserv_radius_auth_port: d.ocserv_radius_auth_port ? parseInt(d.ocserv_radius_auth_port, 10) : null, ocserv_radius_acct_port: d.ocserv_radius_acct_port ? parseInt(d.ocserv_radius_acct_port, 10) : null, ocserv_radius_secret: d.ocserv_radius_secret || null, notes: d.notes || null };
            if (d.ssh_password) payload.ssh_password = d.ssh_password;
            if (d.split_nat_ssh_password) payload.split_nat_ssh_password = d.split_nat_ssh_password;
            try { await api('/api/v1/servers', { method: 'POST', body: JSON.stringify(payload) }); this.formServer = { hostname: '', region: '', role: 'access', cost_cents: 0, host: '', ssh_port: 22, ssh_user: 'root', ssh_password: '', agent_enabled: true, nat_topology: 'combined', cn_public_iface: '', hk_public_iface: '', peer_link_iface: '', peer_link_local_ip: '', peer_link_remote_ip: '', link_tunnel_type: '', split_nat_host: '', split_nat_ssh_port: 22, split_nat_ssh_user: 'root', split_nat_ssh_password: '', split_nat_hk_public_iface: '', protocol: 'wireguard', wg_private_key: '', wg_public_key: '', ocserv_radius_host: '', ocserv_radius_auth_port: 1812, ocserv_radius_acct_port: 1813, ocserv_radius_secret: '', ocserv_port: 443, ocserv_domain: '', ocserv_tls_cert_pem: '', ocserv_tls_key_pem: '', notes: '' }; this.loadServers(); this.loadOverview(); } catch (e) { alert(e.message); }
        },
        resetServerForm() {
            this.formServer = { hostname: '', region: '', cost_cents: 0, host: '', ssh_port: 22, ssh_user: 'root', ssh_password: '', agent_enabled: true, nat_topology: 'combined', cn_public_iface: '', hk_public_iface: '', peer_link_iface: '', peer_link_local_ip: '', peer_link_remote_ip: '', link_tunnel_type: '', peer_link_wg_private_key: '', peer_link_wg_peer_public_key: '', peer_link_wg_endpoint: '', peer_link_wg_allowed_ips: '', split_nat_host: '', split_nat_ssh_port: 22, split_nat_ssh_user: 'root', split_nat_ssh_password: '', split_nat_hk_public_iface: '', split_nat_multi_public_ip_enabled: false, protocol: 'wireguard', vpn_ip_cidrs: '', wg_private_key: '', wg_public_key: '', wg_port: 51820, wg_dns: '1.1.1.1', ocserv_radius_host: '', ocserv_radius_auth_port: 1812, ocserv_radius_acct_port: 1813, ocserv_radius_secret: '', ocserv_port: 443, ocserv_domain: '', ocserv_tls_cert_pem: '', ocserv_tls_key_pem: '', notes: '' };
        },
        openServerCreatePage() {
            this.serverFormMode = 'create';
            this.resetServerForm();
            this.setTab('server_form');
        },
        openServerEditPage(s) {
            this.serverFormMode = 'edit';
            this.formServer = {
                id: s.id,
                hostname: s.hostname || '',
                region: s.region || '',
                cost_cents: s.cost_cents != null ? s.cost_cents : 0,
                host: s.host || '',
                ssh_port: s.ssh_port || 22,
                ssh_user: s.ssh_user || 'root',
                ssh_password: '',
                agent_enabled: !!s.agent_enabled,
                nat_topology: s.nat_topology || 'combined',
                cn_public_iface: s.cn_public_iface || '',
                hk_public_iface: s.hk_public_iface || '',
                peer_link_iface: s.peer_link_iface || '',
                peer_link_local_ip: s.peer_link_local_ip || '',
                peer_link_remote_ip: s.peer_link_remote_ip || '',
                link_tunnel_type: s.link_tunnel_type || '',
                peer_link_wg_private_key: '',
                peer_link_wg_peer_public_key: s.peer_link_wg_peer_public_key || '',
                peer_link_wg_endpoint: s.peer_link_wg_endpoint || '',
                peer_link_wg_allowed_ips: s.peer_link_wg_allowed_ips || '',
                split_nat_host: s.split_nat_host || '',
                split_nat_ssh_port: s.split_nat_ssh_port || 22,
                split_nat_ssh_user: s.split_nat_ssh_user || 'root',
                split_nat_ssh_password: '',
                split_nat_hk_public_iface: s.split_nat_hk_public_iface || '',
                split_nat_multi_public_ip_enabled: !!s.split_nat_multi_public_ip_enabled,
                protocol: s.protocol || 'wireguard',
                vpn_ip_cidrs: s.vpn_ip_cidrs || '',
                wg_private_key: '',
                wg_public_key: s.wg_public_key || '',
                wg_port: s.wg_port || 51820,
                wg_dns: s.wg_dns || '1.1.1.1',
                ocserv_radius_host: s.ocserv_radius_host || '',
                ocserv_radius_auth_port: s.ocserv_radius_auth_port || 1812,
                ocserv_radius_acct_port: s.ocserv_radius_acct_port || 1813,
                ocserv_radius_secret: '',
                ocserv_port: s.ocserv_port || 443,
                ocserv_domain: s.ocserv_domain || '',
                ocserv_tls_cert_pem: s.ocserv_tls_cert_pem || '',
                ocserv_tls_key_pem: s.ocserv_tls_key_pem || '',
                notes: s.notes || '',
            };
            this.setTab('server_form');
        },
        async submitServerFormPage() {
            const d = this.formServer || {};
            const payload = {
                hostname: (d.hostname || '').trim(),
                region: (d.region || '').trim(),
                role: 'access',
                cost_cents: d.cost_cents !== '' && d.cost_cents != null ? parseInt(d.cost_cents, 10) : 0,
                protocol: (d.protocol || '').trim() || null,
                vpn_ip_cidrs: (d.vpn_ip_cidrs || '').trim() || null,
                wg_private_key: (d.wg_private_key || '').trim() || null,
                wg_public_key: (d.wg_public_key || '').trim() || null,
                wg_port: d.wg_port ? parseInt(d.wg_port, 10) : null,
                wg_dns: (d.wg_dns || '').trim() || null,
                ocserv_radius_host: (d.ocserv_radius_host || '').trim() || null,
                ocserv_radius_auth_port: d.ocserv_radius_auth_port ? parseInt(d.ocserv_radius_auth_port, 10) : null,
                ocserv_radius_acct_port: d.ocserv_radius_acct_port ? parseInt(d.ocserv_radius_acct_port, 10) : null,
                ocserv_radius_secret: (d.ocserv_radius_secret || '').trim() || null,
                ocserv_port: d.ocserv_port ? parseInt(d.ocserv_port, 10) : null,
                ocserv_domain: (d.ocserv_domain || '').trim() || null,
                ocserv_tls_cert_pem: (d.ocserv_tls_cert_pem || '').trim() || null,
                ocserv_tls_key_pem: (d.ocserv_tls_key_pem || '').trim() || null,
                host: (d.host || '').trim() || null,
                ssh_port: d.ssh_port ? parseInt(d.ssh_port, 10) : 22,
                ssh_user: (d.ssh_user || 'root').trim() || 'root',
                agent_enabled: !!d.agent_enabled,
                nat_topology: (d.nat_topology || 'combined').trim() || 'combined',
                cn_public_iface: (d.cn_public_iface || '').trim() || null,
                hk_public_iface: (d.hk_public_iface || '').trim() || null,
                peer_link_iface: (d.peer_link_iface || '').trim() || null,
                peer_link_local_ip: (d.peer_link_local_ip || '').trim() || null,
                peer_link_remote_ip: (d.peer_link_remote_ip || '').trim() || null,
                link_tunnel_type: (d.link_tunnel_type || '').trim() || null,
                peer_link_wg_private_key: (d.peer_link_wg_private_key || '').trim() || null,
                peer_link_wg_peer_public_key: (d.peer_link_wg_peer_public_key || '').trim() || null,
                peer_link_wg_endpoint: (d.peer_link_wg_endpoint || '').trim() || null,
                peer_link_wg_allowed_ips: (d.peer_link_wg_allowed_ips || '').trim() || null,
                split_nat_host: (d.split_nat_host || '').trim() || null,
                split_nat_ssh_port: d.split_nat_ssh_port ? parseInt(d.split_nat_ssh_port, 10) : 22,
                split_nat_ssh_user: (d.split_nat_ssh_user || '').trim() || null,
                split_nat_hk_public_iface: (d.split_nat_hk_public_iface || '').trim() || null,
                split_nat_multi_public_ip_enabled: !!d.split_nat_multi_public_ip_enabled,
                notes: (d.notes || '').trim() || null
            };
            if (!payload.hostname || !payload.region) { alert('请填写主机名和区域'); return; }
            if (d.ssh_password && String(d.ssh_password).trim()) payload.ssh_password = String(d.ssh_password).trim();
            if (d.split_nat_ssh_password && String(d.split_nat_ssh_password).trim()) payload.split_nat_ssh_password = String(d.split_nat_ssh_password).trim();
            if (payload.nat_topology === 'combined') {
                if (!payload.cn_public_iface || !payload.hk_public_iface) { alert('一体模式需填写 CN/HK 公网网卡'); return; }
            } else if (!payload.cn_public_iface || !payload.peer_link_iface || !payload.split_nat_host || !payload.split_nat_ssh_user || !payload.split_nat_ssh_password) {
                alert('分体模式需填写 CN网卡、互联网卡、NAT主机、NAT SSH 用户和密码');
                return;
            }
            if (payload.nat_topology !== 'combined' && payload.link_tunnel_type === 'wireguard' && (!payload.peer_link_wg_private_key || !payload.peer_link_wg_peer_public_key || !payload.peer_link_wg_endpoint || !payload.peer_link_local_ip)) {
                alert('分体 + WireGuard 互联需填写：互联网卡WG私钥、对端公钥、对端地址、本机互联IP');
                return;
            }
            if (payload.protocol === 'wireguard' && !payload.vpn_ip_cidrs) { alert('WireGuard 需填写 VPN 内网 CIDR'); return; }
            if (payload.protocol === 'ocserv' && (!payload.ocserv_radius_host || !payload.ocserv_radius_secret || !payload.ocserv_port || !payload.ocserv_domain || !payload.ocserv_tls_cert_pem || !payload.ocserv_tls_key_pem)) {
                alert('OCServ 需填写 RADIUS、端口、域名、证书与私钥');
                return;
            }
            try {
                if (this.serverFormMode === 'edit' && d.id) {
                    await api('/api/v1/servers/' + d.id, { method: 'PUT', body: JSON.stringify(payload) });
                } else {
                    await api('/api/v1/servers', { method: 'POST', body: JSON.stringify(payload) });
                }
                this.setTab('servers');
                await this.loadServers();
                await this.loadOverview();
            } catch (e) {
                alert(e.message || '保存失败');
            }
        },
        async installServerAgent(id) {
            if (!id) return;
            if (!confirm('将通过 SSH 自动部署并启动该节点的 agent，是否继续？')) return;
            try {
                await api('/api/v1/admin/servers/' + id + '/agent/install', { method: 'POST' });
                await this.loadServers();
                this.scheduleDeployPoll();
                try {
                    window.Alpine.store('toast')?.show('已排队', '部署任务已进入 maintenance 队列；列表将自动刷新进度（请确保队列 Worker 已运行）。', 'success');
                } catch (e) {
                    alert('已提交部署任务；请确保 php artisan queue:work --queue=maintenance 在运行。列表将自动刷新进度。');
                }
            } catch (e) {
                alert(e.message || '部署任务提交失败');
            }
        },
        async deleteServerAndReload(id) {
            if (!id) return;
            if (!confirm('确定删除该服务器？')) return;
            try {
                await api('/api/v1/servers/' + id, { method: 'DELETE' });
                await this.loadServers();
                await this.loadOverview();
            } catch (e) {
                alert(e.message || '删除失败');
            }
        },
        startEditServer(s) { this.editingServer = { ...s, ssh_password_plain: '' }; },
        async saveServerEdit() {
            if (!this.editingServer || !this.editingServer.id) return;
            const d = this.editingServer;
            const payload = { hostname: d.hostname, region: d.region, role: d.role, cost_cents: d.cost_cents != null ? parseInt(d.cost_cents, 10) : 0, host: d.host || null, ssh_port: d.ssh_port ? parseInt(d.ssh_port, 10) : 22, ssh_user: d.ssh_user || 'root', agent_enabled: !!d.agent_enabled, nat_topology: d.nat_topology || 'combined', cn_public_iface: d.cn_public_iface || null, hk_public_iface: d.hk_public_iface || null, peer_link_iface: d.peer_link_iface || null, peer_link_local_ip: d.peer_link_local_ip || null, peer_link_remote_ip: d.peer_link_remote_ip || null, link_tunnel_type: d.link_tunnel_type || null, split_nat_host: d.split_nat_host || null, split_nat_ssh_port: d.split_nat_ssh_port ? parseInt(d.split_nat_ssh_port, 10) : 22, split_nat_ssh_user: d.split_nat_ssh_user || null, split_nat_hk_public_iface: d.split_nat_hk_public_iface || null, protocol: d.protocol || null, vpn_ip_cidrs: d.vpn_ip_cidrs || null, wg_private_key: d.wg_private_key || null, wg_public_key: d.wg_public_key || null, wg_port: d.wg_port ? parseInt(d.wg_port, 10) : null, wg_dns: d.wg_dns || null, ocserv_radius_host: d.ocserv_radius_host || null, ocserv_radius_auth_port: d.ocserv_radius_auth_port ? parseInt(d.ocserv_radius_auth_port, 10) : null, ocserv_radius_acct_port: d.ocserv_radius_acct_port ? parseInt(d.ocserv_radius_acct_port, 10) : null, ocserv_radius_secret: d.ocserv_radius_secret || null, notes: d.notes || null };
            if (d.ssh_password_plain) payload.ssh_password = d.ssh_password_plain;
            if (d.split_nat_ssh_password_plain) payload.split_nat_ssh_password = d.split_nat_ssh_password_plain;
            try { await api('/api/v1/servers/' + d.id, { method: 'PUT', body: JSON.stringify(payload) }); this.editingServer = null; this.loadServers(); } catch (e) { alert(e.message); }
        },
        async deleteServer(id) {
            if (!confirm('确定删除该服务器？')) return;
            try { await api('/api/v1/servers/' + id, { method: 'DELETE' }); } catch (e) { alert(e.message); }
        },
        async createOrder() {
            const uid = this.formOrder.user_id, pid = this.formOrder.product_id;
            if (!uid || !pid) { alert('请选择用户和产品'); return; }
            try { await api('/api/v1/users/' + uid + '/orders', { method: 'POST', body: JSON.stringify({ product_id: parseInt(pid) }) }); this.formOrder = { user_id: '', product_id: '' }; this.loadOrders(); this.loadOverview(); } catch (e) { alert(e.message); }
        },
        async deleteOrder(id) {
            if (!id) return;
            if (!confirm('确定删除该订单？')) return;
            try {
                await api('/api/v1/admin/orders/' + id, { method: 'DELETE' });
                this.loadOrders();
                this.loadOverview();
            } catch (e) {
                alert(e.message || '禁止删除或删除失败');
            }
        },
        async createProduct() {
            const d = this.formProduct; if (!d.name || !d.price_yuan || !d.duration_days) { alert('请填写名称、价格、时长'); return; }
            const priceCents = Math.round(parseFloat(d.price_yuan) * 100);
            try { await api('/api/v1/admin/products', { method: 'POST', body: JSON.stringify({ name: d.name, price_cents: priceCents, duration_days: parseInt(d.duration_days) }) }); this.formProduct = { name: '', price_yuan: '99', duration_days: '30' }; this.loadProducts(); } catch (e) { alert(e.message); }
        },
        editProduct(p) {
            const name = prompt('名称', p.name); if (name == null) return;
            const cents = p.price_cents ?? p.priceCents ?? 0;
            const price = prompt('价格(元)', (cents / 100).toFixed(2)); if (price == null) return;
            const days = prompt('时长(天)', p.duration_days ?? p.durationDays); if (days == null) return;
            const priceCents = Math.round(parseFloat(price) * 100);
            api('/api/v1/admin/products/' + p.id, { method: 'PUT', body: JSON.stringify({ name, price_cents: priceCents, duration_days: parseInt(days) }) }).then(() => this.loadProducts()).catch(e => alert(e.message));
        },
        async deleteProduct(id) {
            if (!confirm('确定删除该产品？')) return;
            try { await api('/api/v1/admin/products/' + id, { method: 'DELETE' }); } catch (e) { alert(e.message); }
        },
        async createReseller() {
            const name = this.formReseller.name?.trim(); if (!name) { alert('请填写名称'); return; }
            try { await api('/api/v1/resellers', { method: 'POST', body: JSON.stringify({ name }) }); this.formReseller = { name: '' }; this.loadResellers(); } catch (e) { alert(e.message); }
        },
        editReseller(r) {
            const name = prompt('名称', r.name); if (name == null) return;
            api('/api/v1/resellers/' + r.id, { method: 'PUT', body: JSON.stringify({ name }) }).then(() => this.loadResellers()).catch(e => alert(e.message));
        },
        async deleteReseller(id) {
            if (!confirm('确定删除该分销商？')) return;
            try { await api('/api/v1/resellers/' + id, { method: 'DELETE' }); } catch (e) { alert(e.message); }
        },
        async createResellerApiKey(id) {
            try {
                await api('/api/v1/admin/resellers/' + id + '/api_keys', { method: 'POST', body: JSON.stringify({}) });
                // 重新加载分销商列表，明文展示最新 API Key
                this.loadResellers();
            } catch (e) {
                alert(e.message || '创建 API Key 失败');
            }
        },
        async createIpPool() {
            const d = this.formIpPool; if (!d.ip_address || !d.region) { alert('请填写IP和区域'); return; }
            try { await api('/api/v1/ip_pool', { method: 'POST', body: JSON.stringify({ ip_address: d.ip_address, region: d.region }) }); this.formIpPool = { ip_address: '', region: '' }; this.loadIPPool(); } catch (e) { alert(e.message); }
        },
        async releaseIpPool(id) {
            try { await api('/api/v1/ip_pool/' + id + '/release', { method: 'POST' }); this.loadIPPool(); } catch (e) { alert(e.message); }
        },
    };
}
</script>
<script>
// 接入服务器模态交互（真实调用后端 API）
(function () {
    const addBtn = document.getElementById('as-add-btn');
    const addFullscreenBtn = document.getElementById('as-add-fullscreen-btn');
    const table = document.getElementById('as-server-table');

    const editBackdrop = document.getElementById('as-edit-backdrop');
    const editModal = document.getElementById('as-edit-modal');
    const editTitle = document.getElementById('as-edit-title');
    const editClose = document.getElementById('as-edit-close');
    const editFullscreen = document.getElementById('as-edit-fullscreen');
    const editCancel = document.getElementById('as-edit-cancel');
    const editSave = document.getElementById('as-edit-save');
    const editForm = document.getElementById('as-edit-form');
    const protocolEl = document.getElementById('as-protocol');
    const natTopoEl = document.getElementById('as-nat-topology');
    const wgFieldsWrap = document.getElementById('as-wg-fields');

    const deleteBackdrop = document.getElementById('as-delete-backdrop');
    const deleteModal = document.getElementById('as-delete-modal');
    const deleteClose = document.getElementById('as-delete-close');
    const deleteCancel = document.getElementById('as-delete-cancel');
    const deleteConfirm = document.getElementById('as-delete-confirm');

    if (!addBtn || !table) return; // 仅在接入服务器页存在时生效

    let currentMode = 'create';
    let pendingDeleteId = null;

    function openEditModal(mode, rowData) {
        currentMode = mode;
        editTitle.textContent = mode === 'edit' ? '编辑接入服务器' : '添加接入服务器';

        editForm.reset();
        editForm.elements['id'].value = rowData && rowData.id ? rowData.id : '';
        editForm.elements['hostname'].value = rowData && rowData.hostname ? rowData.hostname : '';
        editForm.elements['region'].value = rowData && rowData.region ? rowData.region : '';
        editForm.elements['role'].value = rowData && rowData.role ? rowData.role : 'access';
        editForm.elements['cost_cents'].value = rowData && rowData.cost_cents != null ? rowData.cost_cents : 0;
        editForm.elements['protocol'].value = rowData && rowData.protocol != null ? rowData.protocol : '';
        editForm.elements['vpn_ip_cidrs'].value = rowData && rowData.vpn_ip_cidrs != null ? rowData.vpn_ip_cidrs : '';
        editForm.elements['wg_public_key'].value = rowData && rowData.wg_public_key != null ? rowData.wg_public_key : '';
        editForm.elements['wg_port'].value = rowData && rowData.wg_port != null ? rowData.wg_port : '';
        editForm.elements['wg_dns'].value = rowData && rowData.wg_dns != null ? rowData.wg_dns : '';
        editForm.elements['host'].value = rowData && rowData.host != null ? rowData.host : '';
        editForm.elements['ssh_port'].value = rowData && (rowData.ssh_port != null && rowData.ssh_port !== '') ? rowData.ssh_port : '22';
        editForm.elements['ssh_user'].value = rowData && rowData.ssh_user != null ? rowData.ssh_user : 'root';
        // 直接展示当前密码，便于修改；如不想改可保留原值
        editForm.elements['ssh_password'].value = rowData && rowData.ssh_password != null ? rowData.ssh_password : '';
        editForm.elements['agent_enabled'].value = rowData && String(rowData.agent_enabled || '1') === '0' ? '0' : '1';
        editForm.elements['nat_topology'].value = rowData && rowData.nat_topology ? rowData.nat_topology : 'combined';
        editForm.elements['cn_public_iface'].value = rowData && rowData.cn_public_iface != null ? rowData.cn_public_iface : '';
        editForm.elements['hk_public_iface'].value = rowData && rowData.hk_public_iface != null ? rowData.hk_public_iface : '';
        editForm.elements['peer_link_iface'].value = rowData && rowData.peer_link_iface != null ? rowData.peer_link_iface : '';
        editForm.elements['peer_link_local_ip'].value = rowData && rowData.peer_link_local_ip != null ? rowData.peer_link_local_ip : '';
        editForm.elements['peer_link_remote_ip'].value = rowData && rowData.peer_link_remote_ip != null ? rowData.peer_link_remote_ip : '';
        editForm.elements['link_tunnel_type'].value = rowData && rowData.link_tunnel_type != null ? rowData.link_tunnel_type : '';
                editForm.elements['split_nat_host'].value = rowData && rowData.split_nat_host != null ? rowData.split_nat_host : '';
        editForm.elements['split_nat_ssh_port'].value = rowData && rowData.split_nat_ssh_port != null ? rowData.split_nat_ssh_port : '22';
        editForm.elements['split_nat_ssh_user'].value = rowData && rowData.split_nat_ssh_user != null ? rowData.split_nat_ssh_user : 'root';
        editForm.elements['split_nat_ssh_password'].value = rowData && rowData.split_nat_ssh_password != null ? rowData.split_nat_ssh_password : '';
        editForm.elements['split_nat_hk_public_iface'].value = rowData && rowData.split_nat_hk_public_iface != null ? rowData.split_nat_hk_public_iface : '';
        editForm.elements['wg_private_key'].value = '';
        editForm.elements['ocserv_radius_host'].value = rowData && rowData.ocserv_radius_host != null ? rowData.ocserv_radius_host : '';
        editForm.elements['ocserv_radius_auth_port'].value = rowData && rowData.ocserv_radius_auth_port != null ? rowData.ocserv_radius_auth_port : '1812';
        editForm.elements['ocserv_radius_acct_port'].value = rowData && rowData.ocserv_radius_acct_port != null ? rowData.ocserv_radius_acct_port : '1813';
        editForm.elements['ocserv_port'].value = rowData && rowData.ocserv_port != null ? rowData.ocserv_port : '443';
        editForm.elements['ocserv_domain'].value = rowData && rowData.ocserv_domain != null ? rowData.ocserv_domain : '';
        editForm.elements['ocserv_tls_cert_pem'].value = rowData && rowData.ocserv_tls_cert_pem != null ? rowData.ocserv_tls_cert_pem : '';
        editForm.elements['ocserv_tls_key_pem'].value = rowData && rowData.ocserv_tls_key_pem != null ? rowData.ocserv_tls_key_pem : '';
        editForm.elements['ocserv_radius_secret'].value = '';
        editForm.elements['notes'].value = rowData && rowData.notes != null ? rowData.notes : '';

        editBackdrop.classList.add('show');
        editModal.classList.add('open');

        // 显示/隐藏 WireGuard 配置字段
        refreshWgFields();
        refreshTopologyFields();
    }

    function closeEditModal() {
        editModal.classList.remove('open');
        editModal.classList.remove('fullscreen');
        if (editFullscreen) editFullscreen.textContent = '全屏填写';
        setTimeout(() => editBackdrop.classList.remove('show'), 180);
    }

    function openDeleteModal(id) {
        pendingDeleteId = id;
        deleteBackdrop.classList.add('show');
        deleteModal.classList.add('open');
    }

    function closeDeleteModal() {
        deleteModal.classList.remove('open');
        setTimeout(() => deleteBackdrop.classList.remove('show'), 180);
        pendingDeleteId = null;
    }

    // 添加
    addBtn.addEventListener('click', function () {
        openEditModal('create', null);
    });
    if (addFullscreenBtn) {
        addFullscreenBtn.addEventListener('click', function () {
            openEditModal('create', null);
            editModal.classList.add('fullscreen');
            if (editFullscreen) editFullscreen.textContent = '退出全屏';
        });
    }

    function refreshWgFields() {
        if (!wgFieldsWrap || !protocolEl) return;
        const isWg = (protocolEl.value || '') === 'wireguard';
        const wgPubWrap = document.getElementById('as-wg-fields-pub');
        const wgPortWrap = document.getElementById('as-wg-port').closest('.as-field');
        const wgDnsWrap = document.getElementById('as-wg-dns').closest('.as-field');
        const ocHost = document.getElementById('as-oc-radius-host-wrap');
        const ocAuth = document.getElementById('as-oc-radius-auth-port-wrap');
        const ocAcct = document.getElementById('as-oc-radius-acct-port-wrap');
        const ocSecret = document.getElementById('as-oc-radius-secret-wrap');
        const ocPort = document.getElementById('as-oc-port-wrap');
        const ocDomain = document.getElementById('as-oc-domain-wrap');
        const ocCert = document.getElementById('as-oc-cert-wrap');
        const ocKey = document.getElementById('as-oc-key-wrap');
        wgFieldsWrap.style.display = isWg ? '' : 'none';
        if (wgPubWrap) wgPubWrap.style.display = isWg ? '' : 'none';
        if (wgPortWrap) wgPortWrap.style.display = isWg ? '' : 'none';
        if (wgDnsWrap) wgDnsWrap.style.display = isWg ? '' : 'none';
        if (ocHost) ocHost.style.display = isWg ? 'none' : '';
        if (ocAuth) ocAuth.style.display = isWg ? 'none' : '';
        if (ocAcct) ocAcct.style.display = isWg ? 'none' : '';
        if (ocSecret) ocSecret.style.display = isWg ? 'none' : '';
        if (ocPort) ocPort.style.display = isWg ? 'none' : '';
        if (ocDomain) ocDomain.style.display = isWg ? 'none' : '';
        if (ocCert) ocCert.style.display = isWg ? 'none' : '';
        if (ocKey) ocKey.style.display = isWg ? 'none' : '';
    }
    function refreshTopologyFields() {
        if (!natTopoEl) return;
        const isCombined = (natTopoEl.value || 'combined') === 'combined';
        const hkWrap = document.getElementById('as-hk-public-iface-wrap');
        const peerIds = [
            'as-peer-link-iface-wrap',
            'as-peer-link-local-ip-wrap',
            'as-peer-link-remote-ip-wrap',
            'as-link-tunnel-type-wrap',
            'as-split-nat-server-id-wrap',
            'as-split-nat-host-wrap',
            'as-split-nat-ssh-port-wrap',
            'as-split-nat-ssh-user-wrap',
            'as-split-nat-ssh-password-wrap',
            'as-split-nat-hk-public-iface-wrap',
        ];
        if (hkWrap) hkWrap.style.display = isCombined ? '' : 'none';
        peerIds.forEach((id) => {
            const el = document.getElementById(id);
            if (el) el.style.display = isCombined ? 'none' : '';
        });
    }
    if (protocolEl) {
        protocolEl.addEventListener('change', refreshWgFields);
    }
    if (natTopoEl) {
        natTopoEl.addEventListener('change', refreshTopologyFields);
    }
    if (editFullscreen) {
        editFullscreen.addEventListener('click', function () {
            const expanded = editModal.classList.toggle('fullscreen');
            editFullscreen.textContent = expanded ? '退出全屏' : '全屏填写';
        });
    }

    // 行级编辑/删除（事件委托）
    table.addEventListener('click', function (e) {
        const editBtn = e.target.closest('.as-edit');
        const installBtn = e.target.closest('.as-install');
        const delBtn = e.target.closest('.as-delete');
        if (editBtn) {
            const tr = editBtn.closest('tr');
            if (!tr) return;
            const data = {
                id: tr.getAttribute('data-id'),
                hostname: tr.getAttribute('data-hostname') || '',
                region: tr.getAttribute('data-region') || '',
                role: tr.getAttribute('data-role') || 'access',
                cost_cents: tr.getAttribute('data-cost-cents') || 0,
                host: tr.getAttribute('data-host') || '',
                ssh_port: tr.getAttribute('data-ssh-port') || '22',
                ssh_user: tr.getAttribute('data-ssh-user') || 'root',
                ssh_password: tr.getAttribute('data-ssh-password') || '',
                agent_enabled: tr.getAttribute('data-agent-enabled') || '1',
                nat_topology: tr.getAttribute('data-nat-topology') || 'combined',
                cn_public_iface: tr.getAttribute('data-cn-public-iface') || '',
                hk_public_iface: tr.getAttribute('data-hk-public-iface') || '',
                peer_link_iface: tr.getAttribute('data-peer-link-iface') || '',
                peer_link_local_ip: tr.getAttribute('data-peer-link-local-ip') || '',
                peer_link_remote_ip: tr.getAttribute('data-peer-link-remote-ip') || '',
                link_tunnel_type: tr.getAttribute('data-link-tunnel-type') || '',
                                split_nat_host: tr.getAttribute('data-split-nat-host') || '',
                split_nat_ssh_port: tr.getAttribute('data-split-nat-ssh-port') || '22',
                split_nat_ssh_user: tr.getAttribute('data-split-nat-ssh-user') || 'root',
                split_nat_ssh_password: tr.getAttribute('data-split-nat-ssh-password') || '',
                split_nat_hk_public_iface: tr.getAttribute('data-split-nat-hk-public-iface') || '',
                notes: tr.getAttribute('data-notes') || '',
                protocol: tr.getAttribute('data-protocol') || '',
                vpn_ip_cidrs: tr.getAttribute('data-vpn-ip-cidrs') || '',
                wg_private_key: '',
                wg_public_key: tr.getAttribute('data-wg-public-key') || '',
                wg_port: tr.getAttribute('data-wg-port') || '',
                wg_dns: tr.getAttribute('data-wg-dns') || '',
                ocserv_radius_host: tr.getAttribute('data-ocserv-radius-host') || '',
                ocserv_radius_auth_port: tr.getAttribute('data-ocserv-radius-auth-port') || '1812',
                ocserv_radius_acct_port: tr.getAttribute('data-ocserv-radius-acct-port') || '1813',
                ocserv_port: tr.getAttribute('data-ocserv-port') || '443',
                ocserv_domain: tr.getAttribute('data-ocserv-domain') || '',
                ocserv_tls_cert_pem: tr.getAttribute('data-ocserv-tls-cert-pem') || '',
                ocserv_tls_key_pem: tr.getAttribute('data-ocserv-tls-key-pem') || '',
                ocserv_radius_secret: '',
            };
            openEditModal('edit', data);
        } else if (installBtn) {
            const id = installBtn.getAttribute('data-id');
            if (!id) return;
            if (!confirm('将通过 SSH 自动部署并启动该节点的 agent，是否继续？')) return;
            const api = window.__adminApi || null;
            if (!api) return;
            api('/api/v1/admin/servers/' + id + '/agent/install', { method: 'POST' })
                .then(() => alert('已提交部署任务（maintenance 队列）'))
                .catch((err) => alert((err && err.message) ? err.message : '部署任务提交失败'));
        } else if (delBtn) {
            const id = delBtn.getAttribute('data-id');
            openDeleteModal(id);
        }
    });

    // 保存（真实提交，保持字段名不变）
    editSave.addEventListener('click', async function () {
        const fd = new FormData(editForm);
        const data = Object.fromEntries(fd.entries());
        const api = window.__adminApi || null;
        if (!api) {
            console.log('save server (no api helper)', currentMode, data);
            closeEditModal();
            return;
        }
        const hostname = (data.hostname || '').trim();
        const region = (data.region || '').trim();
        if (currentMode === 'create') {
            if (!hostname) { alert('请填写主机名'); return; }
            if (!region) { alert('请填写区域'); return; }
        }
        const portNum = data.ssh_port ? parseInt(data.ssh_port, 10) : 22;
        const payload = {
            hostname: hostname,
            region: region,
            role: (data.role || 'access').trim() || 'access',
            cost_cents: data.cost_cents !== '' && data.cost_cents != null ? parseInt(data.cost_cents, 10) : 0,
            protocol: (data.protocol || '').trim() || null,
            vpn_ip_cidrs: (data.vpn_ip_cidrs || '').trim() || null,
            wg_private_key: (data.wg_private_key || '').trim() || null,
            wg_public_key: (data.wg_public_key || '').trim() || null,
            wg_port: data.wg_port ? parseInt(data.wg_port, 10) : null,
            wg_dns: (data.wg_dns || '').trim() || null,
            ocserv_radius_host: (data.ocserv_radius_host || '').trim() || null,
            ocserv_radius_auth_port: data.ocserv_radius_auth_port ? parseInt(data.ocserv_radius_auth_port, 10) : null,
            ocserv_radius_acct_port: data.ocserv_radius_acct_port ? parseInt(data.ocserv_radius_acct_port, 10) : null,
            ocserv_radius_secret: (data.ocserv_radius_secret || '').trim() || null,
            ocserv_port: data.ocserv_port ? parseInt(data.ocserv_port, 10) : null,
            ocserv_domain: (data.ocserv_domain || '').trim() || null,
            ocserv_tls_cert_pem: (data.ocserv_tls_cert_pem || '').trim() || null,
            ocserv_tls_key_pem: (data.ocserv_tls_key_pem || '').trim() || null,
            host: (data.host || '').trim() || null,
            ssh_port: (Number.isNaN(portNum) || portNum < 1 || portNum > 65535) ? 22 : portNum,
            ssh_user: (data.ssh_user || 'root').trim() || 'root',
            agent_enabled: String(data.agent_enabled || '1') !== '0',
            nat_topology: (data.nat_topology || 'combined').trim() || 'combined',
            cn_public_iface: (data.cn_public_iface || '').trim() || null,
            hk_public_iface: (data.hk_public_iface || '').trim() || null,
            peer_link_iface: (data.peer_link_iface || '').trim() || null,
            peer_link_local_ip: (data.peer_link_local_ip || '').trim() || null,
            peer_link_remote_ip: (data.peer_link_remote_ip || '').trim() || null,
            link_tunnel_type: (data.link_tunnel_type || '').trim() || null,
                        split_nat_host: (data.split_nat_host || '').trim() || null,
            split_nat_ssh_port: (data.split_nat_ssh_port && String(data.split_nat_ssh_port).trim()) ? parseInt(String(data.split_nat_ssh_port).trim(), 10) : 22,
            split_nat_ssh_user: (data.split_nat_ssh_user || '').trim() || null,
            split_nat_hk_public_iface: (data.split_nat_hk_public_iface || '').trim() || null,
            notes: (data.notes || '').trim() || null
        };
        if (data.split_nat_ssh_password && String(data.split_nat_ssh_password).trim()) {
            payload.split_nat_ssh_password = String(data.split_nat_ssh_password).trim();
        }
        if (payload.nat_topology === 'combined') {
            if (!payload.cn_public_iface || !payload.hk_public_iface) {
                alert('一体模式请至少填写 CN 公网网卡 与 HK 公网网卡');
                return;
            }
        } else {
            if (!payload.cn_public_iface || !payload.peer_link_iface  || !payload.split_nat_host || !payload.split_nat_ssh_user || !payload.split_nat_ssh_password) {
                alert('分体模式请填写 CN 公网网卡、互联网卡、NAT服务器ID、NAT 服务器 IP/SSH 用户/SSH 密码');
                return;
            }
        }
        // WireGuard 协议下要求至少填写 VPN CIDR；公钥由私钥自动计算
        if ((payload.protocol || '') === 'wireguard') {
            if (!payload.vpn_ip_cidrs) { alert('WireGuard 服务器必须填写 VPN 内网 IP 范围（CIDR）'); return; }
            if (!payload.wg_private_key && currentMode === 'create') {
                // 允许留空由后端自动生成
            }
        }
        if ((payload.protocol || '') === 'ocserv') {
            if (!payload.ocserv_radius_host || !payload.ocserv_radius_secret || !payload.ocserv_domain || !payload.ocserv_tls_cert_pem || !payload.ocserv_tls_key_pem || !payload.ocserv_port) {
                alert('OCServ 模式必须填写 RADIUS、服务端口、绑定域名、SSL证书与私钥');
                return;
            }
        }
        if (data.ssh_password && String(data.ssh_password).trim()) payload.ssh_password = data.ssh_password;
        try {
            if (currentMode === 'edit' && data.id) {
                await api('/api/v1/servers/' + data.id, {
                    method: 'PUT',
                    body: JSON.stringify(payload)
                });
            } else {
                await api('/api/v1/servers', {
                    method: 'POST',
                    body: JSON.stringify(payload)
                });
            }
        } catch (e) {
            console.error('save server failed', e);
            alert(e.message || '保存服务器失败');
        }
        closeEditModal();
        if (window.Alpine && Alpine.store && Alpine.store('dashboard') && Alpine.store('dashboard').loadServers) {
            Alpine.store('dashboard').loadServers();
        } else if (window.location) {
            window.location.reload();
        }
    });

    // 关闭/取消编辑模态
    editClose.addEventListener('click', closeEditModal);
    editCancel.addEventListener('click', closeEditModal);
    // 遮罩点击不关闭
    // 删除确认
    deleteConfirm.addEventListener('click', async function () {
        if (pendingDeleteId == null) {
            closeDeleteModal();
            return;
        }
        const api = window.__adminApi || null;
        if (!api) {
            console.log('delete server (no api helper)', pendingDeleteId);
            closeDeleteModal();
            return;
        }
        try {
            await api('/api/v1/servers/' + pendingDeleteId, {
                method: 'DELETE'
            });
        } catch (e) {
            console.error('delete server failed', e);
            alert(e.message || '删除服务器失败');
        }
        closeDeleteModal();
        if (window.Alpine && Alpine.store && Alpine.store('dashboard') && Alpine.store('dashboard').loadServers) {
            Alpine.store('dashboard').loadServers();
        } else if (window.location) {
            window.location.reload();
        }
    });
    deleteClose.addEventListener('click', closeDeleteModal);
    deleteCancel.addEventListener('click', closeDeleteModal);
    // 遮罩点击不关闭

    refreshTopologyFields();
})();

(function () {
    const addBtn = document.getElementById('prod-add-btn');
    const table = document.getElementById('prod-table');

    const editBackdrop = document.getElementById('prod-edit-backdrop');
    const editModal = document.getElementById('prod-edit-modal');
    const editTitle = document.getElementById('prod-edit-title');
    const editClose = document.getElementById('prod-edit-close');
    const editCancel = document.getElementById('prod-edit-cancel');
    const editSave = document.getElementById('prod-edit-save');
    const editForm = document.getElementById('prod-edit-form');

    const deleteBackdrop = document.getElementById('prod-delete-backdrop');
    const deleteModal = document.getElementById('prod-delete-modal');
    const deleteClose = document.getElementById('prod-delete-close');
    const deleteCancel = document.getElementById('prod-delete-cancel');
    const deleteConfirm = document.getElementById('prod-delete-confirm');

    if (!addBtn || !table) return;

    let currentMode = 'create';
    let pendingDeleteId = null;

    function openEditModal(mode, rowData) {
        currentMode = mode;
        editTitle.textContent = mode === 'edit' ? '编辑产品' : '添加产品';

        editForm.reset();
        editForm.elements['id'].value = rowData && rowData.id ? rowData.id : '';
        editForm.elements['name'].value = rowData && rowData.name ? rowData.name : '';
        editForm.elements['price_yuan'].value = rowData && rowData.price != null && rowData.price !== '' ? (parseInt(rowData.price, 10) / 100).toFixed(2) : '';
        editForm.elements['duration_days'].value = rowData && rowData.days ? rowData.days : '';
        const er = rowData && rowData.enable_radius != null ? String(rowData.enable_radius) : '1';
        const ew = rowData && rowData.enable_wireguard != null ? String(rowData.enable_wireguard) : '1';
        const dp = rowData && rowData.requires_dedicated_public_ip != null ? String(rowData.requires_dedicated_public_ip) : '0';
        const erEl = document.getElementById('prod-enable-radius');
        const ewEl = document.getElementById('prod-enable-wireguard');
        const dpEl = document.getElementById('prod-requires-dedicated-public-ip');
        if (erEl) erEl.checked = (er !== '0');
        if (ewEl) ewEl.checked = (ew !== '0');
        if (dpEl) dpEl.checked = (dp !== '0');

        const bwEl = document.getElementById('prod-bandwidth-kbps');
        const tqEl = document.getElementById('prod-traffic-quota-gb');
        if (bwEl) {
            const b = rowData && rowData.bandwidth_limit_kbps != null && String(rowData.bandwidth_limit_kbps) !== ''
                ? String(rowData.bandwidth_limit_kbps)
                : '';
            bwEl.value = b;
        }
        if (tqEl) {
            const g = rowData && rowData.traffic_quota_gb != null && String(rowData.traffic_quota_gb) !== ''
                ? String(rowData.traffic_quota_gb)
                : '';
            tqEl.value = g;
        }

        editBackdrop.classList.add('show');
        editModal.classList.add('open');
    }

    function closeEditModal() {
        editModal.classList.remove('open');
        setTimeout(() => editBackdrop.classList.remove('show'), 180);
    }

    function openDeleteModal(id) {
        pendingDeleteId = id;
        deleteBackdrop.classList.add('show');
        deleteModal.classList.add('open');
    }

    function closeDeleteModal() {
        deleteModal.classList.remove('open');
        setTimeout(() => deleteBackdrop.classList.remove('show'), 180);
        pendingDeleteId = null;
    }

    addBtn.addEventListener('click', function () {
        openEditModal('create', null);
    });

    table.addEventListener('click', function (e) {
        if (e.target.classList.contains('prod-edit')) {
            const tr = e.target.closest('tr');
            if (!tr) return;
            const data = {
                id: tr.getAttribute('data-id'),
                name: tr.getAttribute('data-name'),
                price: tr.getAttribute('data-price'),
                days: tr.getAttribute('data-days'),
                enable_radius: tr.getAttribute('data-enable-radius'),
                enable_wireguard: tr.getAttribute('data-enable-wireguard'),
                requires_dedicated_public_ip: tr.getAttribute('data-requires-dedicated-public-ip'),
                bandwidth_limit_kbps: tr.getAttribute('data-bandwidth-kbps'),
                traffic_quota_gb: tr.getAttribute('data-traffic-gb')
            };
            openEditModal('edit', data);
        } else if (e.target.classList.contains('prod-delete')) {
            const id = e.target.getAttribute('data-id');
            openDeleteModal(id);
        }
    });

    editSave.addEventListener('click', async function () {
        const fd = new FormData(editForm);
        const data = Object.fromEntries(fd.entries());
        const api = window.__adminApi || null;
        if (!api) {
            console.log('save product (no api helper)', currentMode, data);
            closeEditModal();
            return;
        }
        if (!data.name || !data.price_yuan || !data.duration_days) {
            alert('请填写名称、价格和时长');
            return;
        }
        const priceCents = Math.round(parseFloat(data.price_yuan) * 100);
        const bwRaw = document.getElementById('prod-bandwidth-kbps')?.value?.trim() ?? '';
        const tqRaw = document.getElementById('prod-traffic-quota-gb')?.value?.trim() ?? '';
        const bwParsed = bwRaw === '' ? null : parseInt(bwRaw, 10);
        const tqParsed = tqRaw === '' ? null : parseFloat(tqRaw);
        const payload = {
            name: data.name,
            price_cents: priceCents,
            duration_days: parseInt(data.duration_days, 10),
            enable_radius: !!document.getElementById('prod-enable-radius')?.checked,
            enable_wireguard: !!document.getElementById('prod-enable-wireguard')?.checked,
            requires_dedicated_public_ip: !!document.getElementById('prod-requires-dedicated-public-ip')?.checked,
            bandwidth_limit_kbps: bwRaw === '' || !Number.isFinite(bwParsed) || bwParsed < 1 ? null : bwParsed,
            traffic_quota_gb: tqRaw === '' || !Number.isFinite(tqParsed) || tqParsed <= 0 ? null : tqParsed,
        };
        if (!payload.enable_radius && !payload.enable_wireguard) {
            alert('请至少选择一种开通协议（FreeRADIUS / WireGuard）');
            return;
        }
        try {
            if (currentMode === 'edit' && data.id) {
                await api('/api/v1/admin/products/' + data.id, {
                    method: 'PUT',
                    body: JSON.stringify(payload)
                });
            } else {
                await api('/api/v1/admin/products', {
                    method: 'POST',
                    body: JSON.stringify(payload)
                });
            }
        } catch (e) {
            console.error('save product failed', e);
            alert(e.message || '保存产品失败');
        }
        closeEditModal();
        if (window.Alpine && Alpine.store && Alpine.store('dashboard') && Alpine.store('dashboard').loadProducts) {
            Alpine.store('dashboard').loadProducts();
        } else if (window.location) {
            window.location.reload();
        }
    });

    editClose.addEventListener('click', closeEditModal);
    editCancel.addEventListener('click', closeEditModal);
    // 遮罩点击不关闭，仅取消/保存可关闭，防止误触
    deleteConfirm.addEventListener('click', async function () {
        if (pendingDeleteId == null) {
            closeDeleteModal();
            return;
        }
        const api = window.__adminApi || null;
        if (!api) {
            console.log('delete product (no api helper)', pendingDeleteId);
            closeDeleteModal();
            return;
        }
        try {
            await api('/api/v1/admin/products/' + pendingDeleteId, {
                method: 'DELETE'
            });
        } catch (e) {
            console.error('delete product failed', e);
            alert(e.message || '删除产品失败');
        }
        closeDeleteModal();
        if (window.Alpine && Alpine.store && Alpine.store('dashboard') && Alpine.store('dashboard').loadProducts) {
            Alpine.store('dashboard').loadProducts();
        } else if (window.location) {
            window.location.reload();
        }
    });
    deleteClose.addEventListener('click', closeDeleteModal);
    deleteCancel.addEventListener('click', closeDeleteModal);
    // 遮罩点击不关闭
})();

// 分销商模态交互（真实调用后端 API）
(function () {
    const addBtn = document.getElementById('reseller-add-btn');
    const table = document.getElementById('reseller-table');
    if (!addBtn || !table) return;

    const editBackdrop = document.getElementById('reseller-edit-backdrop');
    const editModal = document.getElementById('reseller-edit-modal');
    const editTitle = document.getElementById('reseller-edit-title');
    const editClose = document.getElementById('reseller-edit-close');
    const editCancel = document.getElementById('reseller-edit-cancel');
    const editSave = document.getElementById('reseller-edit-save');
    const editForm = document.getElementById('reseller-edit-form');

    const deleteBackdrop = document.getElementById('reseller-delete-backdrop');
    const deleteModal = document.getElementById('reseller-delete-modal');
    const deleteClose = document.getElementById('reseller-delete-close');
    const deleteCancel = document.getElementById('reseller-delete-cancel');
    const deleteConfirm = document.getElementById('reseller-delete-confirm');

    let mode = 'create';
    let pendingDeleteId = null;

    function openEdit(row) {
        mode = row ? 'edit' : 'create';
        editTitle.textContent = mode === 'edit' ? '编辑分销商' : '添加分销商';
        editForm.reset();
        editForm.elements['id'].value = row && row.id ? row.id : '';
        editForm.elements['name'].value = row && row.name ? row.name : '';
        editForm.elements['email'].value = row && row.email ? row.email : '';
        editForm.elements['balance_cents'].value = row && row.balance_cents != null ? row.balance_cents : 0;
        const beRaw = row && row.balance_enforced != null ? String(row.balance_enforced) : '';
        const be = ['1', 'true', 'TRUE', 'yes', 'YES'].includes(beRaw);
        editForm.elements['balance_enforced'].value = be ? '1' : '0';
        editForm.elements['status'].value = row && row.status ? row.status : 'active';
        editBackdrop.classList.add('show');
        editModal.classList.add('open');
    }
    function closeEdit() {
        editModal.classList.remove('open');
        setTimeout(() => editBackdrop.classList.remove('show'), 180);
    }
    function openDelete(id) {
        pendingDeleteId = id;
        deleteBackdrop.classList.add('show');
        deleteModal.classList.add('open');
    }
    function closeDelete() {
        deleteModal.classList.remove('open');
        setTimeout(() => deleteBackdrop.classList.remove('show'), 180);
        pendingDeleteId = null;
    }

    addBtn.addEventListener('click', function () {
        openEdit(null);
    });

    table.addEventListener('click', function (e) {
        const target = e.target;
        if (target.classList.contains('reseller-edit')) {
            const tr = target.closest('tr');
            if (!tr) return;
            const row = {
                id: tr.getAttribute('data-id'),
                        name: tr.getAttribute('data-name'),
                        email: tr.getAttribute('data-email'),
                        balance_cents: tr.getAttribute('data-balance-cents'),
                        balance_enforced: tr.getAttribute('data-balance-enforced'),
                        status: tr.getAttribute('data-status'),
            };
            openEdit(row);
        } else if (target.classList.contains('reseller-delete')) {
            const id = target.getAttribute('data-id');
            if (!id) return;
            openDelete(id);
        } else if (target.classList.contains('reseller-api')) {
            const id = target.getAttribute('data-id');
            if (!id) return;
            const api = window.__adminApi || null;
            if (!api) {
                console.log('create reseller api key (no api helper)', id);
                return;
            }
            api('/api/v1/admin/resellers/' + id + '/api_keys', {
                method: 'POST',
                        // 后端默认不会自动清空旧 Key；这里保持“只保留最新一把”的管理体验
                        body: JSON.stringify({ replace_all: true, name: 'latest' })
            }).then(res => {
                const key = res && (res.api_key || res.apiKey);
                if (key) {
                    alert('API Key（仅显示一次）: ' + key);
                } else {
                    alert('API Key 创建成功，但未返回 key');
                }
            }).catch(err => {
                console.error('create reseller api key failed', err);
                alert(err.message || '创建 API Key 失败');
            });
                } else if (target.classList.contains('reseller-balance-adjust')) {
                    const id = target.getAttribute('data-id');
                    if (!id) return;
                    const delta = prompt('资金调整（delta_cents，可正可负）：', '0');
                    if (delta == null) return;
                    const deltaInt = parseInt(delta);
                    if (Number.isNaN(deltaInt)) { alert('delta_cents 必须是整数'); return; }
                    const note = prompt('备注（可空）:', '') || '';
                    const api = window.__adminApi || null;
                    if (!api) return;
                    try {
                        api('/api/v1/admin/resellers/' + id + '/balance_adjust', {
                            method: 'POST',
                            body: JSON.stringify({ delta_cents: deltaInt, note })
                        }).then(() => {
                            window.location.reload();
                        });
                    } catch (e) {
                        alert(e.message || '资金调整失败');
                    }
        }
    });

    editSave.addEventListener('click', async function () {
        const fd = new FormData(editForm);
        const data = Object.fromEntries(fd.entries());
        const api = window.__adminApi || null;
        if (!api) {
            console.log('save reseller (no api helper)', mode, data);
            closeEdit();
            return;
        }
        if (!data.name || !data.name.trim()) {
            alert('请填写名称');
            return;
        }
        try {
            // 只在非空时才提交 password（避免把空字符串当作“要更新”）
            const payload = {
                name: data.name.trim(),
                email: (data.email || '').trim() || null,
                balance_cents: data.balance_cents === '' ? 0 : parseInt(data.balance_cents),
                balance_enforced: data.balance_enforced === '1' || data.balance_enforced === 1 || data.balance_enforced === true,
                status: (data.status || 'active')
            };
            const pw = (data.password || '').trim();
            if (pw) payload.password = pw;

            if (mode === 'edit' && data.id) {
                await api('/api/v1/resellers/' + data.id, {
                    method: 'PUT',
                    body: JSON.stringify(payload)
                });
            } else {
                await api('/api/v1/resellers', {
                    method: 'POST',
                    body: JSON.stringify(payload)
                });
            }
        } catch (e) {
            console.error('save reseller failed', e);
            alert(e.message || '保存分销商失败');
        }
        closeEdit();
        if (window.Alpine && Alpine.store && Alpine.store('dashboard') && Alpine.store('dashboard').loadResellers) {
            Alpine.store('dashboard').loadResellers();
        } else if (window.location) {
            window.location.reload();
        }
    });
    editClose.addEventListener('click', closeEdit);
    editCancel.addEventListener('click', closeEdit);
    // 遮罩点击不关闭

    deleteConfirm.addEventListener('click', async function () {
        if (pendingDeleteId == null) {
            closeDelete();
            return;
        }
        const api = window.__adminApi || null;
        if (!api) {
            console.log('delete reseller (no api helper)', pendingDeleteId);
            closeDelete();
            return;
        }
        try {
            await api('/api/v1/resellers/' + pendingDeleteId, {
                method: 'DELETE'
            });
        } catch (e) {
            console.error('delete reseller failed', e);
            alert(e.message || '删除分销商失败');
        }
        closeDelete();
        if (window.Alpine && Alpine.store && Alpine.store('dashboard') && Alpine.store('dashboard').loadResellers) {
            Alpine.store('dashboard').loadResellers();
        } else if (window.location) {
            window.location.reload();
        }
    });
    deleteClose.addEventListener('click', closeDelete);
    deleteCancel.addEventListener('click', closeDelete);
    // 遮罩点击不关闭
})();

// 终端用户（用户管理）模态交互（真实调用后端 API；同时用于「用户管理」与「已购产品」表）
(function () {
    const editBackdrop = document.getElementById('vpn-edit-backdrop');
    const editModal = document.getElementById('vpn-edit-modal');
    const editClose = document.getElementById('vpn-edit-close');
    const editCancel = document.getElementById('vpn-edit-cancel');
    const editSave = document.getElementById('vpn-edit-save');
    const editForm = document.getElementById('vpn-edit-form');

    const ownerEl = document.getElementById('vpn-owner');
    const resellerEl = document.getElementById('vpn-reseller');
    const orderEl = document.getElementById('vpn-order');
    const expireEl = document.getElementById('vpn-expire');

    const delBackdrop = document.getElementById('vpn-delete-backdrop');
    const delModal = document.getElementById('vpn-delete-modal');
    const delClose = document.getElementById('vpn-delete-close');
    const delCancel = document.getElementById('vpn-delete-cancel');
    const delConfirm = document.getElementById('vpn-delete-confirm');

    let pendingDelete = { vpnUserId: null, userId: null };

    /** 保存/删除 VPN 后刷新：优先 Alpine.store('dashboard')，否则 Alpine.$data(根 x-data)，再否则整页刷新 */
    function refreshAdminDashboardVpnLists() {
        let done = false;
        try {
            if (window.Alpine && typeof Alpine.store === 'function') {
                const s = Alpine.store('dashboard');
                if (s) {
                    if (typeof s.loadVpnAccounts === 'function') {
                        s.loadVpnAccounts();
                        done = true;
                    }
                    if (typeof s.loadPurchasedProducts === 'function') {
                        s.loadPurchasedProducts();
                        done = true;
                    }
                }
            }
        } catch (e) { /* store 未注册时可能抛错 */ }
        if (!done) {
            try {
                const root = document.querySelector('[x-data*="adminDashboard"]');
                if (root && window.Alpine && typeof Alpine.$data === 'function') {
                    const d = Alpine.$data(root);
                    if (d) {
                        if (typeof d.loadVpnAccounts === 'function') d.loadVpnAccounts();
                        if (typeof d.loadPurchasedProducts === 'function') d.loadPurchasedProducts();
                        done = true;
                    }
                }
            } catch (e2) {}
        }
        if (!done && window.location) window.location.reload();
    }

    async function loadWireguardConfig(vpnUserId) {
        const wgTa = document.getElementById('vpn-wg-config');
        const wgMsg = document.getElementById('vpn-wg-msg');
        if (wgTa) wgTa.value = '';
        if (wgMsg) wgMsg.textContent = '正在拉取 WireGuard 配置…';
        const api = window.__adminApi || null;
        if (!api || !vpnUserId) {
            if (wgMsg) wgMsg.textContent = '';
            return;
        }
        try {
            const data = await api('/api/v1/admin/vpn_users/' + vpnUserId + '/wireguard_config');
            if (wgTa && data && data.config) {
                wgTa.value = data.config;
                if (wgMsg) wgMsg.textContent = '';
            } else if (wgMsg) {
                wgMsg.textContent = '未返回配置内容';
            }
        } catch (err) {
            if (wgMsg) wgMsg.textContent = err.message || '无法加载 WireGuard 配置';
        }
    }

    const reloadWgBtn = document.getElementById('vpn-wg-reload');
    if (reloadWgBtn) {
        reloadWgBtn.addEventListener('click', function () {
            const id = editForm.elements['id'] && editForm.elements['id'].value;
            if (id) loadWireguardConfig(id);
        });
    }

    function openEdit() {
        editBackdrop.classList.add('show');
        editModal.classList.add('open');
    }
    function closeEdit() {
        editModal.classList.remove('open');
        setTimeout(() => editBackdrop.classList.remove('show'), 180);
    }
    function openDelete(vpnUserId, userId) {
        pendingDelete = { vpnUserId, userId };
        delBackdrop.classList.add('show');
        delModal.classList.add('open');
    }
    function closeDelete() {
        delModal.classList.remove('open');
        setTimeout(() => delBackdrop.classList.remove('show'), 180);
        pendingDelete = { vpnUserId: null, userId: null };
    }

    async function loadDetail(vpnUserId) {
        const api = window.__adminApi || null;
        if (!api) throw new Error('no api helper');
        return await api('/api/v1/vpn_users/' + vpnUserId);
    }

    /** 供 Alpine 与事件委托共用（对齐 B 站 @click="openDetail(id)" 的显式入口） */
    async function openVpnUserDetailModal(vpnUserId) {
        if (!vpnUserId) return;

        // Reset UI
        editForm.reset();
        editForm.elements['id'].value = String(vpnUserId);
        if (ownerEl) ownerEl.textContent = '-';
        if (resellerEl) resellerEl.textContent = '-';
        if (orderEl) orderEl.textContent = '-';
        if (expireEl) expireEl.textContent = '-';
        document.getElementById('vpn-radius-pass').value = '';
        const wgTa0 = document.getElementById('vpn-wg-config');
        const wgMsg0 = document.getElementById('vpn-wg-msg');
        if (wgTa0) wgTa0.value = '';
        if (wgMsg0) wgMsg0.textContent = '';

        openEdit();
        try {
            const data = await loadDetail(vpnUserId);
            const vu = data.vpn_user || {};
            const u = data.user || null;
            const reseller = data.reseller || null;
            const lo = data.latest_order || null;

            editForm.elements['name'].value = vu.name || '';
            editForm.elements['status'].value = vu.status || 'active';
            editForm.elements['region'].value = vu.region || '';
            editForm.elements['radius_username'].value = vu.radius_username || vu.name || '';
            // 密码不自动填入输入框（避免误改），展示可读信息在详情区

            if (ownerEl) ownerEl.textContent = u ? (`${u.email || '-'} (ID:${u.id})`) : `UID:${vu.user_id}`;
            if (resellerEl) resellerEl.textContent = reseller ? `${reseller.name} (ID:${reseller.id})` : '-';
            if (orderEl) orderEl.textContent = lo ? `#${lo.id} ${lo.status}${lo.product && lo.product.name ? (' · ' + lo.product.name) : ''}` : '-';
            if (expireEl) expireEl.textContent = lo && lo.expires_at ? new Date(lo.expires_at).toLocaleString() : '-';
            await loadWireguardConfig(vpnUserId);
        } catch (err) {
            console.error('load vpn detail failed', err);
            alert(err.message || '加载详情失败');
            closeEdit();
        }
    }
    window.__openAdminVpnUserDetail = openVpnUserDetailModal;

    async function onVpnTableClick(e) {
        // 点击文字节点时 target 可能不是 ELEMENT，classList/closest 会失效
        const raw = e.target;
        const el = raw && raw.nodeType === Node.TEXT_NODE ? raw.parentElement : raw;
        if (!el || typeof el.closest !== 'function') return;

        const viewBtn = el.closest('.vpn-view');
        const delBtn = el.closest('.vpn-delete');

        if (viewBtn) {
            const tr = viewBtn.closest('tr');
            if (!tr) return;
            const vpnUserId = tr.getAttribute('data-id');
            if (!vpnUserId) return;
            await openVpnUserDetailModal(vpnUserId);
        } else if (delBtn) {
            const vpnUserId = delBtn.getAttribute('data-id');
            const userId = delBtn.getAttribute('data-user_id') || '0';
            if (!vpnUserId) return;
            openDelete(vpnUserId, userId);
        }
    }

    ['vpn-table', 'purchased-table'].forEach(function (tid) {
        const el = document.getElementById(tid);
        if (el) el.addEventListener('click', onVpnTableClick);
    });

    editSave.addEventListener('click', async function () {
        const api = window.__adminApi || null;
        if (!api) {
            console.log('save vpn_user (no api helper)');
            closeEdit();
            return;
        }
        const id = editForm.elements['id'].value;
        if (!id) return;

        const payload = {
            name: editForm.elements['name'].value,
            status: editForm.elements['status'].value,
            region: (editForm.elements['region'].value || '').trim() || null,
            radius_username: editForm.elements['radius_username'].value || null,
        };

        const pass = (editForm.elements['radius_password'].value || '').trim();
        if (pass) payload.radius_password = pass;

        try {
            await api('/api/v1/vpn_users/' + id, {
                method: 'PUT',
                body: JSON.stringify(payload)
            });
        } catch (e) {
            console.error('update vpn_user failed', e);
            alert(e.message || '保存失败');
            return;
        }
        closeEdit();
        refreshAdminDashboardVpnLists();
    });

    editClose.addEventListener('click', closeEdit);
    editCancel.addEventListener('click', closeEdit);
    // 遮罩点击不关闭

    delConfirm.addEventListener('click', async function () {
        const api = window.__adminApi || null;
        if (!api) {
            console.log('delete vpn_user (no api helper)', pendingDelete);
            closeDelete();
            return;
        }
        if (!pendingDelete.vpnUserId) {
            closeDelete();
            return;
        }
        const uid = (pendingDelete.userId === null || pendingDelete.userId === undefined || pendingDelete.userId === '')
            ? '0'
            : String(pendingDelete.userId);
        try {
            await api(`/api/v1/users/${uid}/vpn_users/${pendingDelete.vpnUserId}`, {
                method: 'DELETE'
            });
        } catch (e) {
            console.error('delete vpn_user failed', e);
            alert(e.message || '删除失败');
        }
        closeDelete();
        refreshAdminDashboardVpnLists();
    });
    delClose.addEventListener('click', closeDelete);
    delCancel.addEventListener('click', closeDelete);
    // 遮罩点击不关闭
})();

// IP池模态交互（原生 JS，仅模拟 console.log）
(function () {
    const addBtn = document.getElementById('ip-add-btn');
    const batchDeleteBtn = document.getElementById('ip-batch-delete-btn');
    const table = document.getElementById('ip-table');
    const selectAll = document.getElementById('ip-select-all');
    if (!addBtn || !table) return;

    const editBackdrop = document.getElementById('ip-edit-backdrop');
    const editModal = document.getElementById('ip-edit-modal');
    const editTitle = document.getElementById('ip-edit-title');
    const editClose = document.getElementById('ip-edit-close');
    const editCancel = document.getElementById('ip-edit-cancel');
    const editSave = document.getElementById('ip-edit-save');
    const editForm = document.getElementById('ip-edit-form');

    const batchTextarea = document.getElementById('ip-batch');

    const relBackdrop = document.getElementById('ip-release-backdrop');
    const relModal = document.getElementById('ip-release-modal');
    const relClose = document.getElementById('ip-release-close');
    const relCancel = document.getElementById('ip-release-cancel');
    const relConfirm = document.getElementById('ip-release-confirm');

    let pendingReleaseId = null;
    const getApi = () => window.__adminApi || null;

    function openEdit(row) {
        editTitle.textContent = row ? '编辑 IP' : '添加 IP';
        editForm.reset();
        if (batchTextarea) batchTextarea.value = '';
        editForm.elements['id'].value = row && row.id ? row.id : '';
        editForm.elements['ip_address'].value = row && row.ip ? row.ip : '';
        editForm.elements['region'].value = row && row.region ? row.region : '';
        if (editForm.elements['server_id']) editForm.elements['server_id'].value = row && row.server_id ? row.server_id : '';
        editBackdrop.classList.add('show');
        editModal.classList.add('open');
    }
    function closeEdit() {
        editModal.classList.remove('open');
        setTimeout(() => editBackdrop.classList.remove('show'), 180);
    }
    function openRelease(id) {
        pendingReleaseId = id;
        relBackdrop.classList.add('show');
        relModal.classList.add('open');
    }
    function closeRelease() {
        relModal.classList.remove('open');
        setTimeout(() => relBackdrop.classList.remove('show'), 180);
        pendingReleaseId = null;
    }

    addBtn.addEventListener('click', function () {
        openEdit(null);
    });

    table.addEventListener('click', function (e) {
        const releaseBtn = e.target.closest('.ip-release');
        const deleteBtn = e.target.closest('.ip-delete');
        if (releaseBtn) {
            const id = releaseBtn.getAttribute('data-id');
            openRelease(id);
        } else if (deleteBtn) {
            const id = deleteBtn.getAttribute('data-id');
            if (!id) return;
            if (!confirm('确定删除该 IP？此操作不可恢复。')) return;
            const api = getApi();
            if (!api) {
                console.log('delete ip_pool (no api helper)', id);
                return;
            }
            api('/api/v1/ip_pool/' + id, {
                method: 'DELETE',
                body: JSON.stringify({})
            }).then(function () {
                if (window.Alpine && Alpine.store && Alpine.store('dashboard') && Alpine.store('dashboard').loadIPPool) {
                    Alpine.store('dashboard').loadIPPool();
                } else if (window.location) {
                    window.location.reload();
                }
            }).catch(function (e) {
                console.error('delete ip_pool failed', e);
                alert(e.message || '删除 IP 失败');
            });
        }
    });

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            const checked = selectAll.checked;
            document.querySelectorAll('#ip-table .ip-select').forEach(function (cb) {
                cb.checked = checked;
            });
        });
    }

    if (batchDeleteBtn) {
        batchDeleteBtn.addEventListener('click', function () {
            const checked = Array.from(document.querySelectorAll('#ip-table .ip-select:checked')).map(function (cb) {
                return parseInt(cb.value, 10);
            }).filter(function (v) { return !Number.isNaN(v); });
            if (!checked.length) {
                alert('请先勾选要删除的 IP');
                return;
            }
            if (!confirm('确定删除选中的 ' + checked.length + ' 个 IP？此操作不可恢复。')) return;
            const api = getApi();
            if (!api) {
                console.log('batch delete ip_pool (no api helper)', checked);
                return;
            }
            api('/api/v1/ip_pool/batch_delete', {
                method: 'POST',
                body: JSON.stringify({ ids: checked })
            }).then(function () {
                if (window.Alpine && Alpine.store && Alpine.store('dashboard') && Alpine.store('dashboard').loadIPPool) {
                    Alpine.store('dashboard').loadIPPool();
                } else if (window.location) {
                    window.location.reload();
                }
            }).catch(function (e) {
                console.error('batch delete ip_pool failed', e);
                alert(e.message || '批量删除 IP 失败');
            });
        });
    }

    editSave.addEventListener('click', async function () {
        const fd = new FormData(editForm);
        const data = Object.fromEntries(fd.entries());
        const region = (data.region || '').trim();
        const serverIdRaw = (data.server_id || '').trim();
        const serverId = serverIdRaw === '' ? null : parseInt(serverIdRaw, 10);
        const singleIp = (data.ip_address || '').trim();
        const batchText = (batchTextarea && batchTextarea.value ? batchTextarea.value : '').trim();

        const ips = [];
        if (batchText) {
            batchText.split('\n').map(l => l.trim()).filter(Boolean).forEach(line => {
                if (line.includes('/')) {
                    ips.push(...expandCidr(line));
                } else if (line.includes('-')) {
                    ips.push(...expandRange(line));
                } else {
                    ips.push(line);
                }
            });
        } else if (singleIp) {
            ips.push(singleIp);
        }

        if (!ips.length || !region) {
            alert('请至少填写一个 IP（单个或批量）和区域');
            return;
        }

        const api = getApi();
        if (!api) {
            console.log('save ip_pool (no api helper)', { region, ips, server_id: serverId });
            closeEdit();
            return;
        }

        try {
            // 逐条调用原 /api/v1/ip_pool 接口
            await Promise.all(ips.map(ip =>
                api('/api/v1/ip_pool', {
                    method: 'POST',
                    body: JSON.stringify({ ip_address: ip, region, server_id: serverId })
                })
            ));
        } catch (e) {
            console.error('create ip_pool batch failed', e);
            alert(e.message || '创建 IP 失败');
        }
        closeEdit();
        // 刷新当前列表，确保用户能看到新 IP
        if (window.Alpine && Alpine.store && Alpine.store('dashboard') && Alpine.store('dashboard').loadIPPool) {
            Alpine.store('dashboard').loadIPPool();
        } else if (window.location) {
            window.location.reload();
        }
    });

    // 简单的 IP/CIDR/范围 解析函数（仅支持 IPv4，/24 C 段为主）
    function ipToInt(ip) {
        const p = ip.split('.').map(x => parseInt(x, 10));
        if (p.length !== 4 || p.some(x => isNaN(x))) return null;
        return ((p[0] << 24) >>> 0) + (p[1] << 16) + (p[2] << 8) + p[3];
    }
    function intToIp(n) {
        return [(n >>> 24) & 255, (n >>> 16) & 255, (n >>> 8) & 255, n & 255].join('.');
    }
    function expandCidr(cidr) {
        const [ip, maskStr] = cidr.split('/');
        const mask = parseInt(maskStr, 10);
        if (!ip || isNaN(mask) || mask < 24 || mask > 32) return [cidr]; // 保守处理，只展开 /24–/32
        const base = ipToInt(ip);
        if (base == null) return [cidr];
        const hostBits = 32 - mask;
        const count = 1 << hostBits;
        const res = [];
        for (let i = 0; i < count; i++) {
            res.push(intToIp(base + i));
        }
        return res;
    }
    function expandRange(line) {
        const [start, end] = line.split('-').map(s => s.trim());
        const sInt = ipToInt(start);
        const eInt = ipToInt(end);
        if (sInt == null || eInt == null || eInt < sInt) return [line];
        const res = [];
        for (let i = sInt; i <= eInt; i++) {
            res.push(intToIp(i));
        }
        return res;
    }
    editClose.addEventListener('click', closeEdit);
    editCancel.addEventListener('click', closeEdit);
    // 遮罩点击不关闭

    relConfirm.addEventListener('click', async function () {
        if (pendingReleaseId == null) return;
        const api = getApi();
        if (!api) {
            console.log('release ip_pool (no api helper)', pendingReleaseId);
            closeRelease();
            return;
        }
        try {
            await api('/api/v1/ip_pool/' + pendingReleaseId + '/release', {
                method: 'POST',
                body: JSON.stringify({})
            });
            console.log('released ip_pool', pendingReleaseId);
        } catch (e) {
            console.error('release ip_pool failed', e);
            alert(e.message || '释放 IP 失败');
        }
        closeRelease();
    });
    relClose.addEventListener('click', closeRelease);
    relCancel.addEventListener('click', closeRelease);
    // 遮罩点击不关闭
})();
</script>
@endpush
@endsection
