"""
反封锁：将控制面下发的策略写入状态文件（key=value），供外部脚本或本机逻辑读取。
与 1.0 Go nodeagent/antiblock 对应。
"""
import logging
import os
import sys

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))
from common.config import get_antiblock_state_file

logger = logging.getLogger(__name__)


def _bool_val(b: bool) -> str:
    return "1" if b else "0"


def apply_antiblock_policy(result: dict) -> None:
    """
    result: 心跳返回的反封锁字段，如 wireguard_obfs, tls_camouflage, domain_fronting, port_rotation, custom_profile
    """
    path = get_antiblock_state_file()
    os.makedirs(os.path.dirname(path) or ".", mode=0o750, exist_ok=True)
    content = (
        f"wireguard_obfs={_bool_val(result.get('wireguard_obfs', False))}\n"
        f"tls_camouflage={_bool_val(result.get('tls_camouflage', False))}\n"
        f"domain_fronting={_bool_val(result.get('domain_fronting', False))}\n"
        f"port_rotation={_bool_val(result.get('port_rotation', False))}\n"
        f"custom_profile={result.get('custom_profile', '')}\n"
    )
    with open(path, "w") as f:
        f.write(content)
    logger.info("antiblock state written to %s", path)
