"""
带宽限速：使用 tc TBF 对指定网卡限速（单位 Kbps）。
与 1.0 Go nodeagent/bandwidth 对应。
"""
import subprocess
import sys
import os

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))
from common.config import skip_system_commands


def apply_bandwidth_limits(limits: list[dict]) -> None:
    """
    limits: [{"interface": "eth0", "rate_kbps": 10000}, ...]
    tc qdisc replace dev <iface> root tbf rate <rate> burst <burst> latency 400ms
    """
    if not limits or skip_system_commands():
        return
    for lim in limits:
        iface = lim.get("interface") or lim.get("Interface")
        rate_kbps = lim.get("rate_kbps") or lim.get("RateKbps") or 0
        if not iface or rate_kbps <= 0:
            continue
        rate = f"{rate_kbps}kbit"
        burst = "256kbit" if rate_kbps > 1000 else "32kbit"
        subprocess.run(
            ["tc", "qdisc", "replace", "dev", iface, "root", "tbf", "rate", rate, "burst", burst, "latency", "400ms"],
            capture_output=True,
        )


def clear_bandwidth_limit(interface: str) -> None:
    if skip_system_commands() or not interface:
        return
    subprocess.run(["tc", "qdisc", "del", "dev", interface, "root"], capture_output=True)
