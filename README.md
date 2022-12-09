# LGDS PHP SDK

本 SDK 兼容 PHP 5.5+，部分功能依赖 curl扩展。

### 集成 SDK

#### 1. 使用 composer 集成:

```json
{
  "require": {
    "run-bigpig/lgds-php-sdk": "v1.0.0"
  }
}
```

#### 2. 初始化SDK

```php
require "vendor/run-bigpig/lgds-php-sdk/src/LgdsSdk.php";
```

在引入SDK 后，您需要创建 SDK 实例


```php
$lgds = new LgdsSdk(new Consumer("SERVER_URL","APP_ID","Aceess_Key","Secret_Key"));
```

SERVER_URL 为传输数据的 URL，APP_ID 为您的项目的 APP ID,Access_Key 为您的项目AK,Secret_Key 为您的项目SK

### 使用示例

#### 1. 发送事件

您可以调用track来上传事件，此处以玩家登录作为范例：

```php
// 玩家的设备ID
$device_id = "xxxxxxxxx"; 
//玩家的角色ID
$user_id = "213123sadasfdasfasf"; 
//游戏包名
$app_name = "app"
//平台
$platform="ios"
//区服
$server = 1
//事件名称
$event_name="login"
//附加属性
$properties = array();
$properties["level"] = 1;
$properties["ip"] = "114.114.114.114";

// 传入参数分别为，设备ID,用户ID，包名,平台,区服,事件名称,属性
$lgds->Track($device_id,$user_id,$app_name,$platform,$server,$event_name,$properties);

```

参数说明：

* 事件的名称只能以字母开头，可包含数字，字母和下划线“_”，长度最大为 50 个字符，对字母大小写不敏感
* 事件的属性是一个关联数组，其中每个元素代表一个属性
* 数组元素的 Key 值为属性的名称，为 string 类型，规定只能以字母开头，包含数字，字母和下划线“_”，长度最大为 50 个字符，对字母大小写不敏感
* 数组元素的 Value 值为该属性的值，支持支持 string、integer、float、boolean

#### 2. 立即提交数据

```php
// 立即提交数据到相应的接收端
$lgds->flush();
```

#### 3. 关闭 SDK

请在关闭服务器前调用本接口，以避免缓存内的数据丢失：

```php
// 关闭并退出 SDK
$lgds->close();
```
