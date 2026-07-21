# 网上匿名留言板

独立于老内网网站运行的 PHP 7.3留言板。老网站仅需增加一个指向以下地址的链接：

```text
http://IP:新端口/liuyanban/
```

## 开发环境

- Apache 2.4.39
- PHP 7.3.4 FPM
- MySQL 5.7.26
- Docker Compose

启动：

```sh
docker compose up -d --build
```

访问：

```text
http://localhost:8088/liuyanban/
```

验证容器实际版本：

```sh
docker compose exec apache httpd -v
docker compose exec php php -v
docker compose exec mysql mysql --version
```

开发数据库会自动创建示例留言和测试管理员：

```text
用户名：admin
密码：password
```

测试账号仅用于本地开发，禁止复制到生产环境。

## 当前页面范围

- 公开列表只展示审核通过、可见且未删除的留言。
- 支持全部、已回复和待回复筛选。
- 支持分页和管理员回复折叠查看。
- 匿名提交只包含标题、留言内容、安全验证和规则确认。
- 新留言默认进入待审核状态。
- 记录来源 IP，不在公开页面展示。
- 已实现 CSRF、基础频率限制、输出转义和安全响应头。
- 管理后台支持独立登录、组合筛选、分页和当前留言高亮。
- 审核、回复、展示和删除状态分别管理，回复发布不会自动公开留言。
- 支持回复草稿、回复历史、批量审核、批量隐藏和批量恢复。
- 删除采用可恢复的软删除，并提供回收站。
- 登录、审核、回复、隐藏、恢复和软删除等操作均写入操作日志。

管理后台访问地址：

```text
http://localhost:8088/liuyanban/admin/
```

本地测试账号仍为 `admin` / `password`，仅用于开发环境。

## 数据库迁移

全新 Docker 数据卷会按顺序执行：

```text
database/migrations/001_init.sql
database/migrations/002_reply_status.sql
database/seeds/001_development.sql
```

已有数据库升级到当前版本时，在备份后执行：

```sh
mysql -u数据库账号 -p zhidui_nantong < database/migrations/002_reply_status.sql
```

该迁移为回复表增加独立的“草稿/已发布”状态。每个版本化迁移只能执行一次。

## 验证

```sh
docker compose exec php php tests/smoke.php
docker compose exec php sh -lc "find app config public tests -name '*.php' -print0 | xargs -0 -n1 php -l"
```

上线前状态、分页和安全回归测试会生成 150 条标题以 `QA150-` 开头的开发数据，覆盖审核、回复、展示和回收站组合：

```sh
docker compose exec php php tests/seed_prelaunch.php
docker compose exec php php tests/prelaunch.php
sh tests/http_prelaunch.sh
```

生成器可重复执行，会先清理上一轮 `QA150-` 数据再重新生成。仅清理这批测试数据：

```sh
docker compose exec php php tests/seed_prelaunch.php --cleanup
```

`tests/http_prelaunch.sh` 默认检查 `http://127.0.0.1:8088/liuyanban`，也可将其他测试环境基础地址作为第一个参数传入。Windows/phpStudy 正式环境仍需按 `deploy/INSTALL-WINDOWS.md` 单独完成最终冒烟测试。

## 生产部署

Docker 仅用于开发验证。生产环境使用 Windows 10 x64、phpStudy、Apache 2.4.39、PHP 7.3.4 和 MySQL 5.7.26。

生产部署时必须：

1. 使用独立 Apache/PHP 实例和新端口。
2. 将 `/liuyanban/` 映射到本项目 `public/` 目录。
3. 从 `.env.example` 对应项配置数据库和基础路径，不提交真实密码。
4. 对老项目实际 `admin` 表只授予 `SELECT` 权限。
5. 替换测试管理员字段映射和密码校验驱动。

导出无开发密钥的传统部署包：

```sh
./scripts/export-deployment.sh
```

部署包包含 Windows/phpStudy 配置模板、只创建 `liuyan_*` 表的生产初始化 SQL、最小权限授权示例、环境检查脚本和完整安装说明。开发用 Docker 配置、测试管理员 seed、设计产物和测试代码不会进入部署包。

从导出的 ZIP 在全新隔离环境中演练导入：

```sh
./scripts/rehearse-deployment.sh dist/liuyanban-deployment-时间戳.zip
```

Windows 生产步骤详见 `deploy/INSTALL-WINDOWS.md`。
