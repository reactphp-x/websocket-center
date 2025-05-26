# ReactPHP WebSocket Center

一个基于ReactPHP的分布式WebSocket中心管理系统，支持WebSocket服务实例注册到多个注册中心，并支持WebSocket服务实例通过任一注册中心进行服务调用。

## 特性

- 🚀 基于ReactPHP的高性能异步WebSocket服务
- 🔄 分布式WebSocket服务注册中心
- 💬 支持多服务实例的消息广播和路由
- 🌐 RESTful API接口支持
- 📱 Web前端管理界面
- 🔧 灵活的中间件支持

## 安装

使用Composer安装：

```bash
composer require reactphp-x/websocket-center
```

## 快速开始

### 1. 启动注册中心

每个注册中心实例都包含注册服务和对应的HTTP API服务。可以启动多个注册中心实例形成集群。

```bash
# 启动第一个注册中心
REGISTER_CENTER_PORT=8010 HTTP_PORT=8011 OTHER_REGISTER_CENTER_PORT=8012,8014 php examples/register.php

# 启动第二个注册中心
REGISTER_CENTER_PORT=8012 HTTP_PORT=8013 php examples/register.php

# 启动第三个注册中心
REGISTER_CENTER_PORT=8014 HTTP_PORT=8015 php examples/register.php
```

环境变量说明：
- `REGISTER_CENTER_PORT`: 注册中心服务端口（建议使用偶数端口：8010, 8012, 8014...）
- `HTTP_PORT`: 对应的HTTP API服务端口（建议使用奇数端口：8011, 8013, 8015...）

### 2. 启动WebSocket服务

可以启动多个WebSocket服务实例：

```bash
# 启动第一个WebSocket服务实例
REGISTER_CENTER_PORT=8010 PORT=8090 php examples/websocket.php

# 启动第二个WebSocket服务实例
REGISTER_CENTER_PORT=8010 PORT=8091 php examples/websocket.php
```

环境变量说明：
- `REGISTER_CENTER_PORT`: 注册中心端口（需与注册中心保持一致）
- `PORT`: WebSocket服务端口
- `TOKEN`: 可选的访问令牌，多个令牌用逗号分隔

### 3. 启动前端演示界面

```bash
php -S 0.0.0.0:9013 examples/index.html
```

然后访问 http://localhost:9013 查看演示界面。

前端界面支持两种连接方式：
- **HTTP API连接** - 通过注册中心的HTTP API端口（如8011, 8013等）进行服务管理
- **WebSocket连接** - 直接连接到WebSocket服务端口（如8090, 8091等）进行实时通信

## 项目架构

```
                    ┌─────────────────┐
                    │   Web Frontend  │
                    │   (index.html)  │
                    └─────────┬───────┘
                              │
        ┌─────────────────────┼─────────────────────┐
        │                     │                     │
    ┌───▼───┐             ┌───▼───┐             ┌───▼───┐
    │HTTP   │             │HTTP   │             │HTTP   │
    │API 1  │    ...      │API 2  │    ...      │API N  │
    │(8011) │             │(8013) │             │(801Y) │
    └───┬───┘             └───┬───┘             └───┬───┘
        │                     │                     │
    ┌───▼───┐             ┌───▼───┐             ┌───▼───┐
    │Reg.   │             │Reg.   │             │Reg.   │
    │Ctr 1  │             │Ctr 2  │      ...    │Ctr N  │
    │(8010) │             │(8012) │             │(801X) │
    └───────┘             └───────┘             └───────┘
        │                     │                     │
        └─────────────────────┼─────────────────────┘
                              │
        ┌─────────────────────┼─────────────────────┐
        │                     │                     │
    ┌───▼───┐             ┌───▼───┐             ┌───▼───┐
    │WS     │◄───────────►│WS     │◄───────────►│WS     │
    │Svc 1  │             │Svc 2  │      ...    │Svc N  │
    │(8090) │             │(8091) │             │(809X) │
    └───┬───┘             └───┬───┘             └───┬───┘
        │                     │                     │
        └─────────────────────┼─────────────────────┘
                              │
                    ┌─────────▼───────┐
                    │   Web Frontend  │
                    │  WebSocket连接   │
                    │  (直连WS服务)    │
                    └─────────────────┘
```

**架构特点：**

1. **双重连接模式** - Web Frontend既可以通过HTTP API调用WebSocket服务，也可以直接连接WebSocket服务端口进行实时通信
2. **HTTP API与注册中心一对一** - 每个注册中心实例都有对应的HTTP API服务，提供独立的管理接口
3. **多注册中心集群** - 可以部署多个注册中心实例，每个运行在不同端口，形成分布式集群
4. **跨注册中心连接** - 每个WebSocket服务可以同时连接到多个注册中心，实现多点注册
5. **服务调用** - 通过多个注册中心实现WebSocket服务集群的调用
6. **负载均衡** - 多个WebSocket服务实例自动分担客户端连接负载，可以通过用户ID取余连接到对应Websocket服务实例
7. **高可用性** - 单个注册中心或HTTP API故障不影响其他节点的正常运行

## API接口

API支持GET和POST请求，但`token`参数始终通过查询字符串传递。事件名称通过`event`参数指定，其他参数可以通过查询字符串（GET）或请求体（POST）传递。

### 基础语法

**GET请求：**
```http
GET http://localhost:8011/?event={eventName}&token={your_token}&{paramName}[{subKey}]={value}
```

**POST请求：**
```http
POST http://localhost:8011/?event={eventName}&token={your_token}
Content-Type: application/json

{
    "paramName": {
        "subKey": "value"
    }
}
```

### 返回数据结构

所有API调用返回统一的JSON格式：

```json
{
    "code": 0,
    "msg": "ok", 
    "extra": [],
    "master_ids": [
        "98b95842c9e26e8fb113cf8342526f5d",
        "de5d37e88761c59d61bd6b38822de96f"
    ],
    "data": {
        "eventName": {
            "98b95842c9e26e8fb113cf8342526f5d": "节点1的返回数据",
            "de5d37e88761c59d61bd6b38822de96f": "节点2的返回数据"
        }
    }
}
```

**字段说明：**
- `code`: 状态码，0表示成功，非0表示失败
- `msg`: 状态消息
- `extra`: 额外信息数组
- `master_ids`: 当前在线的WebSocket服务节点ID列表
- `data`: 各个节点的返回数据，key为节点ID，value为该节点的执行结果

### 连接管理

#### 获取连接统计

```http
GET http://localhost:8011/?event=getConnectionCount&token=your_token
```

#### 获取所有用户ID

```http
GET http://localhost:8011/?event=getIds&token=your_token
```

#### 获取所有连接ID

```http
GET http://localhost:8011/?event=get_Ids&token=your_token
```

#### 检查用户是否在线

```http
GET http://localhost:8011/?event=isOnlineId&token=your_token&isOnlineId[id]=user123
```

#### 检查连接是否在线

```http
GET http://localhost:8011/?event=isOnline_Id&token=your_token&isOnline_Id[_id]=connection123
```

### 用户ID绑定

#### 绑定用户ID到连接

**GET请求：**
```http
GET http://localhost:8011/?event=bindId&token=your_token&bindId[id]=user123&bindId[_id]=connection123
```

**POST请求：**
```http
POST http://localhost:8011/?event=bindId&token=your_token
Content-Type: application/json

{
    "bindId": {
        "id": "user123",
        "_id": "connection123"
    }
}
```

**返回示例：**
```json
{
    "code": 0,
    "msg": "ok",
    "extra": [],
    "master_ids": ["98b95842c9e26e8fb113cf8342526f5d"],
    "data": {
        "bindId": {
            "98b95842c9e26e8fb113cf8342526f5d": 1
        }
    }
}
```

#### 解绑用户ID

```http
GET http://localhost:8011/?event=unBindId&token=your_token&unBindId[id]=user123
```

#### 解绑连接ID

```http
GET http://localhost:8011/?event=unBind_Id&token=your_token&unBind_Id[_id]=connection123
```

### 分组管理

#### 获取所有分组ID

```http
GET http://localhost:8011/?event=getGroupIds&token=your_token
```

#### 用户加入分组

```http
GET http://localhost:8011/?event=joinGroupById&token=your_token&joinGroupById[groupId]=room1&joinGroupById[id]=user123
```

#### 连接加入分组

```http
GET http://localhost:8011/?event=joinGroupBy_Id&token=your_token&joinGroupBy_Id[groupId]=room1&joinGroupBy_Id[_id]=connection123
```

#### 用户离开分组

```http
GET http://localhost:8011/?event=leaveGroupById&token=your_token&leaveGroupById[groupId]=room1&leaveGroupById[id]=user123
```

#### 连接离开分组

```http
GET http://localhost:8011/?event=leaveGroupBy_Id&token=your_token&leaveGroupBy_Id[groupId]=room1&leaveGroupBy_Id[_id]=connection123
```

#### 用户离开所有分组

```http
GET http://localhost:8011/?event=leaveAllGroupById&token=your_token&leaveAllGroupById[id]=user123
```

#### 连接离开所有分组

```http
GET http://localhost:8011/?event=leaveAllGroupBy_Id&token=your_token&leaveAllGroupBy_Id[_id]=connection123
```

#### 获取用户的分组列表

```http
GET http://localhost:8011/?event=getGroupIdsById&token=your_token&getGroupIdsById[id]=user123
```

#### 获取连接的分组列表

```http
GET http://localhost:8011/?event=getGroupIdsBy_Id&token=your_token&getGroupIdsBy_Id[_id]=connection123
```

#### 检查用户是否在分组中

```http
GET http://localhost:8011/?event=isInGroupById&token=your_token&isInGroupById[groupId]=room1&isInGroupById[id]=user123
```

#### 检查连接是否在分组中

```http
GET http://localhost:8011/?event=isInGroupBy_Id&token=your_token&isInGroupBy_Id[groupId]=room1&isInGroupBy_Id[_id]=connection123
```

#### 获取分组成员数量

```http
GET http://localhost:8011/?event=getGroupIdCount&token=your_token&getGroupIdCount[groupId]=room1
```

#### 获取分组连接数量

```http
GET http://localhost:8011/?event=getGroup_IdCount&token=your_token&getGroup_IdCount[groupId]=room1
```

### 消息发送

#### 发送消息给指定用户

**GET请求：**
```http
GET http://localhost:8011/?event=sendMessageToId&token=your_token&sendMessageToId[id]=user123&sendMessageToId[msg]=Hello%20World
```

**POST请求：**
```http
POST http://localhost:8011/?event=sendMessageToId&token=your_token
Content-Type: application/json

{
    "sendMessageToId": {
        "id": "user123",
        "msg": "Hello World"
    }
}
```

**返回示例：**
```json
{
    "code": 0,
    "msg": "ok",
    "extra": [],
    "master_ids": ["98b95842c9e26e8fb113cf8342526f5d", "de5d37e88761c59d61bd6b38822de96f"],
    "data": {
        "sendMessageToId": {
            "98b95842c9e26e8fb113cf8342526f5d": 1,
            "de5d37e88761c59d61bd6b38822de96f": 0
        }
    }
}
```

*注：返回值表示每个节点成功发送的消息状态*

#### 发送消息给指定连接

```http
GET http://localhost:8011/?event=sendMessageTo_Id&token=your_token&sendMessageTo_Id[_id]=connection123&sendMessageTo_Id[msg]=Hello%20World
```

#### 发送消息给分组

```http
GET http://localhost:8011/?event=sendToGroup&token=your_token&sendToGroup[groupId]=room1&sendToGroup[msg]=Hello%20Group
```

#### 广播消息给所有连接

**GET请求：**
```http
GET http://localhost:8011/?event=broadcast&token=your_token&broadcast[msg]=Hello%20Everyone
```

**POST请求：**
```http
POST http://localhost:8011/?event=broadcast&token=your_token
Content-Type: application/json

{
    "broadcast": {
        "msg": "Hello Everyone"
    }
}
```

**返回示例：**
```json
{
    "code": 0,
    "msg": "ok",
    "extra": [],
    "master_ids": ["98b95842c9e26e8fb113cf8342526f5d", "de5d37e88761c59d61bd6b38822de96f"],
    "data": {
        "broadcast": {
            "98b95842c9e26e8fb113cf8342526f5d": 0,
            "de5d37e88761c59d61bd6b38822de96f": 0.
        }
    }
}
```

*注：返回值表示每个节点广播到的连接数量*

#### 发送消息给用户所在的所有分组

```http
GET http://localhost:8011/?event=sendMessageToGroupByOnlyId&token=your_token&sendMessageToGroupByOnlyId[id]=user123&sendMessageToGroupByOnlyId[msg]=Hello%20Groups
```

#### 发送消息给连接所在的所有分组

```http
GET http://localhost:8011/?event=sendMessageToGroupByOnly_Id&token=your_token&sendMessageToGroupByOnly_Id[_id]=connection123&sendMessageToGroupByOnly_Id[msg]=Hello%20Groups
```

### 批量操作

可以在单个请求中执行多个操作，用逗号分隔事件名称：

**GET请求：**
```http
GET http://localhost:8011/?event=getConnectionCount,getIds&token=your_token
```

**POST请求：**
```http
POST http://localhost:8011/?event=getConnectionCount,getIds&token=your_token
Content-Type: application/json

{
    "getConnectionCount": {},
    "getIds": {}
}
```

**返回示例：**
```json
{
    "code": 0,
    "msg": "ok",
    "extra": [],
    "master_ids": ["98b95842c9e26e8fb113cf8342526f5d", "de5d37e88761c59d61bd6b38822de96f"],
    "data": {
        "getConnectionCount": {
            "98b95842c9e26e8fb113cf8342526f5d": 5,
            "de5d37e88761c59d61bd6b38822de96f": 3
        },
        "getIds": {
            "98b95842c9e26e8fb113cf8342526f5d": ["user1", "user2"],
            "de5d37e88761c59d61bd6b38822de96f": ["user3"]
        }
    }
}
```

### 错误处理

当请求失败时，返回结构如下：

```json
{
    "code": 0,
    "msg": "ok",
    "extra": [],
    "master_ids": ["98b95842c9e26e8fb113cf8342526f5d"],
    "data": {
        "getIds": {
            "98b95842c9e26e8fb113cf8342526f5d": [
                "code": 1,
                "msg": "xxx",
                "data": []
            ],
        }
    }
}
```

常见错误码：
- `1`: token错误或事件参数错误
- 其他错误码根据具体业务逻辑定义

## 配置说明

### WebSocket服务配置

```php
<?php
require __DIR__ . '/../vendor/autoload.php';

use ReactphpX\WebsocketGroup\WebsocketGroupComponent;
use ReactphpX\WebsocketGroup\WebsocketGroupMiddleware;
use ReactphpX\ConnectionGroup\ConnectionGroup;
use ReactphpX\RegisterCenter\Master;
use ReactphpX\RegisterCenter\ServiceRegistry;

// 创建连接组
$connectionGroup = new ConnectionGroup;

// 注册服务到注册中心
ServiceRegistry::register('websocket', $connectionGroup, [
    'env' => 'prod',
]);

// 连接到注册中心
$master = new Master(logger: $logger);
$master->connectViaConnector('127.0.0.1', getenv('REGISTER_CENTER_PORT') ?: 8010);

// 启动WebSocket服务
$websocketGroupMiddleware = new WebsocketGroupMiddleware($connectionGroup);
$http = new React\Http\HttpServer(
    $websocketGroupMiddleware,
    new WebsocketMiddleware(new WebsocketGroupComponent($connectionGroup))
);
$socket = new React\Socket\SocketServer('0.0.0.0:' . getenv('PORT') ?: 8090);
$http->listen($socket);
```

### 注册中心配置

```php
<?php
require __DIR__ . '/../vendor/autoload.php';

use ReactphpX\RegisterCenter\Register;
use ReactphpX\WebsocketCenter\RegisterMiddleware;

// 创建注册中心
$center = new Register(getenv('REGISTER_CENTER_PORT') ?: 8010, $loop, $logger);
$center->start();

// 创建HTTP API服务
$http = new React\Http\HttpServer(new RegisterMiddleware($center));
$socket = new React\Socket\SocketServer('0.0.0.0:' . getenv('HTTP_PORT') ?: 8011);
$http->listen($socket);
```

## 事件处理

### 连接事件

```php
$connectionGroup->on('open', function ($conn, $request) use ($connectionGroup) {
    // 新连接建立时触发
    $connectionGroup->sendMessageTo_id($conn->_id, json_encode([
        'cmd' => 'open',
        '_id' => $conn->_id,
    ]));
});
```

### 消息事件

```php
$connectionGroup->on('message', function ($from, $msg) use ($connectionGroup) {
    // 收到消息时触发
    if ($msg == 'ping') {
        $connectionGroup->sendMessageTo_id($from->_id, json_encode([
            'cmd' => 'pong',
            '_id' => $from->_id,
        ]));
    }
});
```

### 断开连接事件

```php
$connectionGroup->on('close', function ($conn, $reason) {
    // 连接断开时触发
    echo "Connection {$conn->_id} closed: {$reason}\n";
});
```

## 前端演示功能

演示界面提供以下功能：

1. **WebSocket连接管理**
   - 连接到WebSocket服务
   - 实时显示连接状态
   - 自动重连机制

2. **消息发送**
   - 发送广播消息到所有连接
   - 发送私聊消息到指定连接
   - 发送JSON格式消息

3. **服务管理**
   - 查看已注册的服务列表
   - 监控服务状态
   - 获取连接统计信息

4. **实时日志**
   - 显示所有WebSocket事件
   - 区分不同类型的日志消息
   - 自动滚动和清理

## 依赖项

- `reactphp-x/websocket-group`: WebSocket分组管理
- `reactphp-x/register-center`: 服务注册中心
- `monolog/monolog`: 日志记录


## 许可证

MIT License

## 贡献

欢迎提交Issue和Pull Request来改进这个项目。

## 作者

- **wpjscc** - *Initial work* - [wpjscc@gmail.com](mailto:wpjscc@gmail.com)

## 相关链接

- [ReactPHP](https://reactphp.org/)
- [WebSocket RFC 6455](https://tools.ietf.org/html/rfc6455) 