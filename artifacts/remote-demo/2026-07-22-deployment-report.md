# Ubuntu 演示站点部署与上线前测试报告

日期：2026-07-22（Asia/Shanghai）

## 结论

项目已通过 `ssh tc-gz` 部署到 Ubuntu 服务器，并通过现有 HTTPS 站点对外提供演示访问：

- 公开留言板：`https://keson.icu/liuyanban/`
- 管理后台：`https://keson.icu/liuyanban/admin/`
- 部署发布目录：`/home/ubuntu/liuyanban-demo/releases/20260722-073826/liuyanban`
- 当前版本链接：`/home/ubuntu/liuyanban-demo/current`
- 运行配置目录：`/home/ubuntu/liuyanban-demo/runtime`
- QA 日志目录：`/home/ubuntu/liuyanban-demo/qa`

演示环境保留了 150 条 `QA150-` 测试留言。数据库/业务检查 39 项全部通过，HTTP 与管理后台检查 42 项全部通过，公网、窄屏和浏览器交互检查通过。

## 部署结构

- Apache、PHP、MySQL 使用独立 Docker Compose 项目 `liuyanban_demo`。
- Apache 仅监听服务器回环地址 `127.0.0.1:18088`，不直接暴露高端口。
- 现有 1Panel OpenResty 将 `/liuyanban/` 反向代理到 `127.0.0.1:18088`。
- MySQL 不映射宿主机端口。
- 应用源码以只读方式挂载；日志、Session 和数据库使用独立数据卷。
- 容器均配置 `restart: unless-stopped`。
- 演示管理员和数据库密码为服务器随机生成值；配置文件权限均为 `0600`，未写入仓库或报告。

实际版本：

```text
Apache/2.4.39 (Unix)
PHP 7.3.4
MySQL 5.7.26
```

## 反向代理与回滚

- 代理配置：`/opt/1panel/www/sites/keson.icu/proxy/liuyanban.conf`
- 原站点配置备份：`/opt/1panel/www/conf.d/keson.icu.conf.backup-20260722-liuyanban-demo`
- OpenResty 配置语法检查：通过
- Apache 配置语法检查：通过
- HTTPS 公网直连：HTTP/2 200
- Session Cookie：`Secure; HttpOnly; SameSite=Lax; Path=/liuyanban/`
- HTTPS 响应包含 HSTS、CSP、X-Frame-Options、X-Content-Type-Options 和 Referrer-Policy。

## 测试数据覆盖

150 条数据由 `tests/seed_prelaunch.php` 生成，覆盖 48 种组合：

- 审核：`pending`、`approved`、`rejected`
- 回复：未回复、回复草稿、已发布回复、已发布后存在新草稿
- 展示：`visible`、`hidden`
- 删除：正常、软删除

每种组合包含 3 至 4 条数据。当前公开数据为 12 条，其中已回复 6 条、待回复 6 条；公开页第一页 10 条、第二页 2 条。

## 自动化测试结果

### 数据库与业务层

`tests/prelaunch.php`：PASS，39 项通过。

覆盖：状态组合、公开条件、10 条分页、后台 20/50 条分页、36 组后台筛选、关键词及 SQL 注入字符串、日期边界、非法参数回退、单条及批量状态流转、草稿和发布回复、操作日志、100 条批量上限、测试后数据完整性。

### HTTP 与管理后台

`tests/http_prelaunch.sh`：PASS，42 项通过、0 项失败。

覆盖：公开页与静态资源、三种公开筛选、草稿不可见、已发布回复可见、默认展开、HTML 转义、utf8mb4、多行中文、JSON Content-Type 拒绝、未登录跳转、独立 Session、CSRF、管理员登录、150 条后台检索、状态展示、写接口方法限制及错误 CSRF 拒绝。

测试脚本已支持从环境变量传入管理员账号和密码，以及不同数据库对应的公开计数；默认值仍兼容本地开发环境。

### 权限与运行状态

- 应用数据库账号读取 `admin`：通过
- 应用数据库账号更新 `liuyan_*`：通过
- 应用数据库账号写入 `admin`：被 MySQL 正确拒绝
- PHP Fatal/Parse Error 与容器崩溃日志扫描：未发现异常
- Apache：运行中，仅绑定 `127.0.0.1:18088`
- PHP：运行中
- MySQL：运行中且健康

### 浏览器与响应式

- Chromium 实际渲染：通过
- 首页留言数：10；第二页：2
- 6 条已回复留言默认展开
- 点击箭头可收起回复，再次点击可展开
- 390 × 844 视口：无横向溢出，列表、分页和表单均在视口内
- 浏览器 Console：无 error/warning
- 政府网站风格背景图成功加载

真实匿名提交的正向链路尚未在演示站点执行：页面包含算式验证码，浏览器操作需要获得用户对本次验证码求解的明确确认。提交路由的拒绝路径、数据库写入能力、默认待审核状态和本地端到端流程已有自动化覆盖；获得确认后可再执行一次远程提交，并在验证后立即清理该条数据。

## 部署过程中发现并解决的问题

1. 云服务器高端口不适合作为稳定公网入口，因此改为复用现有 HTTPS 域名的 `/liuyanban/` 路径反向代理，并将容器端口收紧为回环监听。
2. 首次重载后的立即请求短暂命中旧 OpenResty worker 返回 404；配置生效后持续返回 200，配置语法复检通过。
3. HTTP 测试脚本原先写死本地管理员 `admin/password`，已改为环境变量输入，服务器随机凭据不会出现在日志中。
4. 公开筛选计数原先包含本地历史样例，已改为可配置预期值；干净演示库的标准结果为 6/6。

## 运维命令

查看容器状态：

```sh
ssh tc-gz 'cd /home/ubuntu/liuyanban-demo/runtime && docker compose ps'
```

查看演示管理员凭据（仅在需要登录时执行）：

```sh
ssh tc-gz 'cat /home/ubuntu/liuyanban-demo/runtime/.demo-admin-credentials'
```

查看最终测试日志：

```sh
ssh tc-gz 'cat /home/ubuntu/liuyanban-demo/qa/prelaunch-latest.log'
ssh tc-gz 'cat /home/ubuntu/liuyanban-demo/qa/http-prelaunch-latest.log'
```

重启演示应用：

```sh
ssh tc-gz 'cd /home/ubuntu/liuyanban-demo/runtime && docker compose restart'
```

## 与正式生产环境的差异

本次是 Ubuntu + Docker 演示部署，用于验证部署包和业务行为；最终 Windows 10 x64 + phpStudy 的传统部署仍需单独完成冒烟测试。演示站点位于 HTTPS 反向代理之后，目前应用按安全默认值只信任直连地址，不解析 `X-Forwarded-For`，因此来源 IP 可能记录为代理/容器地址。正式需要保留访客真实 IP 时，应先明确可信代理地址，再配置可信代理解析，不能无条件信任转发头。
