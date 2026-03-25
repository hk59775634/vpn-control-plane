"""
WireGuard：根据下发的 peers 生成配置并执行 wg syncconf。
与 1.0 Go nodeagent/wireguard 对应。
"""
import os
import subprocess
import sys

# 可依赖 common 的 skip_system_commands
sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))
from common.config import skip_system_commands


def apply_wireguard_config(
    interface: str,
    private_key: str,
    address: str,
    peers: list[dict],
) -> None:
    """
    将 peers 写入 WireGuard 配置并执行 wg syncconf。
    peers 每项含 public_key, allowed_ips 等。
    """
    if skip_system_commands():
        return
    # 构建 [Interface] / [Peer] 配置并写入临时文件，再 wg syncconf <iface> < <file>
    lines = ["[Interface]", f"PrivateKey = {private_key}", f"Address = {address}"]
    for p in peers:
        lines.append("[Peer]")
        lines.append(f"PublicKey = {p.get('public_key', '')}")
        lines.append(f"AllowedIPs = {p.get('allowed_ips', '0.0.0.0/0')}")
        if p.get("endpoint"):
            lines.append(f"Endpoint = {p['endpoint']}")
    conf = "\n".join(lines)
    # 实际实现：写临时文件后 subprocess.run(["wg", "syncconf", interface, "-"], input=conf.encode())
    subprocess.run(
        ["wg", "syncconf", interface, "-"],
        input=conf.encode(),
        capture_output=True,
        check=False,
    )
