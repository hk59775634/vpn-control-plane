<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reseller;
use App\Models\ResellerBalanceTransaction;
use App\Models\ResellerPaymentOrder;
use App\Services\Epay\EpayService;
use App\Support\PaymentConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * 易支付异步通知（公开接口，靠 MD5 签名校验）
 */
class EpayNotifyController extends Controller
{
    public function notify(Request $request): Response
    {
        if (!PaymentConfig::enabled()) {
            return $this->plainFail('epay disabled');
        }

        $service = EpayService::fromConfig();
        if ($service === null || !$service->isConfigured()) {
            return $this->plainFail('epay not configured');
        }

        $params = array_merge($request->query->all(), $request->request->all());

        if (!$service->verifySign($params)) {
            Log::warning('epay notify: bad sign', ['keys' => array_keys($params)]);

            return $this->plainFail('sign');
        }

        $pid = $params['pid'] ?? '';
        if (!$service->pidMatches($pid)) {
            Log::warning('epay notify: pid mismatch', ['pid' => $pid]);

            return $this->plainFail('pid');
        }

        $outTradeNo = (string) ($params['out_trade_no'] ?? '');
        if ($outTradeNo === '') {
            return $this->plainFail('out_trade_no');
        }

        $tradeStatus = strtoupper((string) ($params['trade_status'] ?? ''));
        if (!in_array($tradeStatus, ['TRADE_SUCCESS', 'TRADE_FINISHED'], true)) {
            return $this->plainFail('status');
        }

        $moneyStr = (string) ($params['money'] ?? '');
        if ($moneyStr === '' || !is_numeric($moneyStr)) {
            return $this->plainFail('money');
        }

        $moneyCents = (int) round((float) $moneyStr * 100);

        $tradeNo = (string) ($params['trade_no'] ?? '');

        try {
            DB::transaction(function () use ($outTradeNo, $moneyCents, $tradeNo, $params, $service): void {
                /** @var ResellerPaymentOrder|null $order */
                $order = ResellerPaymentOrder::query()
                    ->where('out_trade_no', $outTradeNo)
                    ->lockForUpdate()
                    ->first();

                if (!$order) {
                    throw new \RuntimeException('order_not_found');
                }

                if ($order->status === 'paid') {
                    return;
                }

                if ($order->amount_cents !== $moneyCents) {
                    Log::warning('epay notify: amount mismatch', [
                        'out_trade_no' => $outTradeNo,
                        'expected_cents' => $order->amount_cents,
                        'got_cents' => $moneyCents,
                    ]);
                    throw new \RuntimeException('amount_mismatch');
                }

                $locked = Reseller::whereKey($order->reseller_id)->lockForUpdate()->first();
                if (!$locked) {
                    throw new \RuntimeException('reseller_missing');
                }

                $locked->balance_cents = (int) $locked->balance_cents + $order->amount_cents;
                $locked->save();

                ResellerBalanceTransaction::create([
                    'reseller_id' => $locked->id,
                    'amount_cents' => $order->amount_cents,
                    'balance_after_cents' => (int) $locked->balance_cents,
                    'type' => 'recharge',
                    'meta' => array_filter([
                        'channel' => 'epay',
                        'out_trade_no' => $order->out_trade_no,
                        'trade_no' => $tradeNo !== '' ? $tradeNo : null,
                        'pay_type' => $params['type'] ?? null,
                        'note' => $order->meta['note'] ?? null,
                    ]),
                ]);

                $order->status = 'paid';
                $order->trade_no = $tradeNo !== '' ? $tradeNo : $order->trade_no;
                $order->pay_type = isset($params['type']) ? (string) $params['type'] : $order->pay_type;
                $order->meta = array_merge($order->meta ?? [], [
                    'notify_at' => now()->toIso8601String(),
                    'notify_raw' => $params,
                ]);
                $order->save();
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'order_not_found') {
                return $this->plainFail('order');
            }
            if ($e->getMessage() === 'amount_mismatch') {
                return $this->plainFail('amount');
            }

            return $this->plainFail('error');
        } catch (\Throwable $e) {
            Log::error('epay notify: '.$e->getMessage(), ['exception' => $e]);

            return $this->plainFail('error');
        }

        return response('success', 200)->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    private function plainFail(string $reason): Response
    {
        Log::info('epay notify fail', ['reason' => $reason]);

        return response('fail', 200)->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
