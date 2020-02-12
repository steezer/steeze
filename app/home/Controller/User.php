<?php
namespace App\Home\Controller;
use Library\Controller;
use App\Home\Model\User as UserModel;
use Library\Request;
use Library\Model;

/**
 * API开发——数据库增删改查范例
 * 客户端浏览器请求方式参见测试文件：/tests/testRoute.php
 */
class User extends Controller{
    
    // 定义中间件
    const MIDDLEWARE='auth';
	
	// 模型参数直接绑定路由
	public function info(UserModel $user){
        $tpl='user: id-{$id}, name-{$name}';
        return $this->fetchString($tpl, $user->data());
	}
	
	// 模型查询
	public function lists(Request $request, UserModel $user, $page=1){
        //获取GET参数
        $where['gender']=1;
        return $user->where($where)
                ->page($page, 3)
                ->order('id desc')
                ->select();
	}
    
    //添加数据
    public function add(Request $request, UserModel $user){
        //获取post信息
        $data=$request->post('info');
        //获取header信息(不区分大小写)
        $token=$request->header('token');
        if($token==='12306' && is_array($data) && !empty($data)){
            $user->startTrans(); //启动事务
            $userId=$user->add($data);
            $user->rollback(); //取消事务
            return $userId;
        }
    }
    
    //更新数据
    public function update(Request $request, UserModel $user){
        if($user->id){
            //获取post信息（原始数据）
            $data=json_decode($request->rawContent(), true);
            $data['id']=$user->id;
            $user->startTrans(); //启动事务
            $result=$user->save($data);
            $user->rollback(); //取消事务
            return $result;
        }
    }
    
    //删除数据
    public function delete(UserModel $user){
        if($user->id){
            //获取post信息（原始数据）
            $user->startTrans(); //启动事务
            $result=$user->delete();
            $user->rollback(); //取消事务
            return $result;
        }
    }
	
}
