# Python 节点 Agent（A 站打包源）

更新时间：**2026-03-25**

本目录为 **A 站 SSH/安装包** 使用的 Agent 源码根（与仓库 **`2.0/agent/`** 保持同步）。

## 与控制面交互（当前实现）

- 注册：`POST /api/v1/agent/bootstrap`
- 心跳：`POST /api/v1/agent/heartbeat`
  - 上报：`metrics`、可选 `wg_peer_transfer`
  - 接收：`commands` + `policy`
- 回执：`POST /api/v1/agent/commands/ack`

## 策略落地（当前实现）

- `combined_nat`：一体拓扑 NAT（`sysctl ip_forward`、FORWARD、MASQUERADE，可持久化）。
- `wg_shaping`：按 WireGuard peer 内网 IP 的对称限速（tc）。
- `wg_remove_peer_public_keys`：超额用户剔除 peer（强制下线）。

## 同步关系

- 建议以 **`2.0/php/A/agent`** 为发布源。
- 目录 **`2.0/agent`** 为镜像副本，更新后需保持同步。

通用说明仍见 **[../../../agent/README.md](../../../agent/README.md)**。
