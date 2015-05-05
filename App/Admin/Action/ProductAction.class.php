<?php
/**
* 产品管理
*/
namespace Admin\Action;
use Common\Action\ACommonAction;
class ProductAction extends ACommonAction {
    
    private $product_obj;

    public function _initialize() {
        parent::_initialize();
        $this->product_obj = D('product');
    }

    public function index() {
        import('@.Page');
        $where = array();

        // 搜索
        $search = array(
            's_title'   => I('get.s_title', '', 'trim'),
            's_type'    => I('get.s_type', 0, 'intval'),
            's_ishot'   => I('get.s_ishot', -1, 'intval'),
            's_status'  => I('get.s_status', -1, 'intval')
        );
        if (!empty($search['s_title'])) {
            $where['productName'] = array('like', '%'.$search['s_title'].'%');
        }
        if (!empty($search['s_type'])) {
            $where['typeId'] = array('eq', $search['s_type']);
        }
        if ($search['s_ishot'] !== -1) {
            $where['isHot'] = array('eq', $search['s_ishot']);
        }
        if ($search['s_status'] !== -1) {
            $where['status'] = array('eq', $search['s_status']);
        }
        $this->assign('search', $search);

        $count = $this->product_obj->where($where)->count();
        $p = new \Page($count, C('ADMIN_PAGE_SIZE'));
        
        $list = $this->product_obj
            ->where($where)
            ->limit($p->firstRow . ',' . $p->listRows)
            ->order('displayorder DESC')->select();

        $this->assign('pagebar', $p->show());
        $this->assign('list', $list);

        $this->display();
    }

    // 添加/修改
    private function _save() {
        if ($this->product_obj->create()) {
            // 数据校验
            if (trim($this->product_obj->productName) == '') {
                $this->error('请输入名称');
            }
            if (empty($this->product_obj->typeId)) {
                $this->error('请选择分类');
            }
            if (trim($this->product_obj->productPrice) == '') {
                $this->error('请输入价格');
            }
            if (empty($this->product_obj->tplCode)) {
                $this->error('请填写模板编码');
            }
            $this->product_obj->virtualnum = intval($this->product_obj->virtualnum);

            $filemsg = '';
            if (!empty($_FILES['Filedata'])) {
                // 上传处理类
                $upload = new \Think\Upload(array(
                    'maxSize'   => C('ADMIN_UPLOAD_MAX'),
                    'rootPath'  => './',
                    'savePath'  => 'product/',
                    'saveName'  => date('YmdHis').rand(0, 1000),
                    'exts'      => C('ADMIN_ALLOW_EXTS'),
                    'autoSub'   => false
                ), 'Ftp', FS('Apiconfig/ftpconfig')); // 实例化上传类
                $info = $upload->upload();
                if (!$info) {
                    $this->error('<br>图片上传失败，'.$upload->getErrorMsg(), '', true);
                } else {
                    $filemsg = '<br>图片上传成功';
                    $info = $upload->getUploadFileInfo();
                    $first = array_shift($info);
                    $this->product_obj->thumb = $this->setting['ftp_path'].$first['savepath'].$first['savename'];
                }
            }

            // 缩略图上传
            if (empty($this->product_obj->thumb)) {
                $this->error('请上传缩略图');
            }

            $id = I('post.id', 0, 'intval');
            if ($id) {// 修改
                $state = $this->product_obj->save();
                $msg = '数据修改';
            } else {// 添加
                $this->product_obj->createTime = time();
                $id = $this->product_obj->add();
                $state = $id;
                $msg = '数据添加';
            }

            if ($state) {
                $this->success($msg.'成功'.$filemsg, U('admin/product/index'));
            } else {
                $this->error($msg.'失败'.$filemsg);
            }
        } else {
            $this->error($this->product_obj->getError());
        }
    }

    // 编辑
    public function edit() {
        if (IS_POST) {
            $this->_save();
        } else {
            $id = I('get.id', 0, 'intval');
            $this->assign('info', $this->product_obj->where('id='.$id)->find());

            $this->display('ajax_add');
        }
    }

    // 新增
    public function add() {
        if (IS_POST) {
            $this->_save();
        } else {
            $this->display('ajax_add');
        }
    }

    // 删除
    public function doDel() {
        if (IS_POST) {
            $id = I('post.id', 0, 'intval');

            $return = array();
            if ($this->product_obj->where(array('id'=>$id))->delete()) {
                $return['status'] = 1;
                $return['id'] = $id;
                $return['info'] = '删除成功';
            } else {
                $return['status'] = 0;
                $return['info'] = '删除失败';
            }
            exit(json_encode($return));
        }
    }

    // 排序
    public function displayorder() {
        if (IS_POST) {
            $ids = $_POST['displayorder'];
            foreach ($ids as $k => $v) {
                $this->product_obj->where(array('id'=>$k))->save(array('displayorder'=>$v));
            }
            $this->success('更新成功', U('admin/product/index'));
        }
    }

    // 状态
    public function status() {
        if (IS_POST) {
            $status = I('get.status', '', 'intval');
            if (isset($_POST['ids']) && in_array($status, array(0, 1))) {
                $ids = join(',', $_POST['ids']);
                if ($this->product_obj->where('id in ('.$ids.')')->save(array('status'=>$status))!==false) {
                    if (0 === $status) {
                        $msg = '下架';
                    } elseif(1 === $status) {
                        $msg = '上架';
                    }
                    $this->success('操作['.$msg.']成功！');
                } else {
                    $this->error('操作失败！');
                }
            }
        }
    }
}

?>