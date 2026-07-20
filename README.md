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

管理后台登录、审核、回复、隐藏、删除和操作日志页面将在后续阶段实现；数据表已预留。

## 生产部署

Docker 仅用于开发验证。生产环境使用 Windows 10 x64、phpStudy、Apache 2.4.39、PHP 7.3.4 和 MySQL 5.7.26。

生产部署时必须：

1. 使用独立 Apache/PHP 实例和新端口。
2. 将 `/liuyanban/` 映射到本项目 `public/` 目录。
3. 从 `.env.example` 对应项配置数据库和基础路径，不提交真实密码。
4. 对老项目实际 `admin` 表只授予 `SELECT` 权限。
5. 替换测试管理员字段映射和密码校验驱动。

