# PHP Garmin Connect API

[English](README.md) | [中文](README_CN.md)

# 目录
- [目录](#目录)
- [描述](#描述)
- [安装](#安装)
- [版本支持](#版本支持)
- [HTTP 客户端配置](#http-客户端配置)
- [示例](#示例)
- [使用方法](#使用方法)
  - [获取授权链接](#获取授权链接)
  - [获取令牌凭据](#获取令牌凭据)
  - [获取 Garmin 用户 ID](#获取-garmin-用户-id)
  - [回填活动数据](#回填活动数据)
  - [注销注册](#注销注册)
  - [可用方法](#可用方法)
    - [获取活动摘要](#获取活动摘要)
    - [回填活动数据](#回填活动数据-1)

# 描述
PHP 库，用于连接和使用 Garmin 健康 API

# 版本支持
本库现在支持 **中文版** 和 **国际版** Garmin Connect API：

- **国际版**（默认）：使用 `connectapi.garmin.com` 和 `healthapi.garmin.com`
- **中文版**：使用 `connectapi.garmin.cn`（OAuth）和 `gcs-wellness.garmin.cn`（健康 REST API）

## 版本使用示例

### 为不同版本创建 API 实例
```php
use Stoufa\GarminApi\GarminApi;

$config = [
    'identifier' => getenv('GARMIN_KEY'),
    'secret' => getenv('GARMIN_SECRET'),
    'callback_uri' => getenv('GARMIN_CALLBACK_URI')
];

// 国际版（默认）
$internationalApi = new GarminApi($config, GarminApi::VERSION_INTERNATIONAL);

// 中文版
$chineseApi = new GarminApi($config, GarminApi::VERSION_CHINESE);
```

### 动态版本切换
```php
$api = new GarminApi($config);

// 切换到中文版
$api->useChineseVersion();

// 切换到国际版
$api->useInternationalVersion();

// 获取当前配置
echo "当前版本: " . $api->getVersion();
echo "API URL: " . $api->getApiUrl();
echo "用户 API URL: " . $api->getUserApiUrl();
echo "授权 URL: " . $api->getAuthUrl();
```

### 版本特定的端点
- **中文版**：
  - OAuth API URL: `https://connectapi.garmin.cn/`（用于请求/访问令牌）
  - 健康 REST API URL: `https://gcs-wellness.garmin.cn/wellness-api/rest/`（用于活动和用户数据）
  - 授权 URL: `http://connect.garmin.cn/oauthConfirm`
  - 工具 URL: `https://healthtools.garmin.cn/tools/login`（数据查看器/回填/Ping）

- **国际版**：
  - API URL: `https://connectapi.garmin.com/`
  - 用户 API URL: `https://healthapi.garmin.com/wellness-api/rest/`
  - 授权 URL: `http://connect.garmin.com/oauthConfirm`

### 向后兼容性
现有代码无需任何更改即可继续工作，因为默认使用国际版本。

# HTTP 客户端配置

本库支持自定义 HTTP 客户端配置，以处理各种网络场景，包括代理设置、超时、SSL 验证和自定义标头。

## 配置选项

您可以使用以下任何 Guzzle HTTP 客户端选项来配置 HTTP 客户端：

- `timeout` - 请求超时时间（秒）（默认：30）
- `connect_timeout` - 连接超时时间（秒）（默认：10）
- `proxy` - 代理服务器配置
- `verify` - SSL 证书验证（默认：true）
- `headers` - 应用于所有请求的默认标头
- `allow_redirects` - 控制重定向行为
- `cookies` - Cookie jar 配置
- `debug` - 启用调试输出

## 使用示例

### 1. 通过构造函数配置

```php
use Stoufa\GarminApi\GarminApi;

$config = [
    'identifier' => getenv('GARMIN_KEY'),
    'secret' => getenv('GARMIN_SECRET'),
    'callback_uri' => getenv('GARMIN_CALLBACK_URI')
];

$httpConfig = [
    'timeout' => 60,
    'connect_timeout' => 15,
    'proxy' => 'http://proxy.example.com:8080',
    'verify' => false,
    'headers' => [
        'User-Agent' => 'My Custom App/1.0'
    ]
];

$server = new GarminApi($config, GarminApi::VERSION_INTERNATIONAL, $httpConfig);
```

### 2. 使用 setter 方法配置

```php
$server = new GarminApi($config);
$server->setHttpClientConfig([
    'timeout' => 45,
    'proxy' => 'http://proxy.example.com:8080',
    'headers' => [
        'User-Agent' => 'My Custom App/1.0'
    ]
]);
```

### 3. 获取当前配置

```php
$currentConfig = $server->getHttpClientConfig();
print_r($currentConfig);
```

### 4. 常见用例

#### 为慢速网络设置自定义超时
```php
$server->setHttpClientConfig([
    'timeout' => 120,
    'connect_timeout' => 30
]);
```

#### 配置代理服务器
```php
$server->setHttpClientConfig([
    'proxy' => 'http://username:password@proxy.example.com:8080'
]);
```

#### 禁用 SSL 验证（用于开发）
```php
$server->setHttpClientConfig([
    'verify' => false
]);
```

#### 设置自定义 User-Agent
```php
$server->setHttpClientConfig([
    'headers' => [
        'User-Agent' => 'MyApp/1.0 (contact@example.com)'
    ]
]);
```

#### 启用调试模式
```php
$server->setHttpClientConfig([
    'debug' => true
]);
```

库发出的所有 HTTP 请求（OAuth 认证、API 调用等）都将使用自定义配置。

# 安装
```
composer require jinchun/php-garmin-connect-api
```

# 示例

请查看 [示例](./examples/README.md) 文件夹以获取完整的使用示例，包括[版本切换示例](./examples/version_switching_example.php)。

# 使用方法

## 获取授权链接
```php
use Stoufa\GarminApi\GarminApi;

try
{
    $config = array(
        'identifier'     => getenv('GARMIN_KEY'),
        'secret'         => getenv('GARMIN_SECRET'),
        'callback_uri'   => getenv('GARMIN_CALLBACK_URI')
    );

    $server = new GarminApi($config);

    // 从服务器检索临时凭据
    $temporaryCredentials = $server->getTemporaryCredentials();

    // 将临时凭据保存在会话中以便稍后检索授权令牌
    $_SESSION['temporaryCredentials'] = $temporaryCredentials;

    // 获取授权链接
    $link = $server->getAuthorizationUrl($temporaryCredentials);
}
catch (\Throwable $th)
{
    // 在这里捕获您的异常
}
```

## 获取令牌凭据

用户成功连接 Garmin 账户后，将重定向到 callback_uri。"oauth_token" 和 "oauth_verifier" 应该在 $_GET 中可用。

```php
try
{
    $config = array(
        'identifier'     => getenv('GARMIN_KEY'),
        'secret'         => getenv('GARMIN_SECRET'),
        'callback_uri'   => getenv('GARMIN_CALLBACK_URI')
    );

    $server = new GarminApi($config);

    // 检索我们之前保存的临时凭据
    $temporaryCredentials = $_SESSION['temporaryCredentials'];

    // 我们现在将从服务器检索令牌凭据
    $tokenCredentials = $server->getTokenCredentials($temporaryCredentials, $_GET['oauth_token'], $_GET['oauth_verifier']);

}
catch (\Throwable $th)
{
    // 在这里捕获您的异常
}
```

## 获取 Garmin 用户 ID

```php
$userId = $server->getUserUid($tokenCredentials);
```

## 回填活动数据

当您首次连接 Garmin 账户并获取令牌凭据时，您将无法获取以前的活动，因为 Garmin 不会给您提供比令牌凭据更早的活动。相反，您需要使用回填方法来用以前的活动（不超过一个月）填充您的令牌。

```php
// 回填 7 天前的活动
$params = [
    'summaryStartTimeInSeconds' => strtotime("-7 days", time()),
    'summaryEndTimeInSeconds' => time()
];
$server->backfillActivitySummary($tokenCredentials, $params);
```

## 注销注册
```php
$server->deleteUserAccessToken($tokenCredentials);
```

## 可用方法（目前）

### 获取活动摘要
```php
$params = [
    'uploadStartTimeInSeconds' => 1598814036, // utc 时间（秒）
    'uploadEndTimeInSeconds' => 1598900435 // utc 时间（秒）
];

// 活动摘要
$server->getActivitySummary($tokenCredentials, $params);

// 手动活动摘要
$server->getManuallyActivitySummary($tokenCredentials, $params);

// 活动详情摘要
$server->getActivityDetailsSummary($tokenCredentials, $params);

// 用户指标（包括 VO2 max 和 fitness age）
$server->getUserMetrics($tokenCredentials, $params);
```

### 回填活动数据
```php
// 对于回填参数，可以使用上传开始时间
$params = [
    'uploadStartTimeInSeconds' => 1598814036, // utc 时间（秒）
    'uploadEndTimeInSeconds' => 1598900435 // utc 时间（秒）
];
// 或使用摘要开始时间
$params = [
    'summaryStartTimeInSeconds' => 1598814036, // utc 时间（秒）
    'summaryEndTimeInSeconds' => 1598900435 // utc 时间（秒）
];

// 回填活动摘要
$server->backfillActivitySummary($tokenCredentials, $params);

// 回填每日活动摘要
$server->backfillDailySummary($tokenCredentials, $params);

// 回填时段摘要
$server->backfillEpochSummary($tokenCredentials, $params);

// 回填活动详情摘要
$server->backfillActivityDetailsSummary($tokenCredentials, $params);

// 回填睡眠摘要
$server->backfillSleepSummary($tokenCredentials, $params);

// 回填身体成分摘要
$server->backfillBodyCompositionSummary($tokenCredentials, $params);

// 回填压力详情摘要
$server->backfillStressDetailsSummary($tokenCredentials, $params);

// 回填用户指标摘要
$server->backfillUserMetricsSummary($tokenCredentials, $params);

// 回填血氧摘要
$server->backfillPulseOxSummary($tokenCredentials, $params);

// 回填呼吸摘要
$server->backfillRespirationSummary($tokenCredentials, $params);
```

### 获取用户指标（VO2 Max）

`getUserMetrics` 方法用于检索用户健身指标，包括 VO2 max 和 fitness age。此方法需要同时传递 `uploadStartTimeInSeconds` 和 `uploadEndTimeInSeconds` 参数，最大时间窗口为 24 小时。

```php
// 获取特定 24 小时期间的用户指标
$params = [
    'uploadStartTimeInSeconds' => 1726876800, // Unix 时间戳
    'uploadEndTimeInSeconds' => 1726963200    // Unix 时间戳（最多 24 小时后）
];

$userMetrics = $server->getUserMetrics($tokenCredentials, $params);

// 响应将包含 VO2 max 数据和其他健身指标
// 响应结构示例：
// {
//   "userMetrics": [
//     {
//       "vo2Max": 45.2,
//       "fitnessAge": 28,
//       "timestamp": "2023-09-21T12:00:00Z"
//     }
//   ]
// }
```

**重要提示**：用户指标查询的时间窗口不能超过 24 小时。