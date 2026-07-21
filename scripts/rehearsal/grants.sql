CREATE USER 'liuyanban_rehearsal'@'%' IDENTIFIED BY 'rehearsal-app-password';

GRANT SELECT ON `zhidui_nantong`.`admin`
TO 'liuyanban_rehearsal'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON `zhidui_nantong`.`liuyan_message`
TO 'liuyanban_rehearsal'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON `zhidui_nantong`.`liuyan_reply`
TO 'liuyanban_rehearsal'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON `zhidui_nantong`.`liuyan_operation_log`
TO 'liuyanban_rehearsal'@'%';

FLUSH PRIVILEGES;
