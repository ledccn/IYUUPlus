# Docker安装方法

### 1.找到你的【种子目录】

> qBittorrent的种子目录叫`BT_backup`，transmission的种子目录叫`torrents`。
> Linux系统qBittorrent种子目录搜索命令：`find / -name BT_backup`
> Linux系统transmission种子目录搜索命令：`find / -name torrents`
> Windows系统qBittorrent种子目录，通常在`C:\Users\你的用户名\AppData\Local\qBittorrent\BT_backup`

如果你知道具体路径，可以直接在下一步创建命令中指定。

### 2.拉取镜像、创建容器，运行

**全平台通用**

```
docker run -d \
  -v /你想在本地保存IYUU配置的路径/:/IYUU/db                    `# 冒号左边请修改为你想在本地保存IYUU配置文件的路径` \
  -v /qBittorrent的BT_backup文件夹在宿主机上的路径/:/BT_backup  `# 冒号左边请修改为你自己的路径，如不使用qb，可删除本行` \
  -v /transmission的torrents文件夹在宿主机上的路径/:/torrents   `# 冒号左边请修改为你自己的路径，如不使用tr，可删除本行` \
  -p 8787:8787 \
  --name IYUUPlus \
  --restart=always \
  iyuucn/iyuuplus
```

*请将`-v`命令中的路径修改为你的实际路径。若不使用transmission可删除transmission那一行，若不使用qBittorrent可删除qBittorrent那一行。*

*在容器中配置下载器时，下载器的`种子目录`请填入映射后的目录。*

*如果上述方式安装后，容器内网络异常，可以指定网络模式为host，使用`--network=host \`代替`-p 8787:8787 \`这一行。*

**以小钢炮为例，其具体命令如下**

```
docker run -d \
  -v /volume1/IYUU/db:/IYUU/db \
  -v /var/lib/transmission/torrents:/torrents \
  -v /var/lib/qbittorrent/.local/share/data/qBittorrent/BT_backup:/BT_backup \
  -p 8787:8787 \
  --name IYUUPlus \
  --restart always \
  iyuucn/iyuuplus
```

**命令解释**

| 参数        | 解释                                                         |
| ----------- | ------------------------------------------------------------ |
| `--name`    | 容器名字                                                     |
| `-v`        | 本地目录或文件:容器目录文件，资源挂载到容器。<br />请新建一个配置文件目录，然后映射进容器内`/IYUU/db`，容器内的数据都会保存到这个目录。 |
| `--restart` | 重启策略                                                     |


### 3.配置IYUU
docker容器运行成功后，打开浏览器访问：http://127.0.0.1:8787 进行配置，或把`127.0.0.1`替换为局域网地址，或者公网DDNS域名。


# 部分Docker指令

### 1.停止

```
docker stop IYUUPlus
```

### 2.运行

```
docker start IYUUPlus
```

### 3.删除容器
```
docker rm IYUUPlus
```

### 4.删除镜像
```
docker rmi iyuucn/IYUUPlus
```

# 说明

#### 功能

IYUU自动辅种工具，功能分为两大块：自动辅种、自动转移。

- 自动辅种：目前能对国内大部分的PT站点自动辅种，支持下载器集群，支持多盘位，支持多下载目录，支持远程连接等；

- 自动转移：可以实现各下载器之间自动转移做种客户端，让下载器各司其职（专职的保种、专职的下载）。

#### 原理

IYUU自动辅种工具（英文名：IYUUAutoReseed），是一款PHP语言编写的Private Tracker辅种脚本，通过计划任务或常驻内存，按指定频率调用transmission、qBittorrent下载软件的API接口，提取正在做种的info_hash提交到辅种服务器API接口（辅种过程和PT站没有任何交互），根据API接口返回的数据拼接种子连接，提交给下载器，自动辅种各个站点。

#### 优势

 - 全程自动化，无需人工干预；
 - 支持多盘位，多做种目录，多下载器，支持远程下载器；
 - 辅种精确度高，精度可配置；
 - 支持微信通知，消息即时达；
 - 自动对合集包，进行拆包辅种（暂未开发）
 - 安全：所有隐私信息只在本地存储，绝不发送给第三方。
 - 拥有专业的问答社区和交流群

#### 支持的下载器

  1. transmission
  2. qBittorrent

#### 运行环境

具备PHP运行环境的所有平台，例如：Linux、Windows、MacOS！

官方下载的记得开启curl、json、mbstring，这3个扩展。

  1. Windows安装php环境：https://www.php.net/downloads

#### 源码仓库

 - github仓库：https://github.com/ledccn/IYUUPlus
 - 码云仓库：https://gitee.com/ledc/iyuuplus


#### 使用方法

- 博客：https://www.iyuu.cn/

#### 接口开发文档

如果您懂得其他语言的开发，可以基于接口做成任何您喜欢的样子，比如手机APP，二进制包，Windows的GUI程序，浏览器插件等。欢迎分享您的作品！

实时更新的接口文档：http://api.iyuu.cn/docs.php


#### 需求提交/错误反馈

 - QQ群：859882209[2000人.入门群]，931954050[1000人.进阶群]
 - 问答社区：http://wenda.iyuu.cn
 - 博客：https://www.iyuu.cn/
 - issues： https://github.com/ledccn/IYUUPlus/issues 