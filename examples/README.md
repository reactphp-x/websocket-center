
## 运行注册中心1
```bash
REGISTER_CENTER_PORT=8010 HTTP_PORT=8011 OTHER_REGISTER_CENTER_PORT=8012 php examples/register.php
```

## 运行注册中心2
```bash
REGISTER_CENTER_PORT=8012 HTTP_PORT=8013 php examples/register.php
```


## 运行websocket服务


```bash
REGISTER_CENTER_PORT=8010 PORT=8090 php examples/websocket.php
```

##运行websocket服务

```bash
REGISTER_CENTER_PORT=8010 PORT=8091 php examples/websocket.php
```

## 运行前端

```
php -S 0.0.0.0:9013 examples/index.html
```