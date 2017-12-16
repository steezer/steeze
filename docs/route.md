# Steeze路由配置
## 一、简介
路由用于将特定主机名（域名）下的访问地址匹配到特定的应用模块处理器，路由配置有全局配置和独立域配置两种方式，全局配置便于快速搭建多个应用，便于统一管理，独立域配置用于多个应用独立部署。

## 二、路由配置
### 1. 全局路由配置
全局路由配置位于storage/Conf/route.php文件中，全局路由配置中可以设置默认的路由，当系统根据主机名（域名）没有找到匹配域时，使用默认路由配置。
默认路由配置示例：

```
return [
	'default' => [
		'/test'=> function(){
			return 'test';
		},
		'/list'=> 'content/list@home'
	]
];
```
除了默认的路由配置，可以根据特定域名配置，例如：

```
return [
	'm.steeze.cn' => [
		'/show'=>'index/show@mobile'
	],
];
```
**特别说明**：找到匹配的域，但未找到匹配的路由时，系统不会使用默认路由匹配，而是提示未找到页面
### 2. 独立域配置
位于storage/Routes/目录下，以匹配的域名为文件名
例如：storage/Routes/m.steeze.com.php

```
return [
	'/info'=>'content/info@mobile'
];
```

## 三、配置格式
路由配置使用数组方式，键名为匹配的路由地址，以“/”开头，键值为路由处理器，可以是控制器或Closure函数。
### 1. 控制器处理器
控制器处理器需要指定控制器类和处理方法。
一个简单的控制器处理器配置如下：

```
return [
	'www.abc.com'=>[
		'/demo'=> 'index/demo@home'
	]
];
```
表示当浏览器访问网址：http://www.abc.com/demo 时，将访问Home模块下的index控制器的demo方法
也可以在域名上指定模块名称，例如：

```
return [
	'www.abc.com@home'=>[
		'/demo'=> 'index/demo',
		'/list'=> 'content/demo',
	]
];
```
### 2. Closure函数处理器
路由处理器也可以使用Closure函数，例如：

```
return [
	'www.abc.com@home'=>[
		'/demo'=> function(){
			return 'abc';
		},
		'/info'=> function(){
			return [
				'code'=>0,
				'message'=>'success'
			];
		},
		'show'=> function(){
			return view('index/show');
		}
	]
];
```
**特别提示**：控制器处理器和Closure函数的返回值可以是字符串、数组和视图：字符串直接输出到浏览器、数组以json字符串的方式输出、视图以渲染后的模版输出。

## 四、路由参数
在路由中可以配置参数，路由参数之后会按照名称注入到控制器路由处理器中，对于Closure函数路由处理器，是按照参数顺序依次传入处理函数。
例如：

```
return [
	'default'=>[
		'/info/{userid}'=> 'user/info@home',
		'/list/{catid}/{page}'=> function($catid,$page){
			echo $catid.':'.page;
		},
	]
];
```
Home模块下User控制器的info方法如下：

```
public function info($userid){
	echo $userid;
}
```

另外对于控制器路由处理器，可以使用路由参数指定，从而更加方便路由配置，
如下的路由配置，当访问路径为"/user/list"时，直接访问Home模块下User控制器的list方法：

```
return [
	'default'=> [
		'/{c}/{a}'=> '{c}/{a}@home'
	]
];
```
可以使用“d”和“s”来限定参数的类型，“d”表示参数只能是数值，“s”表示参数是字符串
下面的路由只匹配：“/test/”+“数值”，例如：/test/11

```
return [
	'default'=> [
		'/test/{page|d}'=> function($page){
			echo $page;
		}
	]
];
```


## 五、路由中间件的配置
可以在一组或单个路由中配置一个或多个中间件，多个中间件以“&”连接，这样当访问到匹配的路由时，依次执行中间件。
### 1. 单个路由的中间件的配置
中间件与控制器之间使用“>”连接，例如：

```
return [
	'default'=> [
		'/show'=> 'auth&convert>content/show',
	]
];
```
### 2. 一组路由的中间件的配置

```
return [
	'default'=> [
		'auth&convert'=> [
			'/{c}/{a}'=>'{c}/{a}',
			'/{c}/{a}/{user|d}'=>'{c}/{a}',
		]
	]
];
```






