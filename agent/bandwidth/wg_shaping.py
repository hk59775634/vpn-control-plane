"""WireGuard 接口上按客户端内网 IP 对称限速（HTB egress + ingress police）。"""
from __future__ import annotations

import subprocess
import sys
import os
from typing import Any

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))
from common.config import skip_system_commands


def _run(cmd: list[str]) -> None:
    subprocess.run(cmd, capture_output=True, check=False)


def apply_wg_peer_shaping(iface: str, peers: list[dict[str, Any]]) -> None:
    if skip_system_commands():
        return
    if not iface:
        return

    _run(["tc", "qdisc", "del", "dev", iface, "root"])
    _run(["tc", "qdisc", "del", "dev", iface, "ingress"])

    if not peers:
        return

    _run(["tc", "qdisc", "add", "dev", iface, "root", "handle", "1:", "htb", "default", "999"])
    _run(
        [
            "tc",
            "class",
            "add",
            "dev",
            iface,
            "parent",
            "1:",
            "classid",
            "1:1",
            "htb",
            "rate",
            "10000mbit",
            "ceil",
            "10000mbit",
        ]
    )
    _run(
        [
            "tc",
            "class",
            "add",
            "dev",
            iface,
            "parent",
            "1:1",
            "classid",
            "1:999",
            "htb",
            "rate",
            "10000mbit",
            "ceil",
            "10000mbit",
        ]
    )

    _run(["tc", "qdisc", "add", "dev", iface, "handle", "ffff:", "ingress"])

    for i, p in enumerate(peers):
        ip = str(p.get("client_ip") or "").strip()
        rate_kbps = int(p.get("rate_kbps") or 0)
        if not ip or rate_kbps <= 0:
            continue
        cid = 10 + i
        rate = f"{rate_kbps}kbit"
        burst = f"{max(rate_kbps, 64)}k"

        _run(
            [
                "tc",
                "class",
                "add",
                "dev",
                iface,
                "parent",
                "1:1",
                "classid",
                f"1:{cid}",
                "htb",
                "rate",
                rate,
                "ceil",
                rate,
            ]
        )
        _run(
            [
                "tc",
                "filter",
                "add",
                "dev",
                iface,
                "parent",
                "1:0",
                "protocol",
                "ip",
                "prio",
                str(i + 1),
                "u32",
                "match",
                "ip",
                "dst",
                f"{ip}/32",
                "flowid",
                f"1:{cid}",
            ]
        )

        _run(
            [
                "tc",
                "filter",
                "add",
                "dev",
                iface,
                "parent",
                "ffff:",
                "protocol",
                "ip",
                "prio",
                str(100 + i),
                "u32",
                "match",
                "ip",
                "src",
                f"{ip}/32",
                "police",
                "rate",
                rate,
                "burst",
                burst,
                "mtu",
                "2040",
                "drop",
            ]
        )
