"""WireGuard：剔除 peer、采集接口流量计数（供心跳上报）。"""
from __future__ import annotations

import logging
import subprocess
from typing import Any

logger = logging.getLogger(__name__)


def remove_wg_peers(iface: str, public_keys: list[str]) -> None:
    if not iface:
        return
    for pk in public_keys:
        if not pk:
            continue
        r = subprocess.run(
            ["wg", "set", iface, "peer", pk, "remove"],
            capture_output=True,
            text=True,
        )
        if r.returncode != 0 and r.stderr:
            logger.debug("wg set remove peer: %s", r.stderr.strip())


def collect_wg_peer_transfer(iface: str) -> list[dict[str, Any]]:
    if not iface:
        return []
    r = subprocess.run(
        ["wg", "show", iface, "dump"],
        capture_output=True,
        text=True,
    )
    if r.returncode != 0:
        return []
    lines = r.stdout.strip().split("\n")
    if len(lines) < 2:
        return []
    out: list[dict[str, Any]] = []
    for line in lines[1:]:
        parts = line.split("\t")
        if len(parts) < 7:
            continue
        try:
            out.append(
                {
                    "public_key": parts[0],
                    "rx_bytes": int(parts[5]),
                    "tx_bytes": int(parts[6]),
                }
            )
        except ValueError:
            continue
    return out
