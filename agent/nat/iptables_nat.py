"""
NAT：使用 iptables 添加/删除 POSTROUTING MASQUERADE 规则。
与 1.0 Go nodeagent/nat 对应。
"""
import subprocess
import sys
import os

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))
from common.config import skip_system_commands


def apply_nat_rules(rules: list[dict]) -> None:
    """
    rules: [{"interface": "eth0", "source_cidr": "10.0.0.0/8"}, ...]
    先 -C 检查是否存在，避免重复添加。
    """
    if not rules or skip_system_commands():
        return
    for r in rules:
        iface = r.get("interface") or r.get("Interface")
        cidr = r.get("source_cidr") or r.get("SourceCIDR")
        if not iface or not cidr:
            continue
        check = ["iptables", "-t", "nat", "-C", "POSTROUTING", "-s", cidr, "-o", iface, "-j", "MASQUERADE"]
        if subprocess.run(check, capture_output=True).returncode == 0:
            continue
        add = ["iptables", "-t", "nat", "-A", "POSTROUTING", "-s", cidr, "-o", iface, "-j", "MASQUERADE"]
        subprocess.run(add, capture_output=True)


def remove_nat_rule(interface: str, source_cidr: str) -> None:
    if skip_system_commands():
        return
    subprocess.run(
        ["iptables", "-t", "nat", "-D", "POSTROUTING", "-s", source_cidr, "-o", interface, "-j", "MASQUERADE"],
        capture_output=True,
    )
