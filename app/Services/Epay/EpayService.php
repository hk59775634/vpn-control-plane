<?php

namespace App\Services\Epay;

use App\Support\PaymentConfig;

/**
 * 彩虹易支付 V1：MD5 签名（与多数易支付 fork 一致）
 *
 * @see https://github.com/QuantumNous/new-api 等项目的易支付对接思路
 */
class EpayService
{
    public function __construct(
        protected string $gatewayBase,
        protected string $pid,
        protected string $key,
    ) {}

    public static function fromConfig(): ?self
    {
        $base = PaymentConfig::gateway();
        $pid = PaymentConfig::pid();
        $key = PaymentConfig::key();

        if ($base === '' || $pid === '' || $key === '') {
            return null;
        }

        return new self($base, $pid, $key);
    }

    public function isConfigured(): bool
    {
        return $this->gatewayBase !== '' && $this->pid !== '' && $this->key !== '';
    }

    /**
     * 参与签名的参数：排除 sign、sign_type，且空值不参与（与官方说明一致）
     *
     * @param  array<string, scalar|null>  $data
     */
    public function sign(array $data): string
    {
        $filtered = [];
        foreach ($data as $k => $v) {
            if ($k === 'sign' || $k === 'sign_type') {
                continue;
            }
            if ($v === '' || $v === null) {
                continue;
            }
            $filtered[(string) $k] = (string) $v;
        }
        ksort($filtered);
        $pairs = [];
        foreach ($filtered as $k => $v) {
            $pairs[] = $k.'='.$v;
        }

        return md5(implode('&', $pairs).$this->key);
    }

    public function verifySign(array $data): bool
    {
        $received = $data['sign'] ?? '';
        if ($received === '') {
            return false;
        }
        $calc = $this->sign($data);

        return hash_equals(strtolower((string) $calc), strtolower((string) $received));
    }

    /**
     * @param  array<string, scalar|null>  $extra  可含 type、param（非空才会参与签名）
     * @return array<string, string>
     */
    public function buildSubmitParams(
        string $outTradeNo,
        string $name,
        string $money,
        string $notifyUrl,
        string $returnUrl,
        array $extra = [],
    ): array {
        $data = [
            'pid' => $this->pid,
            'out_trade_no' => $outTradeNo,
            'notify_url' => $notifyUrl,
            'return_url' => $returnUrl,
            'name' => $name,
            'money' => $money,
            'sign_type' => 'MD5',
        ];
        foreach (['type', 'param'] as $k) {
            if (!empty($extra[$k])) {
                $data[$k] = (string) $extra[$k];
            }
        }
        $data['sign'] = $this->sign($data);

        /** @var array<string, string> $out */
        $out = array_map(static fn ($v) => (string) $v, $data);

        return $out;
    }

    public function submitUrl(): string
    {
        return $this->gatewayBase.'/submit.php';
    }

    public function buildPayUrl(
        string $outTradeNo,
        string $name,
        string $money,
        string $notifyUrl,
        string $returnUrl,
        array $extra = [],
    ): string {
        $params = $this->buildSubmitParams($outTradeNo, $name, $money, $notifyUrl, $returnUrl, $extra);

        return $this->submitUrl().'?'.http_build_query($params);
    }

    public function pidMatches(string|int|null $pid): bool
    {
        return (string) $pid === (string) $this->pid;
    }
}
