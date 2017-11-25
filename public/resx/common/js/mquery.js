(function(A,FURL){
	var t=function(a,b){return new t.fn.init(a,b)},init,browserMatch,
	myTool="$", /*设置外部调用的命名空间名称,外部设置参数:p*/
	tDir="mTools/", /*扩展工具目录,##为本库加载外部库的默认目录,若为外链地址，则从外表加载，否则路径相对于主工具路径##*/
	DCM=A.document,
	DEM=DCM.documentElement,
	DBD=DCM.body,
	cHref=A.location,
	pHost=cHref.protocol+"//"+cHref.host,   /*协议://域名:端口号*/
	fPath=cHref.pathname,   /*当前文件(非脚本文件)路径*/
	showError=2, /*0:完全屏蔽错误;1:显示错误信息;2:错误交给浏览器处理, 外部设置参数:error*/
	ERRORS={
	  'e1':"no privileges for outer iframe！",
	  'e2':"no outer access!",
	  'e3':"para error!"
	},
	/*[[常用正则表达式集合*/
	http=new RegExp("^https?://","gi"),
	hTag=new RegExp("<(\\w+)\\s?","gi"),
	xmlTag=new RegExp("<.+?>","gi"),
	wTag=new RegExp("(^[\\s\\r\\n\\0\\t\\x0B]*)|([\\s\\r\\n\\0\\t\\x0B]*$)","g"),
	regTag=new RegExp("([\\$\\(\\)\\*\\+\\.\\[\\?\\\\\\^\\{\\|])","gi"),

	/*内部私有方法，外部不能访问*/
	gPath=function(){ /*获取当前工具文件夹路径*/
		var cSrc=(arguments[1] ? arguments[1] : FURL),reArr,
				lastSpos=cSrc.indexOf("?")!=-1 ? (cSrc.lastIndexOf("/",cSrc.indexOf("?"))+1) : (cSrc.lastIndexOf("/")+1);
	 	switch(arguments[0]){
	 		case "full":	/*获取一部分，如：http://abx.com/js/mquery.js?a=ss&b=aa*/
	 			return cSrc;
	 		break;
	 		case "part":	/*获取一部分，如：mquery.js?a=ss&b=aa*/
	 			return cSrc.substr(lastSpos);
	 		break;
	 		default:	/*获取路径，如：http://abx.com/js/ */
	 			return cSrc.substring(0,lastSpos);
	 		break;
	 	}
	},
	isLoadFile=function(src,tp){
	  var isLoaded=false,type=getFileType(src,tp),i;
	  if(type=="js" || type=="vbs"){
			var scripts=DCM.getElementsByTagName("script");
			for(i=0;i<scripts.length;i++){
				if(scripts[i].src && scripts[i].src.indexOf(src)!=-1){
					if(scripts[i].readyState=="loaded" || scripts[i].readyState=="complete"){
						isLoaded=true;
						break;
					}
				}
			}
	  }else if(type=="css"){
			var links=DCM.getElementsByTagName("link");
			for(i=0;i<links.length;i++){
				if(links[i].href && links[i].href.indexOf(src)!=-1){
					 if(links[i].readyState=="loaded" || links[i].readyState=="complete" || links[i].readyState=="interactive"){
					  isLoaded=true;
					  break;
					 }
				}
			}
	  }
	  return isLoaded;
  },
	getFileType=function(src,tp){
		if(typeof(tp)!="undefined"){
		  return tp.toLowerCase();
		}
		src=src.replace(/\?.*$/gi,"");
	  var type="",lastIndex=src.lastIndexOf(".");
	  if(lastIndex!=-1){
	    type=src.substr(lastIndex+1);
	  }
	  return type;
	},
	getFile=function(){
 	 	/*
   	  参数形式1: getFile(url[,fn][,tp][,forceExe]);
      参数形式2: getFile(obj[,fn][,tp][,forceExe]);
      参数obj形式:A--> ['url1','url2','url3'] 或 B--> [['url1',fun1],['url2',fun2],['url3',fun3]];
      动态参数：forceExc决定是否在js文件已加载的情况下强制执行回调函数;
   	*/
		var k,fn,cfn,tp,s,forceExe=false;

		for(k=1;k< arguments.length;k++){
			switch(type(arguments[k])){
				case "function":
					fn=arguments[k];
				break;
				case "string":
					tp=arguments[k];
				break;
				case "boolean":
					forceExe=arguments[k];
				break;
			}
		}

		if(type(arguments[0])=="array"){
		  for(k=0;k< arguments[0].length;k++){
				if(typeof(arguments[0][k])=="string"){ /*参数形式:A*/
				   s=arguments[0][k];
				   cfn=fn;
				 }else{  /*参数形式:B*/
				   s=arguments[0][k][0];
				   cfn=(typeof(arguments[0][k][1])=="function" ? arguments[0][k][1] : false);
				 }
				 getFile(s,cfn,tp,forceExc);
		  }
		}else{
			if(isLoadFile(arguments[0],tp)){
			  if(forceExe && typeof(fn)=="function"){fn(arguments[0])}
			  return;
			}else{
				var objDynamic,ftype=getFileType(arguments[0],tp);

				if(ftype=="js" || ftype=="vbs"){
					objDynamic=DCM.createElement("script");
					objDynamic.src=arguments[0];
					objDynamic.type=(ftype=="js" ? "text/javascript" : "text/vbscript");
					objDynamic.language=(ftype=="js" ? "javascript" : "vbscript");
				}else if(ftype=="css"){
					 objDynamic=DCM.createElement("link");
					 objDynamic.rel="stylesheet";
					 objDynamic.type="text/css";
					 objDynamic.href=arguments[0];
				}else{
				   return;
				}
				DCM.getElementsByTagName("head")[0].appendChild(objDynamic);
			  if(t.browser.msie){
			  	t(objDynamic).on("readystatechange",function(){
			  		if(findKey(objDynamic.readyState,["complete","loaded"])){
	            if(typeof(fn)=="function"){fn(arguments[0])}
	            t(objDynamic).off("readystatechange",arguments.callee);
			  		}
			  	});
			  }else{
			  	t(objDynamic).on("load",function(){
	          if(typeof(fn)=="function"){fn(arguments[0])}
	          t(objDynamic).off("load",arguments.callee);
			  	});
				}
				objDynamic.onerror=function(){
				  DCM.getElementsByTagName("head")[0].removeChild(objDynamic);
				};
			}
		}
  },
	uaMatch=function(ua) {
		var rwebkit=/(webkit)[ \/]([\w.]+)/,
				ropera=/(opera)(?:.*version)?[ \/]([\w.]+)/,
				rmsie=/(msie) ([\w.]+)/,
				rmozilla=/(mozilla)(?:.*? rv:([\w.]+))?/;

		ua=ua.toLowerCase();
		var match=rwebkit.exec(ua)||
			ropera.exec(ua)||
			rmsie.exec(ua)||
			ua.indexOf("compatible") < 0 && rmozilla.exec(ua)||
			[];
		return { browser: match[1] || "", version: match[2] || "0" };
	},
	isArrable=function(arr){
		/*
		此判断依据是拥有序列的数字索引即视为数组对象
		*/
		if(typeof(arr)!="object"||typeof(arr.length)=="undefined"){return false};
		if(type(arr)=="array"){return true};
	  for(var k=0;k< arr.length;k++){
	     if(typeof(arr[k])=="undefined"){return false;}
	  }
	  return true;
	},
	/*内部方法，外部可以通过本命名空间访问*/
  type=function(obj){/*获取对象类型*/
		var toString=Object.prototype.toString,class2type={};
    each("Boolean Number String Function Array Date RegExp Object".split(" "),function(i,name){
			class2type[ "[object " + name + "]" ]=name.toLowerCase();
		});
		return obj==null?String(obj):class2type[toString.call(obj)]||"object";
	},
	gDomain=function(){
	 /*
	  参数形式:gDomain([cURL][,gtp])
	  获取:"协议://域名:端口号"组成的字符串，gtp为test判断是否同域
	 */
	 var oArr=["protocol","host","port","full","test"],
	     cURL=trim(arguments[0]).match(http)?trim(arguments[0]):pHost,
	     gtp=setUVL(arguments,oArr,"full"),
	     phREG=new RegExp("^http://[^/]+(:\d+)?","i"),
	     cDomain=cURL.match(phREG)[0];
	     switch(gtp){
	       case "protocol":
	         return (cDomain.match(/^[^:\/]+/i)[0]);
	       break;
	       case "host":
	         return (cDomain.match(/\/[^\/:]+/i)[0].replace(/\//,""));
	       break;
	       case "port":
	         return (cDomain.match(/:[^\/:]+/i)[0].replace(/:/,""));
	       break;
	       case "full":
	         return cDomain;
	       break;
	       case "test":
	         return (cDomain==pHost);
	       break;
	     }
	},
	time=function(){
		return (new Date()).getTime();
	},
	error=function(tp){
	 switch(tp){
	   case 0:
	     t.showError=showError=tp;
	     A.onerror=function(){return true;};
	   break;
	   case 1:
	     t.showError=showError=tp;
	     A.onerror=function(){
	     	 alert("msg: "+arguments[0]+"\r\nfile: "+arguments[1]+"\r\nline: "+arguments[2]);
	     	 return true;
	     };
	   break;
	   case 2:
	     t.showError=showError=tp;
	     A.onerror=function(){return false;};
	   break;
	   default:
	     if(t.showError!=0){
	       if(ERRORS[tp]){
	         return alert(ERRORS[tp]);
	       }else{
	         return alert("unknown error!");
	       }
	     }
	   break;
	 }
	},
	trim=function(str){
	  if(typeof(str)=="undefined"||(!str&&str!==0)){return '';};
	  if(typeof(str)=="number" || typeof(str)=="string"){
	    return str.toString().replace(wTag, "").replace(/　/gi,"");
	  }else{
	    return '';
	  }
	},
	inTrim=function(str){
	  str=trim(str);
	  return str.replace(/[\s\r\n\0\t\x0B]*/gi,"");
	},
	tagTrim=function(str){
		str=trim(str);
		return str.replace(/>[\s\r\n\0\t\x0B]*</gi,"><");
	},
	REG=function(str){ /*转换正则表达式中的特殊字符*/
	  return str.replace(regTag,"\\$1");
	},
	findKey=function(vl,obj,tp){ /*适用于字符串，对象，数组*/
		var reky=-1,tpArr=[],isArr=typeof(obj.length)!="undefined";
    if(isArr){
		  for(var ck=0;ck< obj.length;ck++){
		  	if(typeof(obj[ck])=="undefined"){
		  		isArr=false; /*查找到非数字序列兼职则按照一般对象处理*/
		  		reky=-1;
		  		break;
		  	}
			  if(obj[ck]===vl){reky=ck;}/*注：此处必须是恒等于*/
		  }
    }

    if(!isArr){
		  for(var ck in obj){
			  if(obj[ck]===vl){reky=tpArr.length;}/*注：此处必须是恒等于*/
			  tpArr.push(ck);
		  }
		}else{
			tpArr=obj;
		}
	  switch(tp){
	    case "pre":
	      reky=(reky-1 >= 0)? (reky-1):(tpArr.length-1);
	      return isArr||reky==-1 ? reky : tpArr[reky];
	    break;
	    case "next":
	      reky=(reky+1 >= tpArr.length)? 0:(reky+1);
	      return isArr||reky==-1 ? reky : tpArr[reky];
	    break;
	    case "key":
	       return isArr||reky==-1 ? reky : tpArr[reky];
	    break;
	    default: /*判断是否存在*/
	      return (reky==-1 ? false : true);
	    break;
	  }
	},
	enCode=function(cnt,ctp){
		switch(ctp){
		  case "xu": /*十六进制编码*/
		    cnt=escape(cnt).replace(/%u/gi,"\\u");
	    break;
	    default: /*URL编码*/
	      cnt=encodeURIComponent(cnt);
	    break;
		}
		return cnt;
	},
	deCode=function(cnt,ctp){
		switch(ctp){
		  case "xu": /*十六进制解码*/
			  var reg=/\\u(\w{4})/g;
			  cnt=cnt.replace(reg,
					      function($0,$1,$2){
					        return unescape("%u"+$1);
					      }
					  );
		  break;
		  default: /*URL解码*/
	       cnt=decodeURIComponent(cnt);
		  break;
		}
		return cnt;
	},
	create=function(para,tgt){/*参数形式: para:{'tag':'input','attr':{'type':'text','value':''}},tgt:目标*/
	 var tObj=tgt||DCM.body,cObj,ky,Vl,Tag=para['tag'].toLowerCase(),
	     eName=para['attr']['name'] || para['attr']['id'] || "tElem"+t.startTime;
	 try{ /*防止IE下iframe的name属性无效*/
	 	 cObj=(Tag=="iframe"?DCM.createElement("<"+Tag+" name=\""+eName+"\">"):DCM.createElement(Tag));
	 }catch(e){
	 	 cObj=DCM.createElement(Tag);
	 }
	 if(Tag=="iframe"){
 	 	  if(typeof(para['attr']['value'])!="undefined"){
	 	 	  Vl=para['attr']['value'];
				delete para['attr']['value'];
			}
			attr(cObj,para['attr']);
			if(para['attr']['src']&&typeof(Vl)!="undefined"&&gDomain(para['attr']['src'],"test")){
			 	 if(t.browser.msie){
			 	 	t.event.add(cObj,"readystatechange",null,null,function(){
			     	  if(findKey(cObj.readyState,["complete","loaded"])){
				        val(cObj,Vl);
				   	 	 	t.event.add(cObj,"readystatechange",null,null,arguments.callee);
			     	  }
					});
			  }else{
			 	 	t.event.add(cObj,"load",null,null,function(){
				        val(cObj,Vl);
				   	 	 	t.event.add(cObj,"load",null,null,arguments.callee);
					});
			  }
			}
    }else{
   	  attr(cObj,para['attr']);
    }
    tObj.appendChild(cObj);
	  return cObj;
	},
	aPara=function(url,para){ /*为原RUL添加参数,返回添加后的URL*/
	  if(!trim(para)){return url;};
	  var url=url+(url.indexOf("?")!=-1?'&':'?');
	  return url+t.toURL(para);
	},
	setUVL=function(vl,obj,dft,asObj){/*vl为任意类型，每个值依次在obj对象中查找，找到则返回[vl[i],i],没有返回默认值dft*/
  	var first=function(cObj){for(var ci=0;ci< cObj.length;ci++){return cObj[ci]}},
  	    dft=dft?dft:first(obj);
    if(typeof(vl)!="object"||asObj){
      if(findKey(vl,obj)){
        return vl;
      }
    }else{
      for(var i=0;i< vl.length;i++){
      	if(typeof(vl[i])=="undefined"){
      		return setUVL(vl,obj,dft,1);
      	}
        if(findKey(vl[i],obj)){
         	return vl[i];
        }
      }
    }
    return dft;
	},
  keys=function(obj){/*获取对象键组成的数组*/
  	var rArr=[];
  	if(typeof(obj)!="object"){return rArr;}
  	for(var ky in obj){rArr.push(ky);}
  	return rArr;
  },
  values=function(obj){/*获取对象值组成的数组*/
  	var rArr=[];
  	if(typeof(obj)!="object"){return rArr;}
  	for(var ky in obj){rArr.push(obj[ky]);}
  	return rArr;
  },
  flip=function(obj){/*交换对象的键值*/
  	var rObj={};
  	if(typeof(obj)!="object"){return rObj;}
  	for(var ky in obj){
  		if(trim(obj[ky]).match(/^\w+$/gi)){
  		  rObj[obj[ky]]=ky;
  		}
  	}
  	return rObj;
  },
  merge=function(first,second){ /*合并多个数组*/
		var i=first.length,j=0;
		if(typeof second.length==="number"){
			for(var l=second.length;j< l;j++){
				first[i++]=second[j];
			}
		}else{
			while(second[j] !== undefined){
				first[i++]=second[j++];
			}
		}
		first.length=i;
		return first;
  },
  each=function(obj,callback,args){ /*对数组或对象中的每个元素执行回调函数*/
		var ky, i=0,length=obj.length,isObj=length===undefined || typeof(obj)=="function";
		if(args){
			if(isObj){
				for(ky in obj){
					if(callback.apply(obj[ky],args)===false){
						break;
					}
				}
			}else{
				for(; i < length;){
					if(callback.apply(obj[i++],args)===false){
						break;
					}
				}
			}
		}else{
			if(isObj){
				for(ky in obj){
					if(callback.call(obj[ky],ky,obj[ky])===false){
						break;
					}
				}
			}else{
				for(;i< length;){
					if(callback.call(obj[i],i,obj[i++])===false){
						break;
					}
				}
			}
		}
		return obj;
  },
	makeArray=function(array,results){
		var ret=results||[];
		if (array!=null){
			var tp=type(array);
			if(array.length==null || tp==="string" || tp==="function" || tp==="regexp" || isWindow(array)) {
				Array.prototype.push.call(ret,array);
			} else {
				return merge(ret,array);
			}
		}
		return ret;
	},
	toText=function(str){ /*返回字符串的文本格式，过滤html字符,并将实体字符转化为普通文本*/
		if(typeof(str)!="string"){return "";}
		str=str.replace(/<script[^>]*?>.*?<\/script>/gim,"");
		str=str.replace(/<[\/\!]*?[^<>]*?>/gim,"");
		var reg=new RegExp();
		var oReg=["&(quot|#34);","&(amp|#38);","&(nbsp|#160);","&(iexcl|#161);","&(cent|#162);","&(pound|#163);","&(copy|#169);","&#(\\d+);"];
		var rArr=["\"", "&"," ",String.fromCharCode(161),String.fromCharCode(162),String.fromCharCode(163), String.fromCharCode(169),""];
		for(var i=0;i< oReg.length;i++){
		  reg.compile(oReg[i],"gi");
		  str=str.replace(
		       reg,
		       function($0,$i){
		  	     return $i.match(/\d+/gi)?String.fromCharCode($i):rArr[i];
		       }
		 );
		}
	  return deCode(str,"xu");
	},
	val=function(tObj,vl,isText){
		/*
		  功能：此函数可以设置或获取对象的值
		  参数：val(tObj[,vl]),tObj可以为ID值或DOM对象，或DOM对象和ID值对象虚拟数组
		  说明：当有vl参数，设置对象序列的值，返回有效设置的个数；
		        当无vl参数，当tObj为数组时，返回的是获取值的数组，tObj为ID值或DOM对象，返回单个值

		*/
	   var obj,tgName,reArr=[],vl=(type(vl)=="object" ? val(vl) : vl),
	   		 _T=function(t){return isText ? toText(t):t},
	   		 inTagArr=["input","textarea","select"];
	   if(!isArrable(tObj)){
	   		tObj=[tObj];
	   };
	   for(var ti=0;ti< tObj.length;ti++){
	   	 obj=t.$(tObj[ti]);
	   	 if(!obj){continue};
		   tgName=obj.tagName.toLowerCase();
		   if(findKey(tgName,inTagArr)){
		   	 typeof(vl)!="undefined" ? (obj.value=_T(typeof(vl)=="function" ? vl.apply(obj,[ti,obj.value,tgName]) : vl)) : reArr.push(_T(obj.value));
		   }else{
		   	 if(tgName=="iframe"){
		   	 	  try{
			   	 	  typeof(vl)!="undefined" ? (obj.contentWindow.document.body.innerHTML=_T(typeof(vl)=="function" ? vl.apply(obj,[ti,obj.contentWindow.document.body.innerHTML,tgName]) : vl)):reArr.push(_T(obj.contentWindow.document.body.innerHTML));
		   	   }catch(e){/*return error('e1');*/}
		   	 }else{
		   	 	 typeof(vl)!="undefined" ? (obj.innerHTML=_T(typeof(vl)=="function" ? vl.apply(obj,[ti,obj.innerHTML,tgName]) : vl)):reArr.push(_T(obj.innerHTML));
		     }
		   }
		 }
		 return (typeof(vl)!="undefined"? tObj: reArr.join(""));
	},
	text=function(tObj,vl){/*返回对象包含的文本*/
	   return val(tObj,vl,1);
	},
	attr=function(tObj,na,vl){
		/*
		  功能:设置对象的的属性,vl不提供则获取属性
		  参数->
		  tObj:可以为ID数组、ID值、对象数组、对象;
		  na:属性名称,可以为字符串,也可以为对象的键值;
		  vl:属性值；
		  返回值:没有设置vl时,返回所有找到的属性值，设置了vl时，返回处理的所有对象
		*/
  	 if(typeof(tObj)=="undefined"||typeof(na)=="undefined"){return false;}
  	 if(typeof(na)=="object"){
  	 	 /*按键值处理对象的属性值*/
  	   for(var k in na){
  	     attr(tObj,k,na[k]);
  	   }
  	 }else{
  	   var re=[],vl=(typeof(vl)=="object"?val(vl):vl),
  	   fx={
  	   	    "for":"htmlFor","class":"className","readonly":"readOnly","maxlength":"maxLength",
	          "colspan":"colSpan","tabindex":"tabIndex","cellspacing":"cellSpacing",
	          "rowspan":"rowSpan","usemap":"useMap","frameborder":"frameBorder"
	        },mNa=typeof(na)!="undefined"?na.toLowerCase():na;

	     if(!isArrable(tObj)){
	     	 tObj=[tObj];
  	   }
  	   na=fx[mNa]?fx[mNa]:mNa;

       for(var ri=0;ri< tObj.length;ri++){
       	 obj=t.$(tObj[ri]);
       	 switch(na){
       	   case "style":
       	     typeof(vl)!="undefined" ? (obj.style.cssText=(type(vl)=="function"?vl.apply(obj,[ri,obj.style.cssText]):vl)) : re.push(obj.style.cssText);
       	   break;
       	   case "value":
       	     typeof(vl)!="undefined" ? val(obj,vl) : re.push(val(obj));
       	   break;
       	   default:
       	   	 typeof(vl)!="undefined" ? obj[na]=(type(vl)=="function"?vl.apply(obj,[ri,obj[na]]):vl) : re.push(obj[na]);
       	   break;
       	 }
       }
  	   return (typeof(vl)!="undefined" ? tObj : re.join(""));
	   }
	},
	findVar=function(arr,ftp,start,df){
		var start=start?start:0,re=new RegExp(ftp,"ig");;
		for(var i=start;i< arr.length;i++){
			if(re.test(type(arr[i]))){
				return arr[i];
			}
		}
		return df;
	},
	isWindow=function(obj){
		return obj!=null && obj==obj.window;
	},
	isNumeric=function(obj){
		return !isNaN(parseFloat(obj)) && isFinite(obj);
	},
	isPlainObject=function(obj){
		var hasOwn=Object.prototype.hasOwnProperty;
		if(!obj || type(obj) !== "object" || obj.nodeType || isWindow(obj)) {
			return false;
		}
		try {
			if(obj.constructor &&
				!hasOwn.call(obj, "constructor") &&
				!hasOwn.call(obj.constructor.prototype, "isPrototypeOf")){
				return false;
			}
		} catch(e){
			return false;
		}
		var key;
		for(key in obj){}
		return key=== undefined || hasOwn.call(obj, key);
	},
	isEmptyObject=function(obj){
		for (var name in obj){
			return false;
		}
		return true;
	};


   /*本空间属性和方法*/
	t.version="1.0";
	t.author="Tianlan";
	t.showError=showError;
	t.jtPath=tDir.match(http)?tDir:(gPath()+tDir);/*当前工具包的路径*/
	t.startTime=time();
	t.expando="mquery"+(t.version+Math.random()).replace(/\D/g,"");
	t.guid=0;
	t.browser={};
	browserMatch=uaMatch(navigator.userAgent);
	if(browserMatch.browser){
		t.browser[browserMatch.browser]=true;
		t.browser.version=browserMatch.version;
	}
	if(t.browser.webkit){
		t.browser.safari=true;
	}
	t.GLOBAL={};
	t.GET={};
  t.$=function(a,cDCM){
   	    if(typeof(a)=="object"){return a;};
        var cObj=(cDCM?cDCM:DCM).getElementById(a);
        return (typeof(cObj)!="undefined" && !cObj && cObj!=0) ? false : cObj;
  };
	t.noConflict=function(tp){ /*调用此函数将p命名空间转移*/
	  A[tp]=this;
	  t.event.add(window,"load",null,null,function(){
	    A[myTool]=null;
	    myTool=tp;
	  });
	};
  t.fn=t.prototype={
        'init':
        function(sel,context){
					if(!sel){
						return this;
					}
					if(sel.nodeType){
						this.context=sel.ownerDocument||document;
						this[0]=sel;
						this.length=1;
						return this;
					}
					if(sel==="body" && !context && document.body){
						this.context=document;
						this[0]=document.body;
						this.sel=sel;
						this.length=1;
						return this;
					}
					if(typeof(sel)=="string"){
						var elems=this.query(sel,context);
						this.sel=sel;
						this.length=elems?elems.length:0;
						for(var ei=0;ei< this.length;ei++){
						   this[ei]=elems[ei];
						}
						return this;
					}else if(typeof(sel)=="function"){
						return typeof(context)!="object" ? t(A).one("load",sel) : sel.apply(context);
					}
					if (sel.sel !== undefined ) {
						this.sel = sel.sel;
						this.context = sel.context;
					}
					return makeArray(sel,this);
        },
        'query':
        function(){
					var context=typeof(arguments[1])!="undefined" ? t.$(arguments[1]) : DCM;
					if(context===false){context=DCM;}
					this.context=context;
					return context.querySelectorAll ? context.querySelectorAll(arguments[0]):(A[myTool].find?A[myTool].find(arguments[0],context):null);
        }
	};
	t.fn.init.prototype=t.fn;/*实现简单继承*/

	t.extend=t.fn.extend=function(){
		 if(typeof(arguments[0])=="object"&&arguments.length==1){
			 for(var ky in arguments[0]){
			     this[ky]=arguments[0][ky];
			 }
		 }else{
		 	 var ri=-1;
		 	 for(var i=0;i< arguments.length;i++){
		 	 		if(typeof(arguments[i])=="object"){
		 	 			if(ri==-1){
		 	 				ri=i;
		 	 			}else{
		 	 				for(var k in arguments[i]){
		 	 					arguments[ri][k]=arguments[i][k];
		 	 				}
		 	 			}
		 	 		}
		 	 }
		 	 return arguments[ri];
		 }
	};

  t.event={
		'add':
		function(obj,acts,sel,dt,fn){
				if(!(obj=t.$(obj)) || obj.nodeType === 3 || obj.nodeType === 8 || !acts || !fn || !(edatas=t.data(obj))){
					return false;
				};
				var acts=t.event.hoverHack(acts).split(/[^\w]+/),
						act,hdl,evts=edatas.events;

				hdl=function(e){
					return t.event.dispatch.apply(obj,arguments);
				};
				if(!evts){
					edatas.events=evts={};
				}
				if(!edatas.handle){
					edatas.handle=hdl;
				}

	      for(var ci=0;ci< acts.length;ci++){
	      	act=acts[ci];
					if(!evts[act]){/*首次绑定此种类型的事件，构建事件序列和绑定事件*/
						evts[act]=[];
						t.event.addListener(obj,act,hdl);
					}
					for(var i=0;i< evts[act].length;i++){
						if(evts[act][i]['hdl']==fn){return;}
					}
					evts[act].push({'hdl':fn,'sel':sel,'data':dt,'quick':sel&&t.event.quickParse(sel)});
				}

		},
		'remove':
		function(obj,acts,sel,fn){
			 	var obj=t.$(obj),eDatas,evts,hdl,cevts,
			 			fn=findVar(arguments,"function",2),
			 			sel=findVar(arguments,"string",2);

				if(!obj || !(eDatas=t.data(obj)) || !eDatas['events']){return false};

				hdl=eDatas['handle'];
				evts=eDatas['events'];
				if(!acts){/*清理事件绑定的对象值*/
					for(var act in evts){
						t.event.removeListener(obj,act,hdl);
					}
					t.removeData(obj,'events');
					t.removeData(obj,'handle');
					if(isEmptyObject(eDatas)){
						t.removeData(obj);
					}
				}else{
					acts=t.event.hoverHack(acts).split(/[^\w]+/);
					for(var ai=0;ai< acts.length;ai++){
						cevts=evts[acts[ai]];
						if(!cevts){continue;}
						if(fn){
							for(var i=0;i< cevts.length;i++){
								if(cevts[i]['hdl']==fn){
									cevts.splice(i,1);
									break;
								}
							}
						}else{/*上传事件对应的所有方法*/
							t.event.removeListener(obj,acts[ai],hdl);
							delete evts[acts[ai]];
						}
					}

					if(isEmptyObject(evts)){/*清除事件残留*/
						t.event.remove(obj);
					}
				}
		},
		'dispatch':
		function(event){
				var re,csel,evts=t.data(this,"events"),quick,
						cevt=event||window.event,act=cevt.type;

				/*修正事件属性*/
				cevt['target']=cevt.srcElement||cevt.target;
				cevt['keyCode']=cevt.which||cevt.charCode||cevt.keyCode;
				cevt['pageX']=cevt.x||cevt.pageX;
				cevt['pageY']=cevt.y||cevt.pageY;

				for(var i=0;i< evts[act].length;i++){ /*事件内部遍历执行*/
					cevt['data']=evts[act][i]['data'];
					csel=evts[act][i]['sel'];
					quick=evts[act][i]['quick'];
					if(csel&&!(cevt.button && cevt.type === "click")){
						for(cur=cevt.target; cur != this; cur=cur.parentNode || this){
							if(cur.disabled !== true){
								if((quick ? t.event.quickIs(cur,quick) : t(cur).is(csel))&&(evts[act][i]['hdl'].apply(cur,[cevt])==false)){
									t.event.stopBubble(cevt);
								}
							}
						}
					}else{
						if(evts[act][i]['hdl'].apply(this,[cevt])==false){t.event.stopBubble(cevt)};
					}
				}
		},
		'stopBubble':
		function(cevt){
			DCM.all ? (cevt.cancelBubble=true):cevt.stopPropagation();
		},
		'addListener':
		function(obj,act,hdl){
			if(obj.addEventListener){
				obj.addEventListener(act,hdl,false);
			}else if(obj.attachEvent){
				obj.attachEvent("on"+act,hdl);
			}
		},
		'removeListener':
		function(obj,act,hdl){
			if(obj.removeEventListener){
				obj.removeEventListener(act,hdl,false);
			}else if(obj.detachEvent){
				obj.detachEvent("on"+act,hdl);
			}
		},
		'quickParse':
		function(selector){
			var rquickIs=/^(\w*)(?:#([\w\-]+))?(?:\.([\w\-]+))?$/,
					quick=rquickIs.exec(selector );
			if(quick){   /*	0  1    2   3
					          [ _, tag, id, class ]	*/
				quick[1] =(quick[1] || "" ).toLowerCase();
				quick[3]=quick[3] && new RegExp("(?:^|\\s)" + quick[3] + "(?:\\s|$)" );
			}
			return quick;
		},
		'quickIs':
		function(elem, m){
			var attrs=elem.attributes || {};
			return(
				(!m[1] || elem.nodeName.toLowerCase() === m[1]) &&
				(!m[2] ||(attrs.id || {}).value === m[2]) &&
				(!m[3] || m[3].test((attrs[ "class" ] || {}).value ))
			);
		},
		'hoverHack':
		function(evts){
			var reg=/(?:^|\s)hover(\.\S+)?\b/;
			return evts.indexOf("hover")!=-1 ? evts.replace(reg,"mouseover mouseout$1"):evts;
		}		
  };

	/*扩展常用事件*/
  each(("blur,focus,focusin,focusout,load,resize,scroll,unload,click,dblclick,"+
	"mousedown,mouseup,mousemove,mouseover,mouseout," +
	"change,select,submit,keydown,keypress,keyup,error,contextmenu").split(","),
  function(dx,act){
		t.fn[act]= function(data,fn){
			if(fn==null){
				fn= data;
				data=null;
			}
      if(arguments.length > 0){
      	return this.each(function(){
      		return t.event.add(this,act,null,data,fn);
      	});      	
      }else{
      	return this.each(function(){
      		if(typeof(this[act])!="undefined"){
      			this[act].apply(this);
      		}
      	});
      }
		};
  });

	t.fn.extend({
	  'each':
	  function(){
      if(typeof(arguments[0])=="function"){
	      for(var oi=0;oi< this.length;oi++){
	        if(arguments[0].apply(this[oi],[oi])===false){
	        	break;
	        }
	      }
    	}
		  return this;
	  },
		'get':
		function(cObj){
  	   for(var ei=0;ei< this.length;ei++){
  	   	 if(this[ei]==cObj){
  	   	   return ei;
  	   	 }
  	   }
		},
		'hover':
		function(fn1,fn2){
			 if(typeof(fn1)!='function' || typeof(fn2)!='function'){return false;}
  	   return this.each(function(){
  	   	 t.event.add(this,"mouseover",null,null,fn1);
  	   	 t.event.add(this,"mouseout",null,null,fn2);
  	   });
		},
		'on':
		function(acts,sel,data,fn,one){
			var origFn, act;
			if(typeof(acts)==="object"){
				if(typeof(sel)!=="string"){/*(acts-Object, data)*/
					data=data||sel;
					sel=null;
				}
				for(act in acts){
					this.on(act,sel,data,acts[act],one);
				}
				return this;
			}

			if(data==null && fn==null){/*(acts,fn)*/
				fn=sel;
				data=sel=null;
			} else if(fn==null){
				if(typeof(sel)==="string"){/*(acts, sel, fn)*/
					fn=data;
					data=null;
				} else {/*(acts, data, fn)*/
					fn=data;
					data=sel;
					sel=null;
				}
			}
			if(fn===false){
				fn=function(){return false};
			} else if(!fn){
				return this;
			}

			if(one===1){
				origFn=fn;
				fn=function(event){
					t(this).off(event.type,sel,arguments.callee);
					return origFn.apply(this, arguments);
				};
			}

			return this.each(function() {
				t.event.add(this,acts,sel,data,fn);
			});
		},
		'off':
		function(acts,sel,fn){
			if(typeof(acts)==="object"){/*(acts-object [, sel])*/
				for(var act in acts){
					this.off(act, sel, acts[act]);
				}
				return this;
			}
			if(sel===false || typeof(sel)==="function"){/*(acts [, fn])*/
				fn=sel;
				sel=null;
			}
			if(fn===false){
				fn=function(){return false};
			}
			return this.each(function() {
				t.event.remove(this,acts,sel,fn);
			});
		},
		'one':
		function(acts, sel, data, fn){
			return this.on(acts, sel, data, fn, 1);
		},
	  'val':
	  function(vl){
  	   return val(this,vl);
	  },
	  'text':
	  function(vl){
  	   return text(this,vl);
	  },
	  'attr':
	  function(na,vl){
  	   return attr(this,na,vl);
	  },
	  'replace':
	  function(vl){
	  	if(typeof(vl)!="object"){
	  	  vl=document.createTextNode(vl);
	  	}
  	  return this.each(function(){
	  		this.parentNode.replaceNode(vl,this);
	  	});
	  },
	  'load':
	  function(){
		   	 /*
		   	  功能:为对象加载HTML代码，可以实现跨域加载；
		   	  参数形式1: load(url[,para][,fn][,mtd]);
		      para参数:URL序列字符串,或ID值，或ID数组，或对象;
		      注意:如果请求页面的编码不是utf-8，一定要加入参数{"encode":"编码名称"},如para:{"encode":"gb2312"}
		      fn参数:回调函数或可执行的字符串;
		   	 */
	  	var url=arguments[0],_this=this,uPa=arguments[0].match(/\?.*/gi)?trim(arguments[0].match(/\?.*/gi)[0]).replace(/^\?/i,""):"",
          para=t.toURL(findKey(typeof(arguments[1]),["string","object"]) ? arguments[1] : ""),
          fn=findVar(arguments,"function",1),res,
          mtd=(arguments[3]?(findKey(arguments[3].toLowerCase(),["get","post"])?arguments[3]:"get"):"get").toUpperCase();
	  	t.getHTML(url,para,function(r){	  		
	  		_this.each(function(){
	  			if(!fn||((res=fn.apply(this,[r]))!==false)){
	  				val(this,res?res:r);
	  			}
	  		});        
	  	},mtd);
	  	return this;
	  },
	  'css':
	  function(){
	  	var cssObj={};
      if(typeof(arguments[0])=="object"){
        cssObj=arguments[0];
      }else if(typeof(arguments[0])=="string"){
      	switch(typeof(arguments[1])){
      	  case "undefined":return this[0].style[arguments[0]];break;
      	  case "string":cssObj[arguments[0]]=arguments[1];break;
      	  case "function":break;
      	  default:return false;break;
      	}
      }else{
        return false
      }
	    return this.each(function(){
	    	var NNM;
        for(var cNM in cssObj){
		    	NNM="";
			    for(var ai=0,cArr=cNM.toLowerCase().split("-");ai< cArr.length;ai++){
			       NNM+=(ai?(cArr[ai].substr(0,1).toUpperCase()+cArr[ai].substr(1)):cArr[ai]);
			    }
          this.style[NNM]=cssObj[cNM];
        }
	    });
	  },
	  'data':
	  function(k,v){
	  	var res=[];
	  	this.each(function(){
	  		res.push(t.data(this,k,v));
	  	});
	  	return typeof(v)!="undefined"?this:res.length >1 ? res:res[0];
	  },
	  'removeData':
	  function(k){
	  	return this.each(function(){
	  		t.removeData(this,k);
	  	});
	  },
	  'is':
	  function(para){
	  	var elems,n=0;
	  	if(typeof(para)=="string"){
	  		elems=this.query(para,this.context);
	  		this.each(function(dx){
	  			if(findKey(this,elems)){n++;}

	  		});
	  	}else if(typeof(para)=="function"){
	  		this.each(function(dx){
	  			if(para.apply(this,dx)){n++;}
	  		});
	  	}else if(typeof(para)=="object"){
	  		if(para instanceof t.fn.init){
		  		this.each(function(dx){
		  			if(findKey(this,para)){n++;}
		  		});
	  		}else{
		  		this.each(function(dx){
		  			if(this==para){n++;}
		  		});
	  		}
	  	}
	  	return n >0&&this.length==n;
	  }
	});

	/*将函数内部使用的方法外部化，15个*/
	t.extend({
		  'query':t.fn.query,/*将核心查询函数外部化*/
		  'gDomain':gDomain,
		  'time':time,
		  'error':error,
		  'trim':trim,
		  'inTrim':inTrim,
		  'tagTrim':tagTrim,
		  'REG':REG,
      'findKey':findKey,
		  'enCode':enCode,
		  'deCode':deCode,
		  'create':create,
		  'aPara':aPara,
		  'setUVL':setUVL,
		  'keys':keys,
		  'values':values,
		  'flip':flip,
		  'merge':merge,
		  'each':each,
		  'makeArray':makeArray,
		  'toText':toText,
		  'val':val,
		  'text':text,
		  'attr':attr,
		  'type':type,
		  'findVar':findVar,
		  'isWindow':isWindow,
		  'isNumeric':isNumeric,
		  'isPlainObject':isPlainObject,
		  'isEmptyObject':isEmptyObject
	});


  /*本工具主要执行方法，26个*/
  t.extend({
			/*<<:延迟加载系列函数*/
			'ajaxSetup':function()
			{
			},
			'run':function()
		  {
		  	/*
		  	 说明:动态执行插件库并执行,加载的{tDir}/lib/目录下的库
		  	 参数形式1:run function(fname[,pArr][,callback])
		  	          fname:函数名字符串；
		  	          pArr:传递给执行函数的参数数组,
		  	          callback:为回调函数系统为回调函数传递执行插件函数的返回值为参数
		  	 使用示例:$.run('md5',['admin'],function(r){alert(r)});
		  	*/
		  	if(!arguments.length){return false};
		  	var fname=arguments[0],
		  	    pArr=typeof(arguments[1])=='object' ? arguments[1] : [],
		  	    fn=findVar(arguments,"function",1),pStr="",reVL;
		  	for(var pi=0;pi< pArr.length;pi++){pStr+=",pArr["+pi+"]";};
		  	pStr=pStr.substr(1);
		  	if(A[myTool][fname]){
		  		reVL=eval("A[myTool]."+fname+"("+pStr+")");
		  	  if(fn){(fn)(reVL)};
		  	}else{
          t.getScript(t.jtPath+"lib/"+fname+".js?t="+myTool,function(){
          	var reVL=eval("A[myTool]."+fname+"("+pStr+")");
                if(fn){(fn)(reVL)};

          },true);
		  	}
		  	return this;
			},
			'getCSS':function()
			{
				 getFile(arguments[0],arguments[1],"css",arguments[3]);
				 return this;
			},
			'getScript':function()
			{
				getFile(arguments[0],arguments[1],"js",arguments[3]);
			  return this;
		  },
		  'getJSON':function()
		  {
		   	 /*
		   	  参数形式1: t.loadJSON(url[,data][,fn],[cache]);
		      data参数:URL序列字符串,或ID值，或ID数组，或对象;
		      fn参数:回调函数;
		      cache参数:指定是否为缓存的文件;
		   	 */
		     var jRUL=arguments[0]+(arguments[0].indexOf("?")!=-1?"&":"?"),
		         fn=findVar(arguments,"function",1),
		         cache=findVar(arguments,"boolean",1),
		         pObj=findKey(typeof(arguments[1]),["object","string"]) ? arguments[1] : {},
						 funName='temp'+(cache ? time():t.cookie(myTool+"_CACHE")),
						 apiJs=jRUL+t.toURL(pObj)+"&jsoncallback="+funName;

				 t.ajaxSetup("start");
				 A[funName]=function(json){if(fn){fn(json);}t.ajaxSetup("success");};
				 t.getScript(apiJs);
				 return this;
		  },
			'getXML':function()
			{
		   	 /*
		   	  参数形式1: t.getXML(fStr[,data][,callback][,async]);
		      fStr参数:XML字符串或XML文件路径;
		      callback:为回调函数,系统为回调函数传递生成的XML对象为参数;
		      注意：发送的是GET请求,默认是异步加载数据
		   	 */
				var k,txmlDoc,fStr=arguments[0],fn=false,dt="",async=true,
						parseXML=function(xmlstr){
							var parser,txmlDoc;
							if(A.DOMParser){
							  parser=new DOMParser();
							  txmlDoc=parser.parseFromString(tagTrim(xmlstr),"text/xml");
							}else{
							  txmlDoc=new ActiveXObject("Microsoft.XMLDOM");
							  txmlDoc.async=false;
							  txmlDoc.loadXML(tagTrim(xmlstr));
							}
							return txmlDoc.documentElement;
						};

				for(k=1;k< arguments.length;k++){
					switch(type(arguments[k])){
						case "function":
							fn=arguments[k];
						break;
						case "boolean":
							async=arguments[k];
						break;
						defaut:
							dt=arguments[k];
						break;
					}
				}

				if(fStr.match(xmlTag)){
					/*从XML字符串加载数据*/
						txmlDoc=parseXML(fStr);
						if(fn){(fn)(txmlDoc)};
						return txmlDoc;
				}else{
				  /*从XML文件加载数据*/
				    if(!gDomain(fStr,"test")||async){/*异域异步加载*/
			      	t.getHTML(aPara(fStr,dt),function(r){
			    			if(fn){(fn)(parseXML(r))};
			    		},async);
				    }else{  /*同域加载*/
							txmlDoc=parseXML(t.getHTML(aPara(fStr,dt),async));
							if(fn){(fn)(txmlDoc)};
							return txmlDoc;
				    }
				}
				return this;
		  },
		  'getHTML':function()
		  {
		   	 /*
		   	  参数形式1: t.getHTML(url[,para][,fn][,mtd][,sync]);
		      para参数:URL序列字符串,或ID值，或ID数组，或对象;
		      fn参数:回调函数或可执行的字符串
		      sync参数:是否进行异步加载;
		   	 */
			  var url=arguments[0],uPa=arguments[0].match(/\?.*/gi)?trim(arguments[0].match(/\?.*/gi)[0]).replace(/^\?/i,""):"",
            para=t.toURL(findKey(typeof(arguments[1]),["string","object"]) ? arguments[1] : ""),
            fn=findVar(arguments,"function"),
            mtd=(arguments[3]?(findKey(arguments[3].toLowerCase(),["get","post"])?arguments[3]:"post"):"post").toUpperCase(),
            sync=setUVL(arguments,[true,false]); 

				/*异域尝试用json函数加载，同域则使用ajax方式加载*/
				return !gDomain(url,"test") ? t.getJSON(url,para,fn) : t.ajax(url,para,fn,mtd,sync);
		  },
			'ajax':function()
			{
		   	 /*
		   	  参数形式1: t.ajax(url[,para][,fn][,mtd]);
		      data参数:URL序列字符串;
		      fn参数:回调函数或可执行的字符串;
		   	 */
			  var ajaxObj,url=arguments[0].replace(/[#\?].*/gi,""),uPa=arguments[0].match(/\?.*/gi)?trim(arguments[0].match(/\?.*/gi)[0]).replace(/^\?/i,""):"",
            para=t.toURL(findKey(typeof(arguments[1]),["string","object"]) ? arguments[1] : "")+uPa,
            fn=findVar(arguments,"function"),
			      mtd=(arguments[3]?(findKey(arguments[3].toLowerCase(),["get","post","xml"])?arguments[3]:"post"):"post").toUpperCase(),
			      sync=setUVL(arguments,[true,false]),
			      resType=(mtd=="XML"?"xml":"html");
        url=trim(url)?url:cHref.href.replace(/[#\?].*/gi,"");/*为空则提交至当前页面*/
        if(!gDomain(url,"test")){return error('e2');};
        para+=(resType=="xml"?"&resType=xml":"");
        mtd=findKey(mtd,["GET","POST"])?mtd:"POST";
			  if(window.XMLHttpRequest){
				   ajaxObj=new XMLHttpRequest();
				}else{
					 try{ajaxObj=new ActiveXObject("Msxml2.XMLHTTP");}catch(e){try{ajaxObj=new ActiveXObject("Microsoft.XMLHTTP");}catch(e){return false};};
				};

			  if(mtd=="GET"){
				    ajaxObj.open(mtd,aPara(url,para),sync);
				    ajaxObj.setRequestHeader("If-Modified-Since","0");
            ajaxObj.setRequestHeader("Cache-Control","no-cache");
				    ajaxObj.send(null);
				}else{
				    ajaxObj.open(mtd,url,sync);
				    ajaxObj.setRequestHeader("Content-Type","application/x-www-form-urlencoded;charset=utf-8"); /*采用utf-8字符集发送数据*/
				    ajaxObj.send(para);
			  };
			  t.ajaxSetup("start");
			  ajaxObj.onreadystatechange=function(){
				   if(ajaxObj.readyState==4&&ajaxObj.status==200){
				   	  if(fn){
					   	  if(resType=="xml"&&ajaxObj.responseXML.documentElement){
	                (fn)(ajaxObj.responseXML.documentElement);
					   	  }else{
					   	  	(fn)(ajaxObj.responseText);
					   	  }
				   		}
				   	  t.ajaxSetup("success");
				   };
			  };
			  return (sync?ajaxObj:ajaxObj.responseText);
			},
			'post':function()
			{
		   	 /*
		   	  参数形式1: t.post(url[,pObj][,fn]);
		      pObj参数:传递的字符串、数组、常规对象，
		               当为字符串时是form标签或有子节点的标签的ID值，
		               为数组时，是ID序列，
		               为对象时，对象的每个键名和键值即是提交的参数名和值;
		      fn参数:回调函数或可执行的字符串;
		   	 */
				 var url=arguments[0] || "",
				     pObj=(findKey(typeof(arguments[1]),['object','string'])?arguments[1]:{}),
				     fn=findVar(arguments,"function"),
				     mtd=findVar(arguments,"string",1),
				     tIframe="doFrame"+t.startTime,iObj=t.$(tIframe),startTime=time(),fObj,fDiv;
				 var bindFn=function(){ /*提交IFRAME加载事件绑定函数*/
						       if(t.browser.msie){
							       t.event.add(iObj,"readystatechange",null,null,function(){
							       	  if(findKey(iObj.readyState,["complete","loaded"])){
		                      if(fn){
			                      if(gDomain(url,"test")){
			                        (fn)(val(iObj));/*同一个域则返回处理后内容*/
			                      }else{
			                        (fn)(time()-startTime);/*不同域则返回处理耗时*/
			                      }
		                      }
		                      t.ajaxSetup("success");
		                      off(iObj,"readystatechange",null,arguments.callee);
		                      if(fDiv){DCM.body.removeChild(fDiv);}
		                    }
							       });
						       }else{
							       t.event.add(iObj,"load",null,null,function(){
							       	    if(fn){
					                  if(gDomain(url,"test")){
			                        (fn)(val(iObj));/*同一个域则返回处理后内容*/
			                      }else{
			                        (fn)(time()-startTime);/*不同域则返回处理耗时*/
			                      }
		                      }
		                      t.ajaxSetup("success");
		                      off(iObj,"load",arguments.callee);
		                      if(fDiv){DCM.body.removeChild(fDiv);}
							       });
						       }
				 };

				 if(!url){return false};
         if(!iObj || iObj.tagName.toLowerCase()!="iframe"){
           iObj=create({"tag":"iframe","attr":{'id':("doFrame"+t.startTime),'name':("doFrame"+t.startTime),'frameBorder':'0','src':'about:blank','style':'display:none;visibility:hidden;height:0px;width:0px;'}});
         }
				 if(typeof(pObj)=="object"){ /*当pObj参数为参考对象或数组，则根据常规对象键值或数组ID值建立提交表单*/
				 	  fDiv=create({"tag":"div","attr":{'style':'visibility:hidden;display:none;height:0px;width:0px;'}});
				 	  fObj=create({"tag":"form","attr":{'style':'display:inline;'}},fDiv);
            if(isArrable(pObj)){
               for(var fi=0;fi< pObj.length;fi++){
                 fObj.appendChild(create({"tag":"textarea","attr":{'id':pObj[fi],'name':pObj[fi],'value':val(pObj[fi])}}));
               }
            }else{
               for(var fi in pObj){
                 fObj.appendChild(create({"tag":"textarea","attr":{'id':fi,'name':fi,'value':pObj[fi]}}));
               }
            }
				 }else{ /*当pObj参数为字符串，则提交当前ID值pObj内部的控件值*/
				 	  fObj=t.$(pObj);
				    if(!fObj){return false;}
				    if(fObj.tagName.toLowerCase()!="form"&&fObj.parentNode.tagName.toLowerCase()!="form"){
				    	 var inNode=fObj.cloneNode(true),tpSpan;
				    	 tpSpan=create({"tag":"span","attr":{'style':'display:inline;'}},fObj);
				    	 fObj.parentNode.replaceChild(tpSpan,fObj);
               fObj=create({"tag":"form","attr":{'style':'display:inline;'}},tpSpan);
               fObj.appendChild(inNode);
				    }else if(fObj.parentNode.tagName.toLowerCase()=="form"){
               fObj=fObj.parentNode;
				    }
				 }

	 			 fObj.setAttribute("target",tIframe);
	    	 fObj.setAttribute("action",url);
	    	 fObj.setAttribute("method",(mtd.toLowerCase()=="get"?mtd:"post"));
	    	 t.ajaxSetup("start");
         bindFn(fDiv);
         fObj.submit();
			},
			'get':function(){
				t.post(arguments[0],arguments[1],arguments[2],"get");
			},
			/*延迟加载系列函数:>>*/

		  'toURL':function(tObj)
		  {
		   	 /*
		   	  返回的url字符串结尾为&
		   	  参数形式1: t.toURL(tObj[,arg1][,arg2]);
		      tObj参数:tObj为字符串时，已经是URL序列则原样返回，否则是对象的ID值(arg1为self是取本对像(否则取子对象),arg2为keep表示保留数据库安全字符);
		               tObj为常规对象时,键名和键值即是URL参数名和参数值;
		               tObj为数组时,数组值为标签ID，ID值作为URL参数名称，ID值的对象值作为对应的URL参数的值;
		   	 */

		   	 var fd=function(cid){
		   	 	      var cObj=typeof(cid)=="object" ? cid : t.$(cid);
		            return trim(cObj.name ? cObj.name:cObj.id);
		   	     },urlStr="";
		   	 if(typeof(tObj)=="string"){
		   	 	  if(inTrim(tObj).match(/(\w+=.*?)+/gi)){return trim(tObj).replace(/&$/gi,'')+"&";}; /*排除原先已是url序列的字符串*/
		   	 	  var Obj=t.$(tObj),ctlObjs,ctlObj,rdArr,rdVl,cName="";
		   	    if(!Obj){return ""};
		   	    if(arguments[1]=="self"){
		           return fd(tObj)+"="+t.subData(val(tObj),(arguments[2]?arguments[2]:"keep"))+"&";
		   	    }else{
		   	    	 /*获取当前对象子元素的URL字符串*/
		           var ctls=["input","select","textarea","iframe"];
		           for(var ci=0;ci< ctls.length;ci++){
		              ctlObjs=Obj.getElementsByTagName(ctls[ci]);
		              for(var ti=0;ti< ctlObjs.length;ti++){
		                 ctlObj=ctlObjs[ti];
		                 if(ctls[ci]=="input"&&ctlObj.type=="radio"){
		                 	 if(ctlObj.name){
		                 	 	 if(ctlObj.name!=cName){
			                 	 	 cName=ctlObj.name;
			                 	   rdArr=Obj.getElementsByName(ctlObj.name);
			                 	   rdVl=rdArr[0].value;
			                 	   for(var ri=0;ri< rdArr.length;ri++){
			                 	     if(rdArr[ri].checked){
			                          rdVl=rdArr[ri].value;
			                 	     }
			                 	   }
			                 	   urlStr+=fd(ctlObj)+"="+t.subData(val(ctlObj),(arguments[2]?arguments[2]:"keep"))+"&";
		                 	   };
		                   }else{
		                     urlStr+=fd(ctlObj)+"="+t.subData(val(ctlObj),(arguments[2]?arguments[2]:"keep"))+"&";
		                   }
		                 }else if(ctls[ci]=="iframe"){
                       if(trim(ctlObj.getAttribute("role")).match(/^writable/gi)){
                         urlStr+=fd(ctlObj)+"="+t.subData(val(ctlObj),(arguments[2]?arguments[2]:"keep"))+"&";
                       }
		                 }else{
		                   urlStr+=fd(ctlObj)+"="+t.subData(val(ctlObj),(arguments[2]?arguments[2]:"keep"))+"&";
		                 }
		              }
		           }
		   	    }
		   	 }else if(typeof(tObj)=="object"){
		   	 	  if(isArrable(tObj)){
			        for(var ky=0;ky< tObj.length;ky++){
			          urlStr+=tObj[ky]+"="+t.subData(val(tObj[ky]),(arguments[1]?arguments[1]:"keep"))+"&";
			        }
		   	 	  }else{
			        for(var oKy in tObj){
			          urlStr+=oKy+"="+t.subData(tObj[oKy],(arguments[1]?arguments[1]:"keep"))+"&";
			        }			        
		        }
		   	 }

		     return urlStr.replace(/&$/gi,'');
      },
		  'stripChar':function(str)
		  {
		   	  if(typeof(str)!="number" && typeof(str)!="string"){return str;};
		   		str=trim(str.toString());
					var repArr=[
										   [/'/gi,/,/gi,/"/gi,/;/gi],
										   ["","，","“","；"]
										 ];
				  for(var i=0;i< repArr[0].length;i++){
				    str=str.replace(repArr[0][i],(repArr[1][i]? repArr[1][i]:""));
				  }
				  return str;
		  },
		  'subData':function(str,stp)
		  {  /*stp:为keep保留数据库安全字符*/
		   	 if(typeof(str)!="number" && typeof(str)!="string"){return str;};
		     if(stp!="keep"){str=t.stripChar(str);}
	       return enCode(str);
		  },
			'dom2obj':function(dom) /*将DOM对象的属性转化为相应键和值的对象*/
			{
					 var aObj={};
					 if(!dom || !dom.attributes){return aObj;};
				   for(var mi=0;mi< dom.attributes.length;mi++){
				     aObj[dom.attributes[mi].nodeName]=dom.attributes[mi].nodeValue;
				   }
				   return aObj;
			},
			/*cookie操作系列函数*/
			'cookie':function(ckNa,ckVl,ops)
			{
				if(!arguments.length){return false};
        if(arguments.length==1){
        	return t.getCookie(ckNa);
        }else if(ckVl!=null&&ckVl!="null"&&ckVl!=""){
          return t.setCookie(ckNa,ckVl,ops);
        }else{
          return t.delCookie(ckNa);
        }
			},
			'setCookie':function(ckNa,ckVl)
			{
				 try{
					var ops=ops||{};
					if(ckVl===null){
	            ckVl='';
	            ops.expires=-1;
	        }
	        var expires='';
	        if(ops.expires&&(typeof(ops.expires)=='number'||ops.expires.toUTCString)){
	          var date;
	          if(typeof(ops.expires)=='number'){
	              date=new Date();
	              date.setTime(date.getTime() +(ops.expires * 24 * 60 * 60 * 1000));
	          }else{
	              date=ops.expires;
	          }
	          expires='; expires=' + date.toUTCString();
	        }
	        var path=ops.path ? '; path=' + ops.path : '',
	            domain=ops.domain?'; domain='+ops.domain:'',
	            secure=ops.secure?'; secure':'';
	        document.cookie=[ckNa, '=', encodeURIComponent(ckVl), expires, path, domain, secure].join('');
				  return ckVl;
				}catch(e){}
			},
			'getCookie':function(ckNa)
			{
				try{
				　var arr=DCM.cookie?DCM.cookie.match(new RegExp("(^| )"+REG(ckNa)+"=([^;]*)(;|$)")):[];
				　if(arr!=null&&arr!="null"&&arr!=""){return decodeURIComponent(arr[2]);};
				  return false;
				}catch(e){}
			},
			'delCookie':function(ckNa)
			{
				 try{
				　var exp=new Date(),cval=t.getCookie(ckNa);
				　exp.setTime(exp.getTime()-10000);
					DCM.cookie=ckNa+"=";
					DCM.cookie=ckNa+"=;path=/;expires="+ exp.toGMTString();
			  }catch(e){}
			},
		  '$_GET':function(fd,cTg)
		  {
			  	var fullUrl=(typeof(cTg)=="object" ? cTg.location.href : (typeof(cTg)=="string" ? cTg : cHref.href)),
			  	    vtp=(typeof(cTg)=="string" ? cTg : arguments[2]),
			  	    subUrl,urlArr=[],tparr=[];

			  	if(fullUrl.indexOf('?')!=-1){
			  	  subUrl=fullUrl.substr(fullUrl.indexOf('?')+1,fullUrl.length);
			  	  urlArr=subUrl.split('&');
			  	  for(var ky in urlArr){
			  	  	try{
			  	  		if(typeof(urlArr[ky])!="string"){continue};
				  	    tparr=urlArr[ky].split('=');
				  	    if(fd!=undefined){
					  	    if(tparr[0]==fd){
					  	    	return vtp=="num"&&tparr[1].match(/^\d+$/) ? parseInt(tparr[1]):tparr[1];
					  	    }
				  	    }else if(trim(tparr[0])!=""){
			            t.GET[tparr[0]]=tparr[1];
				  	    }
			  	    }catch(e){}
			  	  }
			  	}
			  	return false;
		  },
			'serialize':function(obj,deep)
			{
					var vStr='{',dp=isNumeric(deep)?parseInt(deep):0;
					if(findKey(typeof(obj),["string","number","boolean","function"])){return obj.toString();}
					if(typeof(obj)!="object"){return "";}
					for(var ky in obj){
					 if(typeof(obj[ky])=='object' && !dp){
					   try{vStr+=t.serialize(obj[ky],deep)+",";}catch(e){}
					 }else{
					 	 try{vStr+="\""+ky+"\":\""+obj[ky].toString()+"\",";}catch(e){}
					 }
					}
					vStr=vStr.replace(/,$/gi,"");
					vStr+='}';
					return vStr;
			},
			'data':function(obj,ky,vl)
			{
				if(typeof(obj)=="object"){
					if(typeof(ky)=="object"){
						for(var k in ky){
							t.data(obj,k,ky[k]);
						}
						return true;
					}
					if(typeof(obj[t.expando])=="undefined"){
						obj[t.expando]={};
					}
					if(typeof(ky)=="string"){
						if(typeof(vl)!="undefined"){
							obj[t.expando][ky]=vl;
						}else{
							return obj[t.expando][ky];
						}
					}else if(typeof(ky)=="undefined"){
						return obj[t.expando];
					}
				}
			},
			'removeData':function(obj,ky){
				if(typeof(obj)=="object"&&typeof(obj[t.expando])!="undefined"){
					if(typeof(ky)!="undefined"){
	 					if(type(ky)=="array"){/*(obj,[])*/
							for(var i=0;i< ky.length;i++){
								t.removeData(obj,ky[i]);
							}
						}else{
							try{
								delete obj[t.expando][ky];
							}catch(e){
								obj[t.expando][ky] = null;
							}
						}
					}else{
						try{
								delete obj[t.expando];
						}catch(e){
							if (obj.removeAttribute ){
								obj.removeAttribute(t.expando);
							}else{
								obj[t.expando]=null;
							}
						}
					}
				}
			}
  });

  init=function(){ /*初始化变量及导入相应的库和插件*/
	  	var cFname=gPath("part"),cVl,
	  	    showError=parseInt((cVl=t.$_GET('error',cFname))?cVl:t.showError,"int");

      /*获取p参数解决外界变量冲突*/
	  	myTool=(cVl=t.$_GET('t',cFname))?(cVl.match(/^[a-zA-Z_\$][\w_\$]*/gi)?cVl:myTool):myTool;
	  	A[myTool]=t;/*为本工具附加处理对象*/

      if(!t.cookie(myTool+"_CACHE")){t.cookie(myTool+"_CACHE",t.startTime)}; /*设置系统缓存标识*/
			if(typeof(DCM.querySelectorAll)=="undefined"){
				DCM.write('<script type="text/javascript" src="'+t.jtPath+"sizzle.js?t="+myTool+'"></script>');
			}
	  	t.error(showError);
		  t.$_GET();
	};
  init();
})(window,document.getElementsByTagName('script')[document.getElementsByTagName('script').length - 1].src);