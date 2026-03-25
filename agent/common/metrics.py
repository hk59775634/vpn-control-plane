"""
系统指标：从 /proc 读取 CPU、内存、负载，供心跳上报。
与 1.0 Go Agent metrics 逻辑对应。
"""
import os


def read_load_avg() -> float:
    """读取 /proc/loadavg 第一列 load1。"""
    try:
        with open("/proc/loadavg", "r") as f:
            return float(f.read().split()[0])
    except (FileNotFoundError, ValueError, IndexError):
        return 0.0


def read_mem_percent() -> float:
    """根据 /proc/meminfo 计算内存使用率（百分比）。"""
    try:
        data = {}
        with open("/proc/meminfo", "r") as f:
            for line in f:
                parts = line.strip().split(":")
                if len(parts) == 2:
                    key = parts[0].strip()
                    val = parts[1].strip().split()[0]
                    data[key] = int(val) * 1024  # kB -> bytes
        mem_total = data.get("MemTotal", 0)
        mem_available = data.get("MemAvailable", 0)
        if mem_total <= 0:
            return 0.0
        used = mem_total - mem_available
        return 100.0 * used / mem_total
    except (FileNotFoundError, KeyError, ValueError):
        return 0.0


def read_cpu_percent() -> float:
    """简单从 /proc/stat 计算 CPU 使用率（需两次采样取差值）。这里返回占位，实际可两次采样。"""
    try:
        with open("/proc/stat", "r") as f:
            line = f.readline()
        # cpu user nice system idle iowait irq softirq
        parts = line.split()
        if len(parts) < 5:
            return 0.0
        user = int(parts[1])
        nice = int(parts[2])
        system = int(parts[3])
        idle = int(parts[4])
        total = user + nice + system + idle
        if total == 0:
            return 0.0
        return 100.0 * (user + nice + system) / total
    except (FileNotFoundError, ValueError, IndexError):
        return 0.0


def get_system_metrics() -> dict:
    """返回 CPU%、内存%、load1。"""
    return {
        "cpu_percent": read_cpu_percent(),
        "mem_percent": read_mem_percent(),
        "load_1": read_load_avg(),
    }
