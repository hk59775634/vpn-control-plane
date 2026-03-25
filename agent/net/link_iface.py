import subprocess
import os
import sys

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))
from common.config import skip_system_commands


def ensure_link_interface(
    tunnel_type: str,
    iface: str,
    local_ip: str,
    remote_ip: str,
    wg_private_key: str,
    wg_peer_public_key: str,
    wg_endpoint: str,
    wg_allowed_ips: str,
) -> None:
    if skip_system_commands() or not iface:
        return
    t = (tunnel_type or "").strip().lower()
    if t != "wireguard":
        return
    if not wg_private_key or not wg_peer_public_key:
        return

    subprocess.run(["ip", "link", "show", iface], capture_output=True)
    if subprocess.run(["ip", "link", "show", iface], capture_output=True).returncode != 0:
        subprocess.run(["ip", "link", "add", "dev", iface, "type", "wireguard"], capture_output=True)

    subprocess.run(["ip", "link", "set", iface, "up"], capture_output=True)
    if local_ip:
        subprocess.run(["ip", "address", "replace", local_ip, "dev", iface], capture_output=True)

    cmd = ["wg", "set", iface, "private-key", "/dev/stdin", "peer", wg_peer_public_key]
    if wg_allowed_ips:
        cmd.extend(["allowed-ips", wg_allowed_ips])
    elif remote_ip:
        cmd.extend(["allowed-ips", remote_ip])
    else:
        cmd.extend(["allowed-ips", "0.0.0.0/0"])
    if wg_endpoint:
        cmd.extend(["endpoint", wg_endpoint])
    subprocess.run(cmd, input=wg_private_key.encode(), capture_output=True)

