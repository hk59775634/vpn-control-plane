"""
系统指标：从 /proc 读取 CPU、内存、负载，并上报在线用户数。
"""
import os
import json
import time
import subprocess

from common.config import get_node_protocol, get_occtl_socket, get_wg_interface


def read_load_avg() -> float:
    """读取 /proc/loadavg 第一列 load1。"""
    try:
        with open("/proc/loadavg", "r") as f:
            return float(f.read().split()[0])
    except (FileNotFoundError, ValueError, IndexError):
        return 0.0


def read_mem_percent() -> float:
    """根据 /proc/meminfo 计算内存使用率（百分比）。"""
    try:
        data = {}
        with open("/proc/meminfo", "r") as f:
            for line in f:
                parts = line.strip().split(":")
                if len(parts) == 2:
                    key = parts[0].strip()
                    val = parts[1].strip().split()[0]
                    data[key] = int(val) * 1024  # kB -> bytes
        mem_total = data.get("MemTotal", 0)
        mem_available = data.get("MemAvailable", 0)
        if mem_total <= 0:
            return 0.0
        used = mem_total - mem_available
        return 100.0 * used / mem_total
    except (FileNotFoundError, KeyError, ValueError):
        return 0.0


def read_cpu_percent() -> float:
    """简单从 /proc/stat 计算 CPU 使用率（需两次采样取差值）。这里返回占位，实际可两次采样。"""
    try:
        with open("/proc/stat", "r") as f:
            line = f.readline()
        # cpu user nice system idle iowait irq softirq
        parts = line.split()
        if len(parts) < 5:
            return 0.0
        user = int(parts[1])
        nice = int(parts[2])
        system = int(parts[3])
        idle = int(parts[4])
        total = user + nice + system + idle
        if total == 0:
            return 0.0
        return 100.0 * (user + nice + system) / total
    except (FileNotFoundError, ValueError, IndexError):
        return 0.0


def read_online_users() -> int:
    """在线用户数：WireGuard 按最近握手统计，OCServ 按 occtl 用户列表统计。"""
    proto = (get_node_protocol() or "").strip().lower()
    if proto == "ocserv":
        return len(_collect_ocserv_sessions())
    return len(_collect_wireguard_sessions())


def _collect_wireguard_sessions() -> list[dict]:
    iface = (get_wg_interface() or "wg0").strip() or "wg0"
    now = int(time.time())
    hs = _run_text(["wg", "show", iface, "latest-handshakes"])
    if not hs:
        return []
    endpoints = _parse_wg_kv(_run_text(["wg", "show", iface, "endpoints"]))
    transfers = _parse_wg_transfer(_run_text(["wg", "show", iface, "transfer"]))
    rows: list[dict] = []
    for line in hs.splitlines():
        parts = line.strip().split()
        if len(parts) < 2:
            continue
        pub = parts[0]
        try:
            ts = int(parts[1])
        except ValueError:
            continue
        if ts <= 0 or (now - ts) > 300:
            continue
        tx, rx = transfers.get(pub, (0, 0))
        rows.append({
            "username": pub[:12] + "...",
            "source_ip": endpoints.get(pub, ""),
            "connected_seconds": max(0, now - ts),
            "rx_bytes": rx,
            "tx_bytes": tx,
            "protocol": "wireguard",
        })
    return rows


def _occtl_cmd_prefix() -> list[str]:
    """occtl 必须连到与 ocserv 一致的 control socket，否则子进程可能无输出。"""
    raw = get_occtl_socket()
    cand = raw if raw else "/var/run/occtl.socket"
    if os.path.exists(cand):
        return ["occtl", "-s", cand]
    return ["occtl"]


def _run_occtl(args_tail: list[str]) -> str:
    cmd = _occtl_cmd_prefix() + args_tail
    try:
        p = subprocess.run(cmd, capture_output=True, text=True, check=False, timeout=8)
    except (FileNotFoundError, subprocess.SubprocessError):
        return ""
    if p.returncode != 0:
        return ""
    return p.stdout or ""


def _safe_uint(val, limit: int | None = None) -> int:
    """将 occtl JSON 中的字节数、时长等安全转为非负整数（避免对 '1.2 MB' 等字符串 int() 抛错）。"""
    if val is None:
        return 0
    if isinstance(val, bool):
        n = int(val)
    elif isinstance(val, (int, float)):
        n = int(val)
    else:
        s = str(val).strip().replace(",", "")
        if not s:
            return 0
        try:
            n = int(float(s)) if ("." in s or "e" in s.lower()) else int(s)
        except ValueError:
            return 0
    if n < 0:
        n = 0
    if limit is not None and n > limit:
        n = limit
    return n


def _iter_ocserv_json_user_rows(data) -> list[dict]:
    if isinstance(data, list):
        return [x for x in data if isinstance(x, dict)]
    if not isinstance(data, dict):
        return []
    for key in ("users", "Users", "sessions", "Sessions", "records", "data"):
        v = data.get(key)
        if isinstance(v, list):
            return [x for x in v if isinstance(x, dict)]
    if data.get("Username") or data.get("username"):
        return [data]
    return []


def _parse_one_ocserv_json_row(row: dict) -> dict | None:
    if not isinstance(row, dict):
        return None
    try:
        username = str(
            row.get("Username")
            or row.get("username")
            or row.get("User")
            or row.get("name")
            or row.get("user")
            or ""
        ).strip()
        src = str(
            row.get("Remote IP")
            or row.get("RemoteIP")
            or row.get("remote_ip")
            or row.get("IP address")
            or row.get("IP")
            or row.get("ip")
            or ""
        ).strip()
        rx = _safe_uint(
            row.get("raw_rx")
            or row.get("RX_bytes")
            or row.get("rx_bytes")
            or row.get("RX")
            or row.get("recv")
        )
        tx = _safe_uint(
            row.get("raw_tx")
            or row.get("TX_bytes")
            or row.get("tx_bytes")
            or row.get("TX")
            or row.get("sent")
        )
        conn = _safe_uint(
            row.get("Connected for (seconds)")
            or row.get("Connected_for_seconds")
            or row.get("connected_for")
            or row.get("conn_time")
            or row.get("Connected for")
            or row.get("Session duration")
            or row.get("session_duration")
            or 0,
            limit=31_536_000,
        )
        uid = str(row.get("ID") or row.get("id") or "").strip()
        if not username and uid:
            username = uid
        if not username and not src and rx == 0 and tx == 0:
            return None
        return {
            "username": username or "-",
            "source_ip": src,
            "connected_seconds": conn,
            "rx_bytes": rx,
            "tx_bytes": tx,
            "protocol": "ocserv",
        }
    except Exception:
        return None


def _collect_ocserv_sessions() -> list[dict]:
    parsed: list[dict] = []
    json_out = _run_occtl(["--json", "show", "users"]) or _run_occtl(["-j", "show", "users"])
    if json_out:
        try:
            blob = json.loads(json_out)
            for row in _iter_ocserv_json_user_rows(blob):
                one = _parse_one_ocserv_json_row(row)
                if one:
                    parsed.append(one)
        except (json.JSONDecodeError, TypeError, ValueError):
            pass
    if parsed:
        return parsed

    txt = _run_occtl(["show", "users"])
    rows: list[dict] = []
    if not txt:
        return rows
    for line in txt.splitlines():
        t = line.strip()
        if not t or t.lower().startswith("id,") or t.lower().startswith("id\t") or t.lower().startswith("id "):
            continue
        if set(t) <= {"-", " "}:
            continue
        parts = t.split()
        username = parts[1] if len(parts) > 1 else "-"
        src = parts[3] if len(parts) > 3 else ""
        rows.append({
            "username": username,
            "source_ip": src,
            "connected_seconds": 0,
            "rx_bytes": 0,
            "tx_bytes": 0,
            "protocol": "ocserv",
        })
    return rows


def _run_text(cmd: list[str]) -> str:
    try:
        p = subprocess.run(cmd, capture_output=True, text=True, check=False, timeout=4)
    except (FileNotFoundError, subprocess.SubprocessError):
        return ""
    if p.returncode != 0:
        return ""
    return p.stdout or ""


def _parse_wg_kv(out: str) -> dict[str, str]:
    m: dict[str, str] = {}
    for line in out.splitlines():
        parts = line.strip().split(maxsplit=1)
        if len(parts) == 2:
            m[parts[0]] = parts[1].strip()
    return m


def _parse_wg_transfer(out: str) -> dict[str, tuple[int, int]]:
    m: dict[str, tuple[int, int]] = {}
    for line in out.splitlines():
        parts = line.strip().split()
        if len(parts) < 3:
            continue
        pub = parts[0]
        try:
            rx = int(parts[1])
            tx = int(parts[2])
        except ValueError:
            continue
        m[pub] = (tx, rx)
    return m


def get_online_sessions(limit: int = 200) -> list[dict]:
    proto = (get_node_protocol() or "").strip().lower()
    rows = _collect_ocserv_sessions() if proto == "ocserv" else _collect_wireguard_sessions()
    if limit > 0:
        return rows[:limit]
    return rows


def get_system_metrics() -> dict:
    """返回 CPU%、内存%、load1、在线用户数。"""
    return {
        "cpu_percent": read_cpu_percent(),
        "mem_percent": read_mem_percent(),
        "load_1": read_load_avg(),
        "online_users": read_online_users(),
    }
