"""
公共配置：从环境变量读取控制面地址、NAT 网卡、是否跳过系统命令等。
与 1.0 Go Agent 环境变量保持一致。
"""
from __future__ import annotations

import os


def get_control_plane_addr() -> str:
    return os.environ.get("CONTROL_PLANE_ADDR", "localhost:9090")


def get_control_plane_http_base() -> str:
    base = os.environ.get("CONTROL_PLANE_HTTP_BASE", "http://127.0.0.1:8000")
    return base.rstrip("/")


def get_agent_register_path() -> str:
    """首次注册 URL 路径。默认 /api/v1/agent/bootstrap（避免 /register 被 CDN/WAF 拦截）。"""
    p = os.environ.get("AGENT_REGISTER_PATH", "/api/v1/agent/bootstrap").strip()
    if not p.startswith("/"):
        p = "/" + p
    return p or "/api/v1/agent/bootstrap"


def get_node_nat_interface() -> str:
    return os.environ.get("NODE_NAT_INTERFACE", "eth0")


def get_bandwidth_interface() -> str:
    return os.environ.get("NODE_BANDWIDTH_INTERFACE", "eth0")


def skip_system_commands() -> bool:
    return os.environ.get("SKIP_SYSTEM_COMMANDS", "") == "1"


def get_antiblock_state_file() -> str:
    if path := os.environ.get("ANTIBLOCK_STATE_FILE"):
        return path
    home = os.environ.get("HOME", "")
    if not home:
        return "antiblock.state"
    return os.path.join(home, ".vpn-node", "antiblock.state")


def get_node_server_id() -> int:
    raw = os.environ.get("NODE_SERVER_ID", "0").strip()
    try:
        return int(raw)
    except ValueError:
        return 0


def get_agent_bootstrap_token() -> str:
    return os.environ.get("AGENT_BOOTSTRAP_TOKEN", "").strip()


def get_agent_token_file() -> str:
    default_path = os.path.join(os.environ.get("HOME", "."), ".vpn-node", "agent.token")
    return os.environ.get("AGENT_TOKEN_FILE", default_path)


def get_config_revision_ts() -> int:
    """与 A 站 servers.config_revision_ts 对齐，由 /etc/vpn-node/agent.env 的 CONFIG_REVISION_TS 注入。"""
    raw = os.environ.get("CONFIG_REVISION_TS", "0").strip()
    try:
        return max(0, int(raw))
    except ValueError:
        return 0


def refresh_config_revision_ts_from_env_file() -> None:
    """从 agent.env 重新加载 CONFIG_REVISION_TS（覆盖进程内环境）。

    部署脚本更新 /etc/vpn-node/agent.env 后，若进程未及时重启，仍可在下一次心跳上报最新修订号。
    """
    path = os.environ.get("AGENT_ENV_FILE", "/etc/vpn-node/agent.env")
    if not os.path.isfile(path):
        return
    try:
        with open(path, encoding="utf-8-sig") as f:
            for line in f:
                line = line.strip()
                if not line or line.startswith("#") or "=" not in line:
                    continue
                key, val = line.split("=", 1)
                key = key.strip()
                if key != "CONFIG_REVISION_TS":
                    continue
                val = val.strip()
                if len(val) >= 2 and val[0] == val[-1] and val[0] in "\"'":
                    val = val[1:-1]
                os.environ["CONFIG_REVISION_TS"] = val
                return
    except OSError:
        return


def get_heartbeat_interval_sec() -> int:
    raw = os.environ.get("HEARTBEAT_INTERVAL_SEC", "15").strip()
    try:
        val = int(raw)
    except ValueError:
        val = 15
    return max(3, min(120, val))


def get_link_tunnel_type() -> str:
    return os.environ.get("NODE_LINK_TUNNEL_TYPE", "").strip().lower()


def get_link_iface() -> str:
    return os.environ.get("NODE_LINK_IFACE", "").strip()


def get_link_local_ip() -> str:
    return os.environ.get("NODE_LINK_LOCAL_IP", "").strip()


def get_link_remote_ip() -> str:
    return os.environ.get("NODE_LINK_REMOTE_IP", "").strip()


def get_link_wg_private_key() -> str:
    return os.environ.get("NODE_LINK_WG_PRIVATE_KEY", "").strip()


def get_link_wg_peer_public_key() -> str:
    return os.environ.get("NODE_LINK_WG_PEER_PUBLIC_KEY", "").strip()


def get_link_wg_endpoint() -> str:
    return os.environ.get("NODE_LINK_WG_ENDPOINT", "").strip()


def get_link_wg_allowed_ips() -> str:
    return os.environ.get("NODE_LINK_WG_ALLOWED_IPS", "").strip()


def get_node_protocol() -> str:
    return os.environ.get("NODE_PROTOCOL", "").strip().lower()


def get_occtl_socket() -> str:
    """与 ocserv.conf 中 occtl-socket-file 一致；未设置时尝试默认 /var/run/occtl.socket。"""
    return os.environ.get("NODE_OCCTL_SOCKET", "").strip()


def get_node_nat_topology() -> str:
    return os.environ.get("NODE_NAT_TOPOLOGY", "combined").strip().lower()


def get_wg_interface() -> str:
    return os.environ.get("NODE_WG_INTERFACE", "wg0").strip() or "wg0"


def get_vpn_ip_cidrs() -> str:
    return os.environ.get("NODE_VPN_IP_CIDRS", "").strip()


def get_node_wg_dns() -> str:
    return os.environ.get("NODE_WG_DNS", "1.1.1.1").strip() or "1.1.1.1"


def get_ocserv_radius_host() -> str:
    return os.environ.get("NODE_OCSERV_RADIUS_HOST", "").strip()


def get_ocserv_radius_auth_port() -> int:
    raw = os.environ.get("NODE_OCSERV_RADIUS_AUTH_PORT", "1812").strip()
    try:
        return int(raw)
    except ValueError:
        return 1812


def get_ocserv_radius_acct_port() -> int:
    raw = os.environ.get("NODE_OCSERV_RADIUS_ACCT_PORT", "1813").strip()
    try:
        return int(raw)
    except ValueError:
        return 1813


def get_ocserv_radius_secret() -> str:
    import base64

    raw = os.environ.get("NODE_OCSERV_RADIUS_SECRET_B64", "").strip()
    if not raw:
        return ""
    try:
        return base64.b64decode(raw.encode("ascii"), validate=True).decode("utf-8")
    except Exception:
        return ""


def get_ocserv_port() -> int:
    raw = os.environ.get("NODE_OCSERV_PORT", "443").strip()
    try:
        p = int(raw)
    except ValueError:
        p = 443
    return max(1, min(65535, p))


def get_ocserv_domain() -> str:
    return os.environ.get("NODE_OCSERV_DOMAIN", "").strip()


def get_ocserv_tls_pem_from_env() -> tuple[str, str]:
    """返回 (cert_pem, key_pem)，失败为 ("", "")。"""
    import base64

    c = os.environ.get("NODE_OCSERV_TLS_CERT_B64", "").strip()
    k = os.environ.get("NODE_OCSERV_TLS_KEY_B64", "").strip()
    try:
        cert = base64.b64decode(c.encode("ascii"), validate=True).decode("utf-8") if c else ""
        key = base64.b64decode(k.encode("ascii"), validate=True).decode("utf-8") if k else ""
        return (cert, key)
    except Exception:
        return ("", "")
