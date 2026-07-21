# Windows 10 + phpStudy 部署说明

本部署包用于独立留言板，不覆盖老网站文件，不修改老网站 PHP 配置，不复用老网站 Session。

目标地址：`http://IP:新端口/liuyanban/`

## 1. 部署前备份和确认

1. 备份 `zhidui_nantong` 数据库，确认备份可以恢复。
2. 记录老系统 `admin` 表真实字段、密码算法和允许登录的 `user_type`。
3. 确认新端口未被占用，并完成 Windows 防火墙放行。
4. 在 phpStudy 中准备独立 Apache 2.4.39、PHP 7.3.4 站点，不改老网站实例。
5. 确认 PHP 已启用 `PDO`、`pdo_mysql`、`session`、`json`、`openssl`。

## 2. 导入应用文件

1. 解压部署包。
2. 将解压后的 `liuyanban` 目录复制到：

   ```text
   D:/phpstudy_pro/WWW/liuyanban
   ```

3. 确认 `public/` 是唯一映射到 Web 的目录；`app/`、`config/`、`database/`、`deploy/` 不得直接暴露。
4. 为 Apache/PHP 运行账号授予 `var/log` 和 `var/session` 的写入权限。
5. 通过 `MANIFEST.sha256` 核对部署文件没有在传输中损坏。

## 3. 初始化数据库

不要在生产环境执行开发用的 `database/migrations/001_init.sql` 或任何 seed 文件，它们包含测试 `admin` 表逻辑。

全新部署只执行：

```bat
mysql -uroot -p zhidui_nantong < database\production\001_liuyan_schema.sql
```

该脚本只创建：

- `liuyan_message`
- `liuyan_reply`
- `liuyan_operation_log`

不会创建或修改 `admin` 表。

如果是从不含回复草稿字段的旧版留言板升级，在完整备份后单独执行：

```bat
mysql -uroot -p zhidui_nantong < database\migrations\002_reply_status.sql
```

全新部署已经包含最终表结构，不要执行该增量迁移；同一数据库也不得重复执行。

复制并修改 `deploy/mysql/least-privilege.sql.example`，由数据库管理员创建独立应用账号。应用账号对 `admin` 只有 `SELECT`，对 `liuyan_*` 表具有业务读写权限。

## 4. 配置 phpStudy Apache/PHP

1. 复制 `deploy/apache/phpstudy-liuyanban.conf.example`，修改：
   - 新端口
   - 应用绝对路径
   - 数据库账号和密码
   - 管理员字段映射、允许类型和密码驱动
2. 将配置加入留言板独立 Apache 2.4.39 实例。
3. 确认 Apache 已加载 `alias`、`env`、`headers` 和 PHP 处理模块。
4. 参考 `deploy/php/php.ini-recommended.ini` 配置留言板独立 PHP 7.3.4。
5. 重启留言板独立 Apache/PHP，不重启或修改老网站实例。

`deploy/env.production.example` 只是环境变量清单，应用不会自动读取 `.env` 文件；phpStudy 部署应通过 Apache `SetEnv` 或等价的站点外部配置注入。

## 5. 命令行验收

在应用根目录执行：

```bat
D:\phpstudy_pro\Extensions\php\php7.3.4nts\php.exe deploy\verify.php
```

所有检查必须显示 `[PASS]`，包括 PHP 7.3.4、MySQL 5.7.26、扩展、业务表、管理员字段映射、时区和可写目录。

## 6. 浏览器验收

1. 访问 `http://IP:新端口/liuyanban/`，确认样式和脚本正常。
2. 访问 `http://IP:新端口/liuyanban/admin/`，使用经授权的老管理员账号登录。
3. 提交一条匿名测试留言，确认初始状态为待审核。
4. 审核通过后确认公开展示。
5. 保存回复草稿，确认公开页不展示草稿；发布后确认展示实际发布时间。
6. 测试隐藏、恢复、软删除和回收站。
7. 检查操作日志和 Apache/PHP 日志。

## 7. 备份、升级和回滚

- 每次升级前备份数据库、应用目录和 Apache 站点配置。
- 数据库迁移按版本顺序单次执行，不重复执行 `ALTER TABLE` 迁移。
- 回滚应用时恢复上一版应用目录和站点配置。
- 如果升级包含不可逆数据库变更，必须同时使用升级前数据库备份回滚。
- 日常备份至少包含 `liuyan_*` 表；`admin` 表继续沿用老系统既有备份策略。
