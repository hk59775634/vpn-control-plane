@extends('layouts.app')

@section('title', '用户中心')

@section('body')
<div class="min-h-screen bg-slate-50">
    <header class="border-b border-slate-200 bg-white shadow-sm">
        <div class="mx-auto flex max-w-4xl items-center justify-between px-4 py-4 sm:px-6">
            <a href="{{ url('/') }}" class="text-lg font-semibold tracking-tight text-slate-900">VPN SaaS</a>
            <span class="text-sm text-slate-500">用户中心</span>
        </div>
    </header>
    <main class="mx-auto max-w-4xl px-4 py-10 sm:px-6">
        <div class="page-section">
            <h1 class="page-title">欢迎</h1>
            <p class="page-desc">这是 A 站用户中心入口。订阅、设备与流量等功能可在此扩展。</p>
        </div>
        <div class="console-card p-6 sm:p-8">
            <div class="console-alert-info">
                <p class="font-medium text-slate-900">提示</p>
                <p class="mt-1 text-sm leading-relaxed text-sky-900/90">
                    若使用分销商 B 站购买服务，请在 B 站用户前台登录与续费；本页为 A 站预留扩展位。
                </p>
            </div>
            <p class="mt-6 text-sm text-slate-600">
                需要管理后台请使用
                <a href="{{ url('/admin/login') }}" class="console-link font-medium">管理员登录</a>
                ；分销商请前往
                <a href="{{ url('/reseller/login') }}" class="console-link font-medium">分销商门户</a>。
            </p>
        </div>
    </main>
</div>
@endsection
