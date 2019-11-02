## v1.3.2更新日志
- 1. 调整内置环境变量IS_AJAX为整数值，优先使用客户端定义的是否使用ajax请求参数
- 2. 调整控制器的ajaxReturn函数的第三个使用方法，更改为返回数据的后续处理选项
- 3. 增强控制器的display和show方法的功能
       客户端返回值由IS_AJAX值决定：
       当为0时，返回给客户端的是字符串，为模版渲染后的结果
       当为1时，返回给客户端的是JSON对象，data字段为模版变量组成的对象
       当为2时，返回给客户端的是JSON对象，data字段为传递给方法的数组参数（模版变量）组成的对象，
               view字段为模版渲染后的字符串
- 4. 默认关闭视图路由配置use_view_route（非控制器模版渲染功能），可以在环境变量文件(.env)或者配置文件中启用


## v1.3.1更新日志
- 1. 优化代码
- 2. 更改中间件的实现方式，调整中间件的处理函数的参数
- 3. 更改控制器中的路由设置方式
- 5. 优化日志处理函数fastlog，优化写入日志效率，同时新增最大日志数量为999


## v1.3.0更新日志
### 系统架构
- 增强应用对象Applicaton功能，新增继承自容器对象（Container），并自动将自身注入到请求对象（Request）、响应对象（Response）、视图（View）和控制器（Controller）中，在相关方法中，可以通getContext方法获取上下文的应用对象；
- 增强应用对象功能，可以直接使用控制器类的方法，用于匿名控制器（直接渲染视图文件，不需要控制器）的操作；
- 增强Closure路由处理器的功能，在Closure函数中的$this指向上下文的应用对象；

### 系统类库
- 优化异常处理类，删除原来的Exception类，新增ErrorException类，用于处理系统错误和异常；
- 扩展容器功能（Container）：
- 1. 将原先的callMethod方法重命名为invokeMethod、callClosure重命名为invokeFunc；
- 2. invokeFunc方法支持传入一般函数名称，如果传入Closure（匿名函数），则$this指向容器对象；
- 3. 新增invoke方法，支持一般函数、Closure（匿名函数）和对象方法的调用；
- 4. 增强模型对象（Model）功能，可以根据变量名称自动设置注入模型的操作表名；
- 新增Request对象中input方法，用于获取POST/GET输入，同时支持输入处理；
- 更新Pager类，将getListPager重命名为getPager，删除getDetailPager函数；
- 更新视图类（View）：
- 1. 将访问权限为final，不支持继承，由系统自动创建并注入应用对象，可通过getContext方法获取；
- 2. 新增getPager方法，用于获取列表分页信息；
- 3. display和fetch方法的第二个参数支持传入模板变量组成的数组；
- 4. 更新render方法，用于渲染控制器对象的方法（以前render方法用于输出内容）
- 更新控制器类（Controller）：
- 1. 增加对上下文应用对象的感知，系统创建控制器实例时自动注入应用对象，可通过getContext方法获取；
- 2. 新增getPager方法，用于获取列表分页信息；
- 3. display和fetch方法的第二个参数支持传入模板变量组成的数组；
- 4. 新增render方法，用于渲染其它控制器对象的方法；
- 5. 新增redirect方法，用于实现页面跳转；
- 6. 删除run方法，控制器的运行转为系统接管；
- 7. 更新display、show、fetch、buildHtml、assign、error、success、ajaxReturn方法的访问权限为public
- 新增中间件基类Middleware，如果用户自定义中间件继承自该类，在中间件中可以直接通过context属性获取上下文应用对象；

### 系统全局函数
- 扩展函数fastlog功能，支持自动按设定值的大小分割日志文件和删除过期日志；
- 新增函数trace，实现应用运行时的变量输出和采集；
- 新增函数to_string，实现将数组和对象属性转化为json字符串；
- 优化函数session函数，在swoole模式运行下能够支持session会话，如果启用session，每次应用启动后自动将session_id的值写入到环境变量SESSION_ID之中；
- 扩展函数make，支持容器参数传入（在swoole模式下不传入容器参数，则使用系统容器，创建的对象每次用户请求处理完成后不会销毁）；
- 删除函数cookie，可以使用应用上下文的Request对象获取或设置cookie；
- 删除函数view，同时增强了View类display和fetch方法，第二个参数支持数组作为模板变量传入；
- 更新函数E，删除第3个参数，不支持返回渲染后的错误内容；
- 删除函数redirect，可以用控制器中新增的方法redirect替代；

