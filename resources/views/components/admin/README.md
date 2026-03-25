# Admin 云控制台可复用组件

更新时间：**2026-03-25**

所有组件使用 TailwindCSS + Alpine.js，仅在 Blade 层使用，不修改后端。

## 当前使用说明（按实际）

- 这些组件用于 Admin 页面的通用 UI（侧栏、顶栏、表格、抽屉、确认框、Toast、分页）。
- 产品、服务器、IP 池、分销商等业务页目前既包含组件化区块，也包含页面内脚本逻辑。
- 新增业务字段（如产品带宽/流量）时，优先复用现有 `resource-table`、`drawer`、`filter-toolbar` 结构，避免重复样式实现。

## 1. 侧边栏导航

- **`<x-admin.sidebar>`**：整体侧栏，含品牌区、导航区、底部 slot。
  - Props: `brand`, `subtitle`, `brandUrl`
  - Slots: 默认 slot（导航链接）、`footer`（底部，如退出）
- **`<x-admin.sidebar-nav-group>`**：分组标题
  - Props: `label`
- **`<x-admin.sidebar-link>`**：单条导航链接
  - Props: `active`, `href`
  - 需在父级 Alpine 中控制 `active` 与点击逻辑

## 2. 顶部栏（含搜索与用户菜单）

- **`<x-admin.topbar>`**：深色顶栏
  - Props: `brand`, `searchPlaceholder`, `showSearch`
  - Slot: 右侧区域（如用户菜单）
- **`<x-admin.topbar-user-menu>`**：用户下拉菜单
  - Props: `userName`, `userEmail`
  - Slot: 下拉内菜单项（如退出）

## 3. 资源表格

- **`<x-admin.resource-table>`**：表格容器 + 表头
  - Props: `headers`（数组）, `hasActions`（是否含“操作”列）, `emptyMessage`
  - Slots: 默认 slot（`<tbody>` 内容）、`toolbar`（表格上方工具栏）、`empty`（空状态）
  - 表体由父级用 `x-for` 等自行渲染。

## 4. 抽屉表单

- **`<x-admin.drawer>`**：右侧抽屉
  - Props: `showVar`（父级 Alpine 变量名，如 `drawerOpen`）, `width`（如 `max-w-lg`）
  - Slots: `header`, 默认 slot（表单内容）, `footer`（底部按钮）
  - 父级需有 `drawerOpen = false`，设为 `true` 即打开。
- **`<x-admin.drawer-header>`**：抽屉标题 + 关闭按钮
  - Props: `title`
  - 关闭需父级监听 `drawer-close` 或将 `showVar` 设为 false。

## 5. 模态确认框

- **`<x-admin.modal-confirm>`**：全局确认弹窗，由 Alpine.store 驱动
  - Props: `store`（默认 `confirm`）
  - 用法：`Alpine.store('confirm').open({ title: '删除', message: '确定删除？', destructive: true }).then(ok => { if (ok) ... })`
  - 在 layout 中挂载一次即可。

## 6. Toast 通知

- **`<x-admin.toast>`**：全局 Toast，由 Alpine.store 驱动
  - Props: `store`（默认 `toast`）
  - 用法：`Alpine.store('toast').success('成功', '已保存')` / `.error('失败', '...')` / `.info('提示', '...')`
  - 在 layout 中挂载一次即可。

## 7. 过滤工具栏

- **`<x-admin.filter-toolbar>`**：表格上方搜索 + 筛选 + 操作区
  - Props: `searchPlaceholder`, `searchModel`（Alpine 绑定变量名）
  - Slots: `filters`（筛选控件）, `actions`（右侧按钮）

## 8. 分页

- **`<x-admin.pagination>`**：Laravel 分页器渲染
  - Props: `paginator`（`LengthAwarePaginator` 实例）
  - 传入 `paginator` 即渲染上一页/页码/下一页。

## Alpine 商店（admin-stores.js）

- **toast**：`show(title, message, type, duration)` / `success` / `error` / `info`
- **confirm**：`open({ title, message, destructive, confirmLabel, cancelLabel })` 返回 Promise，resolve(true/false)

已在 `app.js` 中 import，依赖 `alpine:init` 注册，与 CDN Alpine 兼容。
