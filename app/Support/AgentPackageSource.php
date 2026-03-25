<?php

namespace App\Support;

/**
 * SSH 下发与 HTTP 打包下载共用的 Agent 源码根目录（须含 run_agent.py）。
 */
final class AgentPackageSource
{
    /**
     * @throws \RuntimeException
     */
    public static function resolvePath(): string
    {
        $configured = trim((string) config('app.agent_source_path', ''));
        if ($configured !== '') {
            $real = realpath($configured);
            if ($real !== false && is_dir($real) && is_file($real.'/run_agent.py')) {
                return $real;
            }
            throw new \RuntimeException('AGENT_SOURCE_PATH 无效或缺少 run_agent.py：'.$configured);
        }

        $candidates = [
            base_path('agent'),
            base_path('../../agent'),
            base_path('../agent'),
        ];
        foreach ($candidates as $rel) {
            $real = realpath($rel);
            if ($real !== false && is_dir($real) && is_file($real.'/run_agent.py')) {
                return $real;
            }
        }

        throw new \RuntimeException(
            '未找到 Agent 源码目录。请在 .env 设置 AGENT_SOURCE_PATH=/绝对路径/agent（须含 run_agent.py），'
            .'或将 agent 放在 Laravel 根下 agent/，或 Monorepo 上级 ../agent/、上两级 ../../agent/。当前已尝试：'
            .implode(', ', $candidates)
        );
    }
}
