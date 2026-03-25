# vpn-control-plane（A 站）

> **GitHub 仓库描述（Description）建议填写：** `A 站控制面 — Laravel 管理后台与 API，VPN SaaS 控制面。`

独立 Laravel 应用：管理后台、分销商/订单/产品与对外 API。**单独部署**到一台服务器，Web 根目录指向 `public/`。  
更新时间：**2026-03-25**（按当前仓库实现同步）。

## 功能概览

| 模块 | 路径 / 说明 |
|------|-------------|
| 管理后台 | `/admin` — Blade + Tailwind + Alpine.js |
| 管理员登录 | `/admin/login` |
| 健康检查 | `GET /api/health` |
| REST API | `/api/v1/*`（管理员 Sanctum、分销商 Bearer API Key 等） |

根路径 `/` 重定向到 `/admin`。

技术栈：PHP 8.2+、Laravel、MySQL（推荐）或 SQLite、Vite 前端资源。

## 当前落地能力（实装）

- **Agent 控制面**：`/api/v1/agent/bootstrap`、`/api/v1/agent/heartbeat`、`/api/v1/agent/commands/ack`、安装清单/打包下载。
- **心跳策略 `policy`**：
  - 一体拓扑 NAT：`ip_forward` + `iptables`（可持久化）；
  - WireGuard 按用户限速：`tc`（peer 内网 IP 维度）；
  - 流量超额：下发剔除 peer。
- **产品限额**：`bandwidth_limit_kbps`、`traffic_quota_bytes`（留空=不限）。
- **流量入账**：Agent 上报 `wg_peer_transfer`，A 站写入 `traffic_logs`。
- **SNAT / 独立公网 IP**：IP 池分配、SNAT 映射、审计日志与管理端查询。
- **OCServ**：节点侧支持自动部署（`protocol=ocserv` 时）。

### FreeRADIUS / Redis（可选）

- 迁移包含 **`radcheck`** 等表；订单与 **`vpn_users`** 变更会通过 **`FreeradiusSyncService`** 同步。
- 启用 Redis 认证缓存：`.env` 中 **`RADIUS_REDIS_AUTH_ENABLED=true`**（及 `REDIS_*`）；全量回填：**`php artisan radius:sync-cache`**。
- 与 A 站同机部署 FreeRADIUS：**仓库根目录** **`infra/scripts/setup-freeradius-a-sql-redis.sh`**，说明见 **`infra/docs/OCSERV_FREERADIUS.md`**。

### 节点 Agent

- 源码目录：**`agent/`**（与 monorepo **`2.0/agent/`** 同步）；打包安装见 **`AGENT_BOOTSTRAP_TOKEN`**、**`GET /api/v1/agent/package`**。
- 心跳返回 **`policy`**（NAT / WG 限速 / 剔除 peer）；详见 **`agent/README.md`**。

### 推送到 GitHub（单仓 A 站时）

见 **`docs/GITHUB_PUSH.md`**。若整仓为 monorepo（含 `2.0/`），请使用仓库根目录 **`GITHUB_PUSH.md`**。

### 当前整仓地址

- Monorepo：**https://github.com/hk59775634/vpn1**

---

## 部署要求

- PHP ≥ 8.2，扩展：`pdo`、`mbstring`、`openssl`、`tokenizer`、`xml`、`ctype`、`json`、`bcmath`（按 `composer.json` 与 Laravel 要求）
- Composer 2.x
- Node.js 20+ 与 npm（用于构建前端）
- MySQL 8+ / MariaDB（生产推荐）或 SQLite（开发）

---

## 部署步骤（生产）

### 1. 获取代码

```bash
git clone https://github.com/<你的用户名>/vpn-control-plane.git
cd vpn-control-plane
```

### 2. 环境变量

```bash
cp .env.example .env
php artisan key:generate
```

编辑 `.env`（至少）：

- `APP_NAME`、`APP_URL`（**HTTPS 公网域名**，无末尾斜杠）
- `APP_ENV=production`、`APP_DEBUG=false`
- `DB_*`：数据库连接（当前模板为 MySQL 示例）
- 若使用易支付：配置 `EPAY_*`（或在管理后台「支付设置」中保存，数据库优先）

**切勿**将真实 `.env` 提交到 Git。

### 3. 依赖与数据库

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
```

创建数据库并授权后：

```bash
php artisan migrate --force
php artisan db:seed --force
```

默认管理员账号（以 `DatabaseSeeder` 为准，一般为：**admin@example.com** / **admin123**）。**首次登录后请立即修改密码。**

### 4. 目录权限

```bash
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache
```

（用户/组按实际 Web 进程调整。）

### 5. Web 服务器

- **Nginx / Apache** 文档根指向项目下的 `public/`。
- 配置 `try_files` + `index.php` 转发（标准 Laravel）。
- HTTPS 证书；`APP_URL` 与对外访问域名一致。

### 6. 定时任务（可选）

若使用队列、调度，配置 `cron`：

```cron
* * * * * cd /path/to/vpn-control-plane && php artisan schedule:run >> /dev/null 2>&1
```

### 7. 队列常驻（推荐）

生产环境建议使用 `systemd` 常驻运行队列与调度器：

```bash
# 按需修改 WorkingDirectory/User 后安装
sudo cp deploy/systemd/vpn-a-queue-worker.service /etc/systemd/system/
sudo cp deploy/systemd/vpn-a-scheduler.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now vpn-a-queue-worker.service
sudo systemctl enable --now vpn-a-scheduler.service
sudo systemctl status vpn-a-queue-worker.service --no-pager
```

队列 worker 命令为：

```bash
php artisan queue:work --queue=maintenance,default
```

### 8. Agent 一键 SSH 部署依赖

若需要在后台点击“部署Agent”自动通过 SSH 下发，A 站机器需安装：

```bash
sudo apt-get update
sudo apt-get install -y sshpass openssh-client
```

并在“接入服务器”中填写：
- `host`（目标 IP/域名）
- `ssh_user`
- `ssh_password`
- **NAT 拓扑**（一体 / 分体·接入 / 分体·NAT）与网卡角色（见下节）
- `node_nat_interface` / `node_bandwidth_interface`（未填写 HK 网卡时的兜底；分体接入侧建议与互联口一致）

### 9. 接入与 NAT 拓扑（一体 / 分体）

同一业务区域（如 CN-HK）常见两种部署：

1. **一体**：单台机器兼接入与 NAT。典型：`eth0`= CN 公网（用户接入面），`eth1`= HK 公网（出口 NAT 面）。在后台将该机 `nat_topology` 设为 **一体**，并填写 `cn_public_iface` / `hk_public_iface`。
2. **分体**：两台机器。接入机：`eth0`= CN 公网，`eth1`= 与 NAT 机互联（内网专线、静态路由、WireGuard 隧道等均可）。NAT 机：`eth0`= HK 公网，`eth1`= 与接入机互联。在后台分别为两台 `servers` 记录设置 **分体·接入** / **分体·NAT**，填写互联网卡与本端/对端互联 IP（可选），并用 `paired_server_id` 指向对端 `servers.id`。

「NAT 服务器」表中的 `exit_nodes` 行表示 **终端配置里可见的出口地址**（一般为 HK 公网 IP），可额外填写 `public_iface` 与 `notes` 说明该 IP 落在哪块网卡。

Agent 通过 SSH 安装时，会按 `nat_topology` 推断默认的 `NODE_NAT_INTERFACE` / `NODE_BANDWIDTH_INTERFACE`（一体与 NAT 侧重 HK 口；接入分体重在 `node_nat_interface` 与互联口）。

### 10. 独立公网 IP、IP 池、SNAT、OCServ 与管理端审计

- **产品**：`products.requires_dedicated_public_ip` 为真时，分销商开通必须带 **`region`**，且该区域需存在启用 **`split_nat_multi_public_ip_enabled`** 的接入服务器；控制面从 **`ip_pool`** 分配公网 IP，并可按 **`ip_pool.server_id`** 限定到具体接入机池。
- **WireGuard**：SNAT 的 `source_ip` 为隧道内分配地址；**RADIUS / OCServ**：当前实现以 **`Framed-IP-Address`**（与下发到 `radreply` 的地址一致）作为 NAT 侧 SNAT 的源地址，便于与开通流程对齐、无需在线会话探测。
- **OCServ 与真实源地址**：若部署中客户端经 OCServ 出站时，在 NAT 机上看到的源 IP **与 RADIUS 下发的 `Framed-IP-Address` 不一致**（例如中间再做了一层地址转换），则现有 SNAT 规则可能对不上。此时需在 RADIUS/OCServ/接入链路上对齐地址，或扩展控制面支持「按会话/探测得到的真实源 IP」下发（需按现网抓包或 Accounting 验证）。
- **管理端 API（Sanctum 管理员登录态）**  
  - `GET /api/v1/admin/snat_maps`：分页与筛选。查询参数示例：`page`、`per_page`（默认 50，最大 200）、`status`（`active` / `released`）、`vpn_user_id`、`server_id`、`q`（匹配用户邮箱、`source_ip`、`public_ip`）。响应：`{ "data": [...], "total", "page", "per_page" }`。  
  - `GET /api/v1/admin/provision_audit_logs`：`page`、`per_page`、`event`、`vpn_user_id`、`order_id`。记录分销商开通导致的 **`ip_pool_bind`**、**`snat_applied`**、**`snat_replaced`**，以及后台释放池 IP 时的 **`ip_pool_admin_release`**、移除 SNAT 时的 **`snat_admin_remove`**（`meta` 为 JSON 详情）。
- **数据表**：`provision_resource_audit_logs`（迁移自动创建）。后台菜单 **资源审计** 与 **SNAT 映射表** 已对接上述接口。

---

## 接口说明（摘要）

- 成功响应：`{ "success": true, "code", "message", "data" }`
- 失败响应：`{ "success": false, "code", "message", "data" }`
- 易支付等回调可能返回纯文本 `success` / `fail`，以支付平台约定为准。

---

## 本地开发

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
npm install && npm run build
php artisan serve
```

访问：`http://127.0.0.1:8000/admin`

---

## 与 B 站的关系

A 站为「控制面」：B 站（`vpn-saas-reseller`）通过 `VPN_A_URL` + 分销商 API Key 调用本仓库提供的 API（校验 Key、开通订单、公开产品等）。部署 B 站前需先部署 A 站并创建分销商与 API Key。
