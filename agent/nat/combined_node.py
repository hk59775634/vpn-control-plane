"""一体拓扑：IPv4 转发、FORWARD、MASQUERADE（可选持久化）。"""
from __future__ import annotations

import os
import subprocess
import sys
import logging
from typing import Any

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))
from common.config import get_wg_interface, skip_system_commands
from nat.iptables_nat import apply_nat_rules

logger = logging.getLogger(__name__)


def _run(cmd: list[str]) -> bool:
    p = subprocess.run(cmd, capture_output=True, check=False, text=True)
    if p.returncode != 0:
        logger.warning("command failed: %s rc=%s stderr=%s", cmd, p.returncode, (p.stderr or "").strip())
        return False
    return True


def _ensure_sysctl_forward(persist: bool) -> None:
    if skip_system_commands():
        return
    if persist:
        path = "/etc/sysctl.conf"
        try:
            existing = ""
            try:
                with open(path, "r", encoding="utf-8") as f:
                    existing = f.read()
            except FileNotFoundError:
                existing = ""

            lines = existing.splitlines()
            found = False
            out: list[str] = []
            for line in lines:
                stripped = line.strip()
                if stripped.startswith("net.ipv4.ip_forward"):
                    out.append("net.ipv4.ip_forward=1")
                    found = True
                else:
                    out.append(line)
            if not found:
                if out and out[-1].strip() != "":
                    out.append("")
                out.append("net.ipv4.ip_forward=1")
            new_content = "\n".join(out).rstrip("\n") + "\n"
            if new_content != existing:
                with open(path, "w", encoding="utf-8") as f:
                    f.write(new_content)
            _run(["sysctl", "-q", "-p", path])
        except OSError:
            _run(["sysctl", "-w", "net.ipv4.ip_forward=1"])
    else:
        _run(["sysctl", "-w", "net.ipv4.ip_forward=1"])


def _ensure_forward_rules(wg_if: str, wan_if: str) -> None:
    if skip_system_commands() or not wg_if or not wan_if:
        return
    pairs = [
        (
            ["iptables", "-C", "FORWARD", "-i", wg_if, "-o", wan_if, "-j", "ACCEPT"],
            ["iptables", "-A", "FORWARD", "-i", wg_if, "-o", wan_if, "-j", "ACCEPT"],
        ),
        (
            [
                "iptables",
                "-C",
                "FORWARD",
                "-i",
                wan_if,
                "-o",
                wg_if,
                "-m",
                "conntrack",
                "--ctstate",
                "RELATED,ESTABLISHED",
                "-j",
                "ACCEPT",
            ],
            [
                "iptables",
                "-A",
                "FORWARD",
                "-i",
                wan_if,
                "-o",
                wg_if,
                "-m",
                "conntrack",
                "--ctstate",
                "RELATED,ESTABLISHED",
                "-j",
                "ACCEPT",
            ],
        ),
    ]
    for check, add in pairs:
        if subprocess.run(check, capture_output=True).returncode != 0:
            subprocess.run(add, capture_output=True)
    rev_check = [
        "iptables",
        "-C",
        "FORWARD",
        "-i",
        wan_if,
        "-o",
        wg_if,
        "-j",
        "ACCEPT",
    ]
    if subprocess.run(rev_check, capture_output=True).returncode != 0:
        _run(["iptables", "-A", "FORWARD", "-i", wan_if, "-o", wg_if, "-j", "ACCEPT"])


def _maybe_persist_iptables() -> None:
    if skip_system_commands():
        return
    rules_path = "/etc/iptables/rules.v4"
    parent = os.path.dirname(rules_path)
    try:
        os.makedirs(parent, mode=0o755, exist_ok=True)
    except OSError:
        return
    subprocess.run(
        f"iptables-save > {rules_path}",
        shell=True,
        capture_output=True,
        check=False,
    )


def apply_combined_nat_block(block: dict[str, Any]) -> None:
    if not block.get("enabled"):
        return
    wan = str(block.get("wan_interface") or "").strip() or "eth0"
    cidrs = block.get("source_cidrs") or []
    if not isinstance(cidrs, list) or not cidrs:
        return
    wg_if = str(block.get("wg_interface") or "").strip() or get_wg_interface()

    persist_sysctl = bool(block.get("persist_sysctl", True))
    persist_iptables = bool(block.get("persist_iptables", True))

    _ensure_sysctl_forward(persist_sysctl)
    _ensure_forward_rules(wg_if, wan)

    rules = [{"interface": wan, "source_cidr": str(c).strip()} for c in cidrs if str(c).strip()]
    apply_nat_rules(rules)

    if persist_iptables:
        _maybe_persist_iptables()
