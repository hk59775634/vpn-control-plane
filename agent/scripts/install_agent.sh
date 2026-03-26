#!/usr/bin/env bash
# 从 A 站安装节点 Agent：无需 server_id / token；A 站按「当前连接的源 IP」匹配 servers.host / split_nat_host。
#
# 用法（第 1 个参数：A 站域名或完整根 URL；第 2 个：与 A 站通信用的本机出口 IP，须与 A 站里 SSH 登录地址一致，用于 curl --interface）：
#   wget "https://你的A站/install_agent.sh" -O install_agent.sh && bash install_agent.sh "a.ai101.eu.org" "203.0.113.7"
# 若第 1 个参数不含 http(s)://，则默认使用 https://
# 第 2 个参数可省略（单出口机器可不绑定网卡）。
#
# 分体拓扑：在 NAT / 接入各自 SSH 地址上执行；第 2 参数填对应公网 IP。
# A 站前有 CDN/反代时，源站须能解析真实客户端 IP（如 TrustProxies、CF-Connecting-IP）。
# 多网卡：默认路由公网 IP 与 SSH 登录 IP 不一致时，必须传第 2 参数（curl --interface）。
# 环境变量 VPN_INSTALL_SKIP_SOURCE_DEBUG=1 可跳过 install-source-debug（仅建议自动化场景）。

set -euo pipefail

CP_INPUT="${1:?用法: $0 <A站域名或根URL> [curl出口IP与SSH登录IP一致]}"
OUT_BIND="${2:-}"

case "$CP_INPUT" in
  http://*|https://*)
    API_BASE="$CP_INPUT"
    ;;
  *)
    API_BASE="https://${CP_INPUT}"
    ;;
esac
API_BASE="${API_BASE%/}"

SERVER_ID=""
VPN_LABEL="节点"
# install-source-debug 解析结果（供多网卡提示）
VPN_DBG_MATCHED=""
VPN_DBG_SEEN_IP=""

vpn_curl_a() {
  local -a a=( "$@" )
  if [ -n "${OUT_BIND}" ]; then
    a=( --interface "${OUT_BIND}" "${a[@]}" )
  fi
  curl "${a[@]}"
}

vpn_report() {
  [ -n "${SERVER_ID}" ] || return 0
  local st="$1" msg="$2"
  local jf
  jf=$(mktemp)
  printf '%s' "$msg" | VPN_SID="$SERVER_ID" VPN_ST="$st" python3 -c '
import json, sys, os
m = sys.stdin.read()
print(json.dumps({
  "server_id": int(os.environ["VPN_SID"]),
  "deploy_status": os.environ["VPN_ST"],
  "deploy_message": m,
}, ensure_ascii=False))
' > "$jf"
  vpn_curl_a -fsS -o /dev/null -X POST "${API_BASE}/api/v1/agent/install-progress" \
    -H "Content-Type: application/json; charset=utf-8" \
    --data-binary @"${jf}" \
    --connect-timeout 25 --max-time 35 || true
  rm -f "$jf"
}

vpn_package_url() {
  local base="$1"
  case "$base" in
    *\?*) echo "${base}&server_id=${SERVER_ID}" ;;
    *) echo "${base}?server_id=${SERVER_ID}" ;;
  esac
}

vpn_agent_maybe_sudo() {
  if [ "$(id -u)" -eq 0 ]; then
    "$@"
  elif command -v sudo >/dev/null 2>&1 && sudo -n true 2>/dev/null; then
    sudo -n "$@"
  else
    "$@"
  fi
}

vpn_agent_has_downloader() { command -v curl >/dev/null 2>&1 || command -v wget >/dev/null 2>&1; }

vpn_agent_tools_ok() {
  command -v python3 >/dev/null 2>&1 \
    && vpn_agent_has_downloader \
    && command -v tar >/dev/null 2>&1 \
    && command -v gzip >/dev/null 2>&1 \
    && command -v find >/dev/null 2>&1
}

vpn_agent_install_tools() {
  if vpn_agent_tools_ok; then return 0; fi
  echo "[vpn-install] 正在安装依赖（python3、curl、wget、tar、gzip 等）…" >&2
  if [ -f /etc/debian_version ]; then
    export DEBIAN_FRONTEND=noninteractive
    vpn_agent_maybe_sudo apt-get update -qq \
      && vpn_agent_maybe_sudo apt-get install -y -qq python3 curl wget ca-certificates tar gzip findutils coreutils
  elif [ -f /etc/alpine-release ]; then
    vpn_agent_maybe_sudo apk add --no-cache python3 curl wget ca-certificates tar gzip
  elif [ -f /etc/arch-release ]; then
    vpn_agent_maybe_sudo pacman -Sy --noconfirm python curl wget ca-certificates tar gzip findutils
  elif command -v dnf >/dev/null 2>&1; then
    vpn_agent_maybe_sudo dnf install -y python3 curl wget tar gzip findutils ca-certificates coreutils
  elif command -v yum >/dev/null 2>&1; then
    vpn_agent_maybe_sudo yum install -y python3 curl wget tar gzip findutils ca-certificates coreutils
  elif command -v zypper >/dev/null 2>&1; then
    vpn_agent_maybe_sudo zypper --non-interactive install -y python3 curl wget tar gzip findutils ca-certificates coreutils
  else
    echo "[vpn-install] 无法识别包管理器" >&2
    exit 1
  fi
  if ! vpn_agent_tools_ok; then
    echo "[vpn-install] 依赖仍不完整" >&2
    exit 1
  fi
}

vpn_print_install_source_debug() {
  local tmp code evf
  VPN_DBG_MATCHED=""
  VPN_DBG_SEEN_IP=""
  tmp=$(mktemp)
  evf=$(mktemp)
  code=$(vpn_curl_a -sS -o "$tmp" -w "%{http_code}" --connect-timeout 25 --max-time 90 \
    "${API_BASE}/api/v1/agent/install-source-debug") || code="000"
  echo "[vpn-install] install-source-debug HTTP ${code}" >&2
  VPN_DBG_FILE="$tmp" VPN_EVAL_OUT="$evf" python3 -c '
import json, os, sys, shlex
p = os.environ["VPN_DBG_FILE"]
ev = os.environ["VPN_EVAL_OUT"]
try:
  j = json.loads(open(p, encoding="utf-8").read())
except Exception:
  print("[vpn-install] 响应体（非 JSON，前 2000 字符）:", file=sys.stderr)
  try:
    print(open(p, errors="replace").read()[:2000], file=sys.stderr)
  except OSError:
    pass
  sys.exit(0)
d = j.get("data") or {}
print("[vpn-install] A 站感知源 IP:   ", d.get("client_ip"), file=sys.stderr)
print("[vpn-install] 归一化后:         ", d.get("client_ip_normalized"), file=sys.stderr)
print("[vpn-install] 已匹配 server_id: ", d.get("matched_server_id"), file=sys.stderr)
print("[vpn-install] X-Forwarded-For:  ", d.get("x_forwarded_for"), file=sys.stderr)
print("[vpn-install] X-Real-IP:        ", d.get("x_real_ip"), file=sys.stderr)
if d.get("hint"):
  print("[vpn-install]", d["hint"], file=sys.stderr)
m = d.get("matched_server_id")
seen = d.get("client_ip_normalized") or ""
with open(ev, "w", encoding="utf-8") as f:
  f.write("export VPN_DBG_MATCHED=%s\n" % ("" if m is None else str(int(m))))
  f.write("export VPN_DBG_SEEN_IP=%s\n" % shlex.quote(seen))
' || true
  if [ -s "$evf" ]; then
    # shellcheck source=/dev/null
    . "$evf"
  fi
  rm -f "$tmp" "$evf"
}

vpn_hint_if_multihome_mismatch() {
  if [ -n "${OUT_BIND}" ]; then
    return 0
  fi
  if [ -n "${VPN_DBG_MATCHED}" ]; then
    return 0
  fi
  echo "[vpn-install] 未匹配服务器且未指定第2参数。若本机有多块公网网卡，默认路由出口可能与 SSH 地址不一致。" >&2
  echo "[vpn-install] 请执行: ip -brief -4 addr show scope global —— 将第2参数设为「SSH 登录所用」那块网卡上的 IPv4。" >&2
  if command -v ip >/dev/null 2>&1; then
    ip -brief -4 addr show scope global 2>/dev/null | sed 's/^/[vpn-install]   /' >&2 || true
  fi
}

vpn_download_and_extract() {
  local U="$1" DEST="$2"
  local tmp code
  tmp=$(mktemp "${TMPDIR:-/tmp}/vpn-agent.XXXXXX.pkg")
  trap 'rm -f "$tmp"' RETURN
  if [ -n "${OUT_BIND}" ] && ! command -v curl >/dev/null 2>&1; then
    echo "[vpn-install] 指定了出口绑定参数时需要 curl" >&2
    exit 1
  fi
  U="$(vpn_package_url "$U")"
  if command -v curl >/dev/null 2>&1; then
    code=$(vpn_curl_a -sS --connect-timeout 30 --max-time 600 \
      -o "$tmp" -w "%{http_code}" "$U") || code="000"
  elif command -v wget >/dev/null 2>&1; then
    if wget -q --timeout=600 -O "$tmp" "$U"; then
      code="200"
    else
      echo "[vpn-install] wget 下载失败" >&2
      head -c 4096 "$tmp" 2>/dev/null >&2 || true
      exit 1
    fi
  else
    exit 1
  fi
  if [ "${code:-000}" != "200" ]; then
    echo "[vpn-install] 下载失败 HTTP ${code}" >&2
    head -c 4096 "$tmp" >&2 || true
    if head -c 8192 "$tmp" 2>/dev/null | grep -q proc_open; then
      echo "[vpn-install] A 站返回打包失败：PHP 未启用 proc_open。请在 A 站 php.ini 去掉 disable_functions 中对 proc_open 的限制并重载 PHP-FPM。" >&2
    fi
    exit 1
  fi
  if ! gzip -t "$tmp" 2>/dev/null; then
    echo "[vpn-install] 响应不是 gzip（可能是 JSON 错误页）" >&2
    head -c 4096 "$tmp" >&2 || true
    if head -c 8192 "$tmp" 2>/dev/null | grep -q proc_open; then
      echo "[vpn-install] 若正文含 proc_open：请在 A 站启用 PHP proc_open 后重试。" >&2
    fi
    exit 1
  fi
  if ! tar xzf "$tmp" -C "$DEST"; then
    echo "[vpn-install] 解压 Agent 包失败（tar xzf）。临时文件仍位于 ${tmp}，可手动检查。" >&2
    exit 1
  fi
}

# --- 主流程 ---

vpn_agent_install_tools

if [ "${VPN_INSTALL_SKIP_SOURCE_DEBUG:-0}" != "1" ]; then
  echo "[vpn-install] 正在查询 A 站看到的源 IP（请与第2参数及 A 站「服务器」SSH 地址对照）…" >&2
  vpn_print_install_source_debug
  vpn_hint_if_multihome_mismatch
else
  echo "[vpn-install] 已跳过 install-source-debug（VPN_INSTALL_SKIP_SOURCE_DEBUG=1）" >&2
fi

CONTEXT_JSON="$(vpn_curl_a -fsSL --connect-timeout 25 --max-time 90 \
  "${API_BASE}/api/v1/agent/install-context")" || {
  echo "[vpn-install] 拉取 install-context 失败：请对照上方「A 站感知源 IP」与脚本第2参数、curl --interface；"
  echo "[vpn-install] A 站 servers.host / split_nat_host 须与该 IP 一致；HTTP 404 多为路由未进 Laravel，422 多为未匹配服务器。" >&2
  exit 1
}

# 首次仅解析 server_id / 下载 URL 等；agent.env 在「下载包之后」再拉取 install-context 写入，
# 避免下载耗时期间用户在 A 站保存服务器导致 CONFIG_REVISION_TS 已升高而落盘仍是旧值（控制台长期「待下发」）。
eval "$(printf '%s' "$CONTEXT_JSON" | python3 -c "
import json, sys
try:
    j = json.load(sys.stdin)
except Exception as e:
    print('[vpn-install] install-context 不是合法 JSON:', e, file=sys.stderr)
    sys.exit(1)
d = j.get('data') or j
for k in ('env', 'server_id', 'package_url'):
    if k not in d:
        print('[vpn-install] install-context 缺少字段:', k, file=sys.stderr)
        sys.exit(1)
print('export SERVER_ID=' + str(int(d['server_id'])))
print('export VPN_PKG_URL=' + json.dumps(d['package_url']))
print('export VPN_LABEL=' + json.dumps(d.get('stage_label', '节点')))
print('export VPN_REMOTE_DIR=' + json.dumps(d.get('remote_dir', '/opt/vpn1-agent')))
")"

vpn_report running "${VPN_LABEL}：开始 install_agent.sh…"

vpn_report running "${VPN_LABEL}：准备目录…"
mkdir -p "${VPN_REMOTE_DIR}" /etc/vpn-node
find "${VPN_REMOTE_DIR}" -mindepth 1 -delete

vpn_report running "${VPN_LABEL}：下载 Agent 包…"
vpn_download_and_extract "${VPN_PKG_URL}" "${VPN_REMOTE_DIR}"

vpn_report running "${VPN_LABEL}：拉取最新 install-context 并写入 agent.env（对齐 CONFIG_REVISION_TS）…"
CONTEXT_JSON="$(vpn_curl_a -fsSL --connect-timeout 25 --max-time 90 \
  "${API_BASE}/api/v1/agent/install-context")" || {
  echo "[vpn-install] 二次拉取 install-context 失败，无法写入与 A 站一致的 agent.env" >&2
  exit 1
}
printf '%s' "$CONTEXT_JSON" | python3 -c "
import json, pathlib, sys
try:
    j = json.load(sys.stdin)
except Exception as e:
    print('[vpn-install] install-context 不是合法 JSON:', e, file=sys.stderr)
    sys.exit(1)
d = j.get('data') or j
for k in ('env', 'server_id', 'package_url'):
    if k not in d:
        print('[vpn-install] install-context 缺少字段:', k, file=sys.stderr)
        sys.exit(1)
sid = int(d['server_id'])
if sid != int(${SERVER_ID:?}):
    print('[vpn-install] 错误：二次 install-context 的 server_id=%s 与首次 %s 不一致' % (sid, ${SERVER_ID}), file=sys.stderr)
    sys.exit(1)
pathlib.Path('/tmp/vpn-install.env').write_text(d['env'])
pathlib.Path('/tmp/vpn-install-systemd.service').write_text(d.get('systemd_unit', ''))
"

vpn_report running "${VPN_LABEL}：写入 agent.env 与 systemd…"
install -m 0600 /tmp/vpn-install.env /etc/vpn-node/agent.env
install -m 0644 /tmp/vpn-install-systemd.service /etc/systemd/system/vpn-node-agent.service
rm -f /tmp/vpn-install.env /tmp/vpn-install-systemd.service

vpn_report running "${VPN_LABEL}：启动 vpn-node-agent.service…"
if command -v systemctl >/dev/null 2>&1 && [ -d /run/systemd/system ]; then
  systemctl daemon-reload
  # enable/start + 强制重启，确保加载最新 agent 代码与 /etc/vpn-node/agent.env
  if ! systemctl enable --now vpn-node-agent.service; then
    echo "[vpn-install] systemctl enable --now 失败，请检查 journalctl -u vpn-node-agent -e" >&2
    exit 1
  fi
  if ! systemctl restart vpn-node-agent.service; then
    echo "[vpn-install] systemctl restart 失败，请检查 journalctl -u vpn-node-agent -e" >&2
    exit 1
  fi
else
  echo "[vpn-install] 未检测到运行中的 systemd，无法自动注册服务。" >&2
  echo "[vpn-install] 请手动将 /etc/systemd/system/vpn-node-agent.service 载入并启动，或改用等价进程管理。" >&2
  exit 1
fi

vpn_report success "${VPN_LABEL}：安装完成，等待 Agent 注册与心跳"
echo "[vpn-install] 完成：${VPN_REMOTE_DIR} 已部署，vpn-node-agent.service 已启用。" >&2
