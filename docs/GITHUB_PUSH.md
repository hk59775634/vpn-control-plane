# A 站文档：推送到 GitHub

更新时间：**2026-03-25**

当前 A 站独立仓库：**https://github.com/hk59775634/vpn-control-plane**  
（可选）monorepo：**https://github.com/hk59775634/vpn1**

---

## 方案 A：推送到 A 站独立仓库（推荐）

若你在 **A 站独立代码目录**（如 `/opt/vpn-control-plane` 或本仓库内的 `2.0/php/A`）执行：

```bash
git remote add origin https://github.com/hk59775634/vpn-control-plane.git
git branch -M main
git push -u origin main
```

## 方案 B：从 monorepo 仅推送 A 站子目录（高级）

当你在 monorepo 根目录（如 `/opt/vpn1`）维护，但要把 `2.0/php/A` 同步到 A 站独立仓库，可用 `git subtree`：

```bash
cd /opt/vpn1
git remote add a_origin https://github.com/hk59775634/vpn-control-plane.git
git subtree split --prefix=2.0/php/A -b a-site-main
git push a_origin a-site-main:main
```

（如需覆盖远端历史可用 `--force`，谨慎使用。）

## 方案 C：新建你自己的 A 站仓库（可选）

1. 在 GitHub 网页：**New repository**  
   - Repository name：`vpn-control-plane`  
   - Description：`A 站控制面`  
   - **不要**勾选 README / .gitignore / license（保持空仓库便于首次 push）

2. 在本机项目根目录（已 `git init` 且已 commit）执行（将 `YOUR_USER` 换成你的 GitHub 用户名或组织名）：

```bash
git remote add origin git@github.com:YOUR_USER/vpn-control-plane.git
# 若使用 HTTPS：
# git remote add origin https://github.com/YOUR_USER/vpn-control-plane.git

git branch -M main
git push -u origin main
```

3. 若尚未配置 SSH：在 GitHub → Settings → SSH and GPG keys 添加本机公钥；或使用 **Personal Access Token** 通过 HTTPS 推送。

4. 使用 **GitHub CLI**（可选）：

```bash
gh repo create vpn-control-plane --public --description "A 站控制面"
git push -u origin main
```

需先 `gh auth login`。
