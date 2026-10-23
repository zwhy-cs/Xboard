# ClashMeta 订阅域名规则

`subscribe_domain_rule` 使用现有 `admin_setting()` 存储与缓存机制，无需数据库迁移。
仅影响 ClashMeta 的自动插入规则；Surge 和其他协议不受影响。

| 值 | 行为 |
| --- | --- |
| `PROXY`（未配置时默认） | 在规则首位插入 `DOMAIN,<订阅请求域名>,PROXY`，保持本 fork 的默认策略 |
| `DIRECT` | 在规则首位插入 `DOMAIN,<订阅请求域名>,DIRECT` |
| `OFF` | 不自动插入规则，完整保留模板规则（包括模板自行配置的同域名规则） |

沿用请求 Host 作为订阅域名，去除端口；没有 Host 时不插入。已存储的非法值回退到 `PROXY`。
`PROXY` 是策略名称，模板必须定义同名代理或策略组，并非 ClashMeta 内置策略。
仓库的 `default.clash.yaml` 未定义此名称；使用该模板时应配置合适的 `PROXY` 组，
或选择 `DIRECT` / `OFF`。本次不重命名策略组、不修改链式代理或模板。

## 配置接口

使用现有管理员身份认证和实际后台安全路径：

- `GET /api/v2/<secure_path>/config/fetch?key=subscribe`：在 `data.subscribe` 中返回该字段，默认 `PROXY`。
- `POST /api/v2/<secure_path>/config/save`，JSON 示例：`{"subscribe_domain_rule":"OFF"}`。

只接受大写 `DIRECT`、`PROXY`、`OFF`；空值、null、其他字符串和非字符串被拒绝。
旧管理客户端不提交该字段时不会覆盖设置。后台前端源码不在此仓库，本次未添加页面控件。

## 回归验证

安装项目 Composer 开发依赖后执行：

```sh
vendor/bin/phpunit --bootstrap vendor/autoload.php tests/Unit/Protocols/ClashMetaSubscriptionRuleTest.php
```

覆盖默认值、三种模式、非法存储值、Host 缺失/端口、规则顺序、空规则模板、
链式配置保持不变，以及管理接口字段校验和旧客户端省略字段。
