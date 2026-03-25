"""Agent 版本号：与 A 站 AGENT_PACKAGE_VERSION 一致，格式 YYYYMMDD + 两位序号（10 位数字）。"""
import os

# 默认与未设置环境变量时的回退；SSH 部署时由 /etc/vpn-node/agent.env 注入 AGENT_VERSION 覆盖
_DEFAULT = "2026032501"


def get_agent_version() -> str:
    v = os.environ.get("AGENT_VERSION", "").strip()
    if v and len(v) <= 64:
        return v
    return _DEFAULT
