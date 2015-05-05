<?php
/**
* 用户管理
*/
namespace Admin\Action;
use Common\Action\ACommonAction;
Class UserAction extends ACommonAction {
    protected $user_obj;

    public function _initialize() {
        parent::_initialize();
        $this->user_obj = D('user');
    }

    public function index() {
        if (IS_POST) {
            $status = I('get.status', '', 'intval');
            if (isset($_POST['ids']) && in_array($status, array(0, 1))) {
                $ids = join(',', $_POST['ids']);
                if ($this->user_obj->where('id in ('.$ids.')')->save(array('status'=>$status))!==false) {
                    if (0 === $status) {
                        $msg = '锁定';
                    } elseif(1 === $status) {
                        $msg = '正常';
                    }
                    $this->success('操作['.$msg.']成功！');
                } else {
                    $this->error('操作失败！');
                }
            }
        } else {
            import('@.Page');
            $where = array();

            // 搜索
            $search = array(
                's_title'       => I('get.s_title', '', 'trim'),
                's_usertype'    => I('get.s_usertype', -1, 'intval'),
                's_status'      => I('get.s_status', -1, 'intval')
            );
            if (!empty($search['s_title'])) {
                $where['userName'] = array('like', '%'.$search['s_title'].'%');
            }
            if ($search['s_usertype'] !== -1) {
                $where['usertype'] = array('eq', $search['s_usertype']);
            }
            if ($search['s_status'] !== -1) {
                $where['status'] = array('eq', $search['s_status']);
            }
            $this->assign('search', $search);

            $count = $this->user_obj->where($where)->count();
            $p = new \Page($count, C('ADMIN_PAGE_SIZE'));
            
            $list = $this->user_obj
                ->where($where)
                ->limit($p->firstRow . ',' . $p->listRows)
                ->order('id DESC')->select();

            $this->assign('pagebar', $p->show());
            $this->assign('list', $list);

            $this->display();
        }
    }

    // 修改
    public function ajax_edit() {
        if (IS_POST) {
            $data = array(
                'id'        => intval($_POST['post']['id']),
                'email'     => trim($_POST['post']['email']),
                'mobile'    => trim($_POST['post']['mobile']),
                'realName'  => trim($_POST['post']['realname']),
                'address'   => trim($_POST['post']['address']),
                'sex'       => trim($_POST['post']['sex']),
                'birthday'  => trim($_POST['post']['birthday'])
            );
            // 修改密码
            if (!empty($_POST['post']['password'])) {
                $data['password'] = md5($_POST['post']['password']);
            }
            if ($this->user_obj->save($data) !== false) {
                $this->success('修改成功！', U('admin/user/index'));
            } else {
                $this->error('修改失败！');
            }
        } else {
            $id = I('get.id', 0, 'intval');
            $this->assign('info', $this->user_obj->where('id='.$id)->find());

            $this->display();
        }
    }

    // 新增
    public function ajax_add() {
        if (IS_POST) {
            $data = array(
                'userName'      => trim($_POST['post']['username']),
                'usertype'      => intval($_POST['post']['usertype']),
                'email'         => trim($_POST['post']['email']),
                'mobile'        => trim($_POST['post']['mobile']),
                'realName'      => trim($_POST['post']['realname']),
                'address'       => trim($_POST['post']['address']),
                'sex'           => trim($_POST['post']['sex']),
                'birthday'      => trim($_POST['post']['birthday'])
            );
            if ($this->user_obj->where(array('userName' => $data['userName']))->count()) {
                $this->error('用户名已存在，添加失败!');
            }
            // 设置密码
            $data['password']   = md5($_POST['post']['password']);
            $data['setupTime']  = time();
            $data['status']     = 1;
            if ($this->user_obj->add($data)) {
                $this->success('添加成功!', U('admin/user/index'));
            } else {
                $this->error('添加失败!');
            }
        } else {
            $this->display('ajax_edit');
        }
    }
}
?>