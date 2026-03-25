"""根据心跳返回的 policy 应用 sysctl、iptables、tc 与 wg peer remove。"""
from __future__ import annotations

import logging
from typing import Any

from bandwidth.wg_shaping import apply_wg_peer_shaping
from common.config import skip_system_commands
from nat.combined_node import apply_combined_nat_block
from vpn.wg_peer import remove_wg_peers

logger = logging.getLogger(__name__)


def apply_heartbeat_policy(policy: dict[str, Any] | None) -> None:
    if not policy or skip_system_commands():
        return

    nat_block = policy.get("combined_nat")
    if isinstance(nat_block, dict):
        apply_combined_nat_block(nat_block)

    from common.config import get_wg_interface

    sh = policy.get("wg_shaping")
    if isinstance(sh, dict):
        iface = str(sh.get("interface") or "").strip() or get_wg_interface()
        peers = sh.get("peers") or []
        if not isinstance(peers, list):
            peers = []
        apply_wg_peer_shaping(iface, peers)
    else:
        apply_wg_peer_shaping(get_wg_interface(), [])

    keys = policy.get("wg_remove_peer_public_keys") or []
    if isinstance(keys, list) and keys:
        from common.config import get_wg_interface

        iface = get_wg_interface()
        remove_wg_peers(iface, [str(k).strip() for k in keys if str(k).strip()])
        logger.info("wg_remove_peers count=%s iface=%s", len(keys), iface)
