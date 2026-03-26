# Python 节点 Agent（A 站打包源）

更新时间：**2026-03-25**

本目录为 **A 站 SSH/安装包** 使用的 Agent 源码根（本仓库路径 **`sites/A/agent`**；与历史 monorepo 路径 **`2.0/agent`** 或 **`2.0/php/A/agent`** 为同源关系）。

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

- **发布与打包**：以本目录（A 站 Laravel 根下的 **`agent/`**）为准。
- 若你仍在使用旧 monorepo 布局，**`2.0/php/A/agent`** 与 **`2.0/agent`** 应与此处保持内容一致。

通用说明仍见仓库根目录 **[../../../README.md](../../../README.md)**。
