<?php

return [

    /*
    |--------------------------------------------------------------------------
    | 彩虹易支付 / 易支付 V1（MD5）
    |--------------------------------------------------------------------------
    | 网关通常为：https://你的域名/submit.php ，此处填写站点根 URL（不含路径）。
    */

    'enabled' => (bool) env('EPAY_ENABLED', false),

    /** 例如：https://pay.example.com */
    'gateway' => rtrim((string) env('EPAY_GATEWAY', ''), '/'),

    /** 商户 ID（pid） */
    'pid' => env('EPAY_PID', ''),

    /** V1 MD5 密钥 */
    'key' => env('EPAY_KEY', ''),

    /** 支付后浏览器跳转（同步） */
    'return_url' => env('EPAY_RETURN_URL'),

    /**
     * 异步通知 URL；留空则使用 APP_URL + /api/v1/payments/epay/notify
     */
    'notify_url' => env('EPAY_NOTIFY_URL'),

    /** 是否允许分销商后台「模拟充值」（仅开发/内测建议开启） */
    'allow_simulated_recharge' => (bool) env('EPAY_ALLOW_SIMULATED_RECHARGE', true),

];
