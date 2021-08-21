# 介绍

IYUUAutoReseed项目的升级版，常驻内存运行；集成webui界面、辅种、转移、下载、定时访问URL、动态域名ddns等常用功能，提供完善的插件机制。

IYUU自动辅种工具，目前能对国内大部分的PT站点自动辅种，支持下载器集群，支持多盘位，支持多下载目录，支持远程连接等。



# 免责声明

在使用本工具前，请认真阅读《免责声明》全文如下：

使用IYUUAutoReseed自动辅种工具本身是非常安全的，IYUU脚本辅种时不会跟PT站点的服务器产生任何交互，只是会把下载种子链接推送给下载器，由下载器去站点下载种子。理论上，任何站点、任何技术都无法检测你是否使用了IYUUAutoReseed。危险来自于包括但不限于以下几点：

第一：建议不要自己手动跳校验，任何因为跳校验ban号，别怪我没提醒，出事后请不要怪到IYUU的头上；

第二：官方首发资源、其他一切首发资源的种子，IYUUAutoReseed自动辅种工具也无法在出种前辅种，如果因为你个人的作弊而被ban号，跟IYUU无关；

第三：您使用IYUU工具造成的一切损失，与IYUU无关。如不接受此条款，请不要使用IYUUAutoReseed，并立刻删除已经下载的源码。



# 原理

IYUU自动辅种工具（英文名：IYUUAutoReseed），是一款PHP语言编写的Private Tracker辅种脚本，通过计划任务或常驻内存，按指定频率调用transmission、qBittorrent下载软件的API接口，提取正在做种的info_hash提交到辅种服务器API接口（辅种过程和PT站没有交互），根据API接口返回的数据拼接种子连接，提交给下载器，自动辅种各个站点。




# 安装教程：

Docker命令行安装：https://github.com/ledccn/IYUUPlus/tree/master/docker

群晖Docker安装：https://www.iyuu.cn/archives/426/

ARM平台Docker命令行安装：https://www.iyuu.cn/archives/427/

Windows安装：https://www.iyuu.cn/archives/429/





# 支持的下载器

1. transmission
2. qBittorrent





# 支持自动辅种的站点

学校、杜比、家园、天空、朋友、馒头、萌猫、我堡、猫站、铂金家、烧包、北洋、TCCF、南洋、TTG、映客、城市、52pt、brobits、备胎、SSD、CHD、ptmsg、leaguehd、聆音、瓷器、hdarea、eastgame(TLF)、1ptba、hdtime、hd4fans、opencd、hdbug、hdstreet、joyhd、u2、upxin(HDU)、oshen、discfan(GZT)、cnscg圣城(已删除)、北邮、CCFBits、dicmusic、天雪、葡萄、HDRoute、伊甸园hdbd、海胆haidan、HDfans、龙之家、百川PT、HDPOST。






# 技术栈

| 类型     | 感谢                                      | 简介                                                         |
| -------- | ----------------------------------------- | ------------------------------------------------------------ |
| 常驻服务 | webman                                    | php语言编写，基于[workerman](https://www.workerman.net/)开发的高性能HTTP服务框架 |
| 前端界面 | layui2.5.7、font-awesome-4.7.0、layuimini | layui（谐音：类UI) 是一款采用自身模块规范编写的前端 UI 框架  |
| 开发语言 | PHP、HTML5、CSS3、JavaScript、Shell       |                                                              |





# 接口开发文档

如果您懂得其他语言的开发，可以基于接口做成任何您喜欢的样子，比如手机APP，二进制包，Windows的GUI程序，浏览器插件等。欢迎分享您的作品！

实时更新的接口文档：http://api.iyuu.cn/docs.php