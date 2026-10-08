# Microsoft 登录

## 业务需求

后台通过 Microsoft Entra 的 OpenID Connect (OIDC) 完成组织账号登录。只有已经绑定到后台账号的微软身份才能直接登录；其他组织账号只能提交访问申请，不能凭邮箱自动关联已有账号，也不会自动创建后台账号。个人微软账号不受支持。

这是一个需要点击登录入口的 OIDC 流程，不会在打开登录页时自动探测 Entra 会话。若浏览器已有 Entra 会话，微软可能复用该会话而不再次要求输入凭据；这属于单点登录 (SSO) 的效果，不等于后台自动登录。退出后台只结束本地会话，不会退出 Entra。

## 实现设计

**配置与回调。** 应用程序 ID、租户和启用状态由 CodeIgniter Settings 保存，客户端密钥只从环境配置读取。OIDC 回调地址使用应用默认语言 `en`；发起授权时的语言保存在会话中，回调后恢复。OAuth state 和 nonce 在会话中校验，流程有效期为 10 分钟。

**绑定与登录。**

已有本地后台账号的用户先用密码登录，在“账号设置”中输入当前密码，点击“绑定微软账号”，然后完成 Entra 授权。成功后将该微软身份绑定到当前账号；一个后台账号只允许绑定一个微软身份。退出本地会话后，可在登录页点击“微软登录”，使用已绑定身份登录原后台账号。

未绑定的组织账号从登录页点击“微软登录”并完成 Entra 授权后，会提交访问申请，而不是直接获得后台权限。拥有 `users.manage-admins` 权限的审批人在微软登录设置页查看申请，**通过可信的线下渠道确认申请人的身份**后，才能选择一个已有的非 `superadmin` 后台用户（包括普通 `user` 组用户）批准绑定，或拒绝申请。`superadmin` 只能通过账号设置自行绑定微软身份。页面显示的邮箱仅供参考，不应作为身份核验依据；实际绑定标识是小写的 `tid:oid`（租户 ID 与对象 ID）。

申请有效期为 24 小时；每个租户最多 10 条、全局最多 100 条待审申请。拒绝会阻止同一身份在该申请过期前再次提交；过期后可重新申请。审批人可撤销非 `superadmin` 用户的绑定；`superadmin` 只能在微软登录设置页撤销自己的绑定。撤销后该微软身份无法继续登录，但**已经建立的后台会话不会立即失效**。被 Shield 封禁（`ban`）的账号不可绑定或通过微软登录；无需 `admin.access` 权限，这里也不使用 Shield 的 `active` 邮件激活标志。

## 运行约束

### 初始配置

1. 按 [快速启动](../README.md#快速启动) 安装依赖、执行迁移并创建首个本地 `superadmin`。保留本地登录入口，以便微软配置不可用时管理后台。
2. 在 Microsoft Entra 注册应用，选择支持**任意组织目录中的账号**（若只允许一个租户，则按组织的部署要求配置）。在“身份验证”中添加 **Web** 平台的重定向 URI。本地默认地址为 `https://localhost/en/microsoft/callback`；线上使用实际 HTTPS 域名及同一 `/en/microsoft/callback` 路径。协议、域名、路径和尾部斜杠必须与 Entra 中登记的值完全一致。
3. 在 `admin/.env` 中设置客户端密钥的**值**（不是密钥 ID），不要提交到版本库或写进数据库：

   ```dotenv
   microsoftoauth.clientSecret = <client-secret-value>
   ```

   配置键的 `microsoftoauth` 必须全小写；CodeIgniter 使用配置类的全小写短名称查找环境变量。修改运行中服务的 `.env` 后，重启 FrankenPHP worker：

   ```sh
   docker compose -f docker/docker-compose.yaml restart geminus-admin
   ```

4. 使用有 `admin.settings` 权限的账号打开 `/en/admin/settings/microsoft`，填写 Entra 的应用程序（客户端）ID、租户，并打开“启用微软登录”。租户填 `organizations` 可接受任意组织目录，填租户 GUID 则仅接受该组织。

本地环境的迁移命令（在仓库根目录运行）：

```sh
docker compose -f docker/docker-compose.yaml exec geminus-admin php spark migrate --all
```

### 验证

在仓库根目录运行相关自动化测试：

```sh
docker compose -f docker/docker-compose.yaml exec -T geminus-admin sh -c 'cd /app && vendor/bin/phpunit --testsuite App --filter MicrosoftSettingsTest --no-coverage'
```

自动化测试覆盖配置、组织账号声明规则、回调失败、绑定、申请、审批、拒绝和撤销，不连接真实 Entra。上线前仍需在浏览器中走通完整链路：绑定一个受控组织账号，退出后台，再从登录页使用该账号登录；另用未绑定的受控组织账号确认只能提交申请，审批后才可登录。若要验证**跨应用 SSO**，在同一浏览器会话中先登录另一款使用同一 Entra 身份的应用，再首次进入本后台并点击微软登录；只测试本后台内退出后重新登录，不能证明跨应用 SSO。

### 故障排查

- `AADSTS50011`：检查 Entra 应用注册中的 **Web** 重定向 URI 是否与请求中显示的地址逐字一致，例如本地的 `https://localhost/en/microsoft/callback`。保存后从登录页或账号设置页重新发起授权，不要刷新旧回调。
- “无法完成微软登录”：检查开关、客户端 ID、租户与 `microsoftoauth.clientSecret` 是否已配置；修改 `.env` 后重启服务。异常详情见 `admin/writable/logs/`，不要把密钥或完整令牌贴入日志、工单或聊天。
- “当前密码不正确”：绑定必须使用当前后台账号的**本地密码**，不是微软密码。未绑定身份申请审批不需要已有后台账号的本地密码。
- 回调失败或过期：OAuth state 和 nonce 在会话中校验，流程有效期为 10 分钟；重新从入口开始，保持同一浏览器会话。
- 撤销后仍可访问：现有后台会话仍有效；先退出再测试微软登录。退出后台不会同时注销微软会话。