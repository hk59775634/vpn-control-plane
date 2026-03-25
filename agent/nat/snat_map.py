import subprocess
import sys
import os

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))
from common.config import skip_system_commands


def apply_snat_mapping(interface: str, source_ip: str, public_ip: str) -> None:
    if skip_system_commands():
        return
    iface = (interface or "").strip()
    src = (source_ip or "").strip()
    pub = (public_ip or "").strip()
    if not iface or not src or not pub:
        return
    cidr = src if "/" in src else f"{src}/32"

    rule = [
        "iptables", "-t", "nat", "-C", "POSTROUTING",
        "-s", cidr, "-o", iface, "-j", "SNAT", "--to-source", pub,
    ]
    if subprocess.run(rule, capture_output=True).returncode == 0:
        return

    add = [
        "iptables", "-t", "nat", "-I", "POSTROUTING", "1",
        "-s", cidr, "-o", iface, "-j", "SNAT", "--to-source", pub,
    ]
    subprocess.run(add, capture_output=True)


def remove_snat_mapping(interface: str, source_ip: str, public_ip: str) -> None:
    if skip_system_commands():
        return
    iface = (interface or "").strip()
    src = (source_ip or "").strip()
    pub = (public_ip or "").strip()
    if not iface or not src or not pub:
        return
    cidr = src if "/" in src else f"{src}/32"
    delete = [
        "iptables", "-t", "nat", "-D", "POSTROUTING",
        "-s", cidr, "-o", iface, "-j", "SNAT", "--to-source", pub,
    ]
    subprocess.run(delete, capture_output=True)

