<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Support\PaymentConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

class AdminPaymentSettingsController extends Controller
{
    public function show(): JsonResponse
    {
        $keySet = PaymentConfig::keyIsSetInDatabase() || (string) config('epay.key', '') !== '';
        $plainKey = PaymentConfig::key();
        $hint = '';
        if ($plainKey !== '') {
            $hint = strlen($plainKey) <= 8 ? '********' : ('…'.substr($plainKey, -4));
        }

        return response()->json([
            'epay_enabled' => PaymentConfig::enabled(),
            'epay_gateway' => PaymentConfig::gateway(),
            'epay_pid' => PaymentConfig::pid(),
            'epay_key_set' => $keySet,
            'epay_key_hint' => $hint,
            'epay_notify_url' => $this->rawNotifyUrlForForm(),
            'epay_return_url' => $this->rawReturnUrlForForm(),
            'epay_allow_simulated_recharge' => PaymentConfig::allowSimulatedRecharge(),
            'epay_notify_url_effective' => PaymentConfig::notifyUrl(),
            'epay_return_url_effective' => PaymentConfig::returnUrl(),
        ]);
    }

    /**
     * 表单展示：若库中未单独配置则返回空字符串（表示使用默认/环境变量）
     */
    private function rawNotifyUrlForForm(): string
    {
        $db = SiteSetting::getValue(PaymentConfig::K_NOTIFY_URL);
        if ($db !== null) {
            return $db;
        }

        return '';
    }

    private function rawReturnUrlForForm(): string
    {
        $db = SiteSetting::getValue(PaymentConfig::K_RETURN_URL);
        if ($db !== null) {
            return $db;
        }

        return '';
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'epay_enabled' => ['required', 'boolean'],
            'epay_gateway' => ['nullable', 'string', 'max:512'],
            'epay_pid' => ['nullable', 'string', 'max:64'],
            'epay_key' => ['nullable', 'string', 'max:512'],
            'epay_notify_url' => ['nullable', 'string', 'max:1024'],
            'epay_return_url' => ['nullable', 'string', 'max:1024'],
            'epay_allow_simulated_recharge' => ['required', 'boolean'],
        ]);

        $gwIn = trim((string) ($data['epay_gateway'] ?? ''));
        $pidIn = trim((string) ($data['epay_pid'] ?? ''));
        if ($gwIn !== '' && !filter_var($gwIn, FILTER_VALIDATE_URL)) {
            throw ValidationException::withMessages([
                'epay_gateway' => ['API 地址应为合法 URL（例如 https://pay.example.com）'],
            ]);
        }

        if ($data['epay_enabled']) {
            if ($gwIn === '' || $pidIn === '') {
                throw ValidationException::withMessages([
                    'epay_gateway' => ['开启在线支付时，请填写 API 地址与商户 ID'],
                ]);
            }
            $hasKey = ($data['epay_key'] ?? '') !== '' || PaymentConfig::keyIsSetInDatabase() || (string) config('epay.key', '') !== '';
            if (!$hasKey) {
                throw ValidationException::withMessages([
                    'epay_key' => ['开启在线支付时，请填写 MD5 密钥（或已在数据库/环境变量中配置过）'],
                ]);
            }
        }

        SiteSetting::setValue(PaymentConfig::K_ENABLED, $data['epay_enabled'] ? '1' : '0');
        SiteSetting::setValue(PaymentConfig::K_ALLOW_SIMULATED, $data['epay_allow_simulated_recharge'] ? '1' : '0');

        if ($gwIn === '') {
            SiteSetting::deleteKey(PaymentConfig::K_GATEWAY);
        } else {
            SiteSetting::setValue(PaymentConfig::K_GATEWAY, rtrim($gwIn, '/'));
        }
        if ($pidIn === '') {
            SiteSetting::deleteKey(PaymentConfig::K_PID);
        } else {
            SiteSetting::setValue(PaymentConfig::K_PID, $pidIn);
        }

        if (!empty($data['epay_key'])) {
            SiteSetting::setValue(PaymentConfig::K_KEY, Crypt::encryptString($data['epay_key']));
        }

        $notify = trim((string) ($data['epay_notify_url'] ?? ''));
        if ($notify === '') {
            SiteSetting::deleteKey(PaymentConfig::K_NOTIFY_URL);
        } else {
            if (!filter_var($notify, FILTER_VALIDATE_URL)) {
                throw ValidationException::withMessages([
                    'epay_notify_url' => ['异步通知地址应为合法 URL'],
                ]);
            }
            SiteSetting::setValue(PaymentConfig::K_NOTIFY_URL, $notify);
        }

        $ret = trim((string) ($data['epay_return_url'] ?? ''));
        if ($ret === '') {
            SiteSetting::deleteKey(PaymentConfig::K_RETURN_URL);
        } else {
            if (!filter_var($ret, FILTER_VALIDATE_URL)) {
                throw ValidationException::withMessages([
                    'epay_return_url' => ['同步跳转地址应为合法 URL'],
                ]);
            }
            SiteSetting::setValue(PaymentConfig::K_RETURN_URL, $ret);
        }

        return $this->show();
    }
}
