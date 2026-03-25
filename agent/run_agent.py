#!/usr/bin/env python3
"""节点 Agent 入口：HTTP register + heartbeat + command ack MVP。"""
import logging
import os
import sys
import json
import time
import urllib.request
import urllib.error

# 保证可导入 common、vpn、nat、bandwidth、antiblock
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from common.agent_version import get_agent_version
from common.config import (
    get_agent_bootstrap_token,
    get_agent_token_file,
    get_bandwidth_interface,
    get_control_plane_http_base,
    get_config_revision_ts,
    get_heartbeat_interval_sec,
    get_node_nat_interface,
    get_link_tunnel_type,
    get_link_iface,
    get_link_local_ip,
    get_link_remote_ip,
    get_link_wg_private_key,
    get_link_wg_peer_public_key,
    get_link_wg_endpoint,
    get_link_wg_allowed_ips,
    get_node_server_id,
    get_agent_register_path,
    get_node_protocol,
    get_wg_interface,
    skip_system_commands,
)
from common.metrics import get_system_metrics
from antiblock.state_file import apply_antiblock_policy
from bandwidth.tc_limit import apply_bandwidth_limits
from nat.iptables_nat import apply_nat_rules
from nat.snat_map import apply_snat_mapping, remove_snat_mapping
from net.link_iface import ensure_link_interface
from vpn.ocserv import ensure_ocserv_deployed
from vpn.wg_peer import collect_wg_peer_transfer
from policy.apply import apply_heartbeat_policy

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)


def load_agent_env_file() -> None:
    """从 /etc/vpn-node/agent.env 注入环境变量（与 systemd EnvironmentFile 一致）。

    直接执行 python3 run_agent.py 时不会自动加载该文件，导致 NODE_SERVER_ID 等缺失。
    已存在于 os.environ 的键不会被覆盖（便于 systemd / 容器注入覆盖）。
    """
    path = os.environ.get("AGENT_ENV_FILE", "/etc/vpn-node/agent.env")
    if not os.path.isfile(path):
        return
    try:
        with open(path, encoding="utf-8-sig") as f:
            for line in f:
                line = line.strip()
                if not line or line.startswith("#"):
                    continue
                if "=" not in line:
                    continue
                key, val = line.split("=", 1)
                key = key.strip()
                val = val.strip()
                if not key:
                    continue
                if len(val) >= 2 and val[0] == val[-1] and val[0] in "\"'":
                    val = val[1:-1]
                os.environ.setdefault(key, val)
    except OSError as exc:
        logger.warning("cannot read agent env file %s: %s", path, exc)


def _unwrap_api_payload(data: dict) -> dict:
    """A 站 NormalizeApiResponse 将成功体包在 { success, code, message, data: {...} }。"""
    if isinstance(data, dict) and data.get("success") is True:
        inner = data.get("data")
        if isinstance(inner, dict):
            return inner
    return data


def _http_post(url: str, payload: dict, headers: dict | None = None, timeout: int = 10) -> dict:
    data = json.dumps(payload).encode("utf-8")
    req = urllib.request.Request(url, data=data, method="POST")
    req.add_header("Content-Type", "application/json; charset=utf-8")
    req.add_header("Accept", "application/json")
    req.add_header("User-Agent", f"vpn-node-agent/{get_agent_version()}")
    for k, v in (headers or {}).items():
        req.add_header(k, v)
    try:
        with urllib.request.urlopen(req, timeout=timeout) as resp:
            raw = resp.read().decode("utf-8")
            return json.loads(raw) if raw else {}
    except urllib.error.HTTPError as e:
        try:
            body = e.read().decode("utf-8", errors="replace")[:2000]
        except Exception:
            body = ""
        logger.error("HTTP %s %s body: %s", e.code, url, body)
        raise


def _load_agent_token() -> str:
    path = get_agent_token_file()
    try:
        with open(path, "r", encoding="utf-8") as f:
            return f.read().strip()
    except FileNotFoundError:
        return ""


def _save_agent_token(token: str) -> None:
    path = get_agent_token_file()
    os.makedirs(os.path.dirname(path) or ".", mode=0o750, exist_ok=True)
    with open(path, "w", encoding="utf-8") as f:
        f.write(token.strip())
    os.chmod(path, 0o600)


def _register_if_needed(base: str, hostname: str, region: str, role: str) -> str:
    token = _load_agent_token()
    if token:
        return token

    server_id = get_node_server_id()
    if server_id <= 0:
        raise RuntimeError(
            "NODE_SERVER_ID is required for register; "
            "ensure /etc/vpn-node/agent.env contains NODE_SERVER_ID=… or use systemd unit with EnvironmentFile."
        )

    bootstrap = get_agent_bootstrap_token()
    if not bootstrap:
        raise RuntimeError("AGENT_BOOTSTRAP_TOKEN is required for first register")

    payload = {
        "server_id": server_id,
        "hostname": hostname,
        "region": region,
        "role": role,
        "agent_version": get_agent_version(),
        "config_revision_ts": get_config_revision_ts(),
    }
    data = _http_post(
        f"{base}{get_agent_register_path()}",
        payload,
        headers={"X-Agent-Bootstrap-Token": bootstrap},
    )
    reg = _unwrap_api_payload(data)
    token = str(reg.get("agent_token", "")).strip()
    if not token:
        raise RuntimeError("register succeeded but no agent_token returned")
    _save_agent_token(token)
    return token


def _exec_command(cmd: dict) -> dict:
    cid = int(cmd.get("id", 0))
    typ = str(cmd.get("type", "")).strip()
    payload = cmd.get("payload") or {}

    try:
        if typ == "apply_bandwidth":
            apply_bandwidth_limits([{
                "interface": payload.get("interface") or get_bandwidth_interface(),
                "rate_kbps": int(payload.get("rate_kbps", 0)),
            }])
            return {"command_id": cid, "ok": True, "message": "bandwidth applied"}

        if typ == "apply_nat":
            apply_nat_rules([{
                "interface": payload.get("interface") or get_node_nat_interface(),
                "source_cidr": payload.get("source_cidr", "10.0.0.0/8"),
            }])
            return {"command_id": cid, "ok": True, "message": "nat rules applied"}

        if typ == "apply_snat_map":
            apply_snat_mapping(
                interface=str(payload.get("interface") or get_node_nat_interface()),
                source_ip=str(payload.get("source_ip") or "").strip(),
                public_ip=str(payload.get("public_ip") or "").strip(),
            )
            return {"command_id": cid, "ok": True, "message": "snat mapping applied"}

        if typ == "remove_snat_map":
            remove_snat_mapping(
                interface=str(payload.get("interface") or get_node_nat_interface()),
                source_ip=str(payload.get("source_ip") or "").strip(),
                public_ip=str(payload.get("public_ip") or "").strip(),
            )
            return {"command_id": cid, "ok": True, "message": "snat mapping removed"}

        if typ == "apply_antiblock":
            apply_antiblock_policy(payload if isinstance(payload, dict) else {})
            return {"command_id": cid, "ok": True, "message": "antiblock applied"}

        return {"command_id": cid, "ok": False, "message": f"unsupported command type: {typ}"}
    except Exception as exc:
        return {"command_id": cid, "ok": False, "message": f"{typ} failed: {exc}"}


def main() -> None:
    load_agent_env_file()
    hostname = os.environ.get("NODE_HOSTNAME", "node1")
    region = os.environ.get("NODE_REGION", "default")
    role = os.environ.get("NODE_ROLE", "access")
    base = get_control_plane_http_base()
    interval = get_heartbeat_interval_sec()
    logger.info(
        "agent starting: version=%s hostname=%s region=%s role=%s base=%s skip_commands=%s",
        get_agent_version(), hostname, region, role, base, skip_system_commands(),
    )

    token = _register_if_needed(base, hostname, region, role)
    ensure_link_interface(
        tunnel_type=get_link_tunnel_type(),
        iface=get_link_iface(),
        local_ip=get_link_local_ip(),
        remote_ip=get_link_remote_ip(),
        wg_private_key=get_link_wg_private_key(),
        wg_peer_public_key=get_link_wg_peer_public_key(),
        wg_endpoint=get_link_wg_endpoint(),
        wg_allowed_ips=get_link_wg_allowed_ips(),
    )
    ensure_ocserv_deployed()
    logger.info("agent token ready; heartbeat loop started")

    while True:
        try:
            metrics = get_system_metrics()
            hb_payload = {
                "agent_version": get_agent_version(),
                "metrics": metrics,
                "meta": {
                    "hostname": hostname,
                    "region": region,
                    "role": role,
                    "config_revision_ts": get_config_revision_ts(),
                },
                "pull_limit": 30,
            }
            proto = get_node_protocol()
            if proto in ("wireguard", ""):
                xfer = collect_wg_peer_transfer(get_wg_interface())
                if xfer:
                    hb_payload["wg_peer_transfer"] = xfer
            hb = _http_post(
                f"{base}/api/v1/agent/heartbeat",
                hb_payload,
                headers={"Authorization": f"Bearer {token}"},
            )
            hb_inner = _unwrap_api_payload(hb)
            apply_heartbeat_policy(hb_inner.get("policy"))
            commands = hb_inner.get("commands") or []
            if commands:
                results = [_exec_command(c) for c in commands]
                _http_post(
                    f"{base}/api/v1/agent/commands/ack",
                    {"results": results},
                    headers={"Authorization": f"Bearer {token}"},
                )
                ok_cnt = sum(1 for r in results if r.get("ok"))
                logger.info("heartbeat commands: total=%s ok=%s", len(results), ok_cnt)
        except urllib.error.HTTPError as exc:
            logger.error("heartbeat http error: %s", exc)
            if exc.code == 401:
                # token失效时清空本地 token，下一轮重新注册
                _save_agent_token("")
                token = _register_if_needed(base, hostname, region, role)
        except Exception as exc:
            logger.error("heartbeat failed: %s", exc)

        time.sleep(interval)


if __name__ == "__main__":
    main()
