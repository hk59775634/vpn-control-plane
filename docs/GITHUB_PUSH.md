# 首次推送到 GitHub（vpn-control-plane）

若当前代码在 **monorepo** 内（路径如 **`2.0/php/A`**），要推送 **整个仓库** 时请参阅仓库根目录 **`GITHUB_PUSH.md`**。

---

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
