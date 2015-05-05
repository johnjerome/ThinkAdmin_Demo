<?php
/**
* 访客反馈
*/
namespace Admin\Action;
use Common\Action\ACommonAction;
class FeedbackAction extends ACommonAction {
    
    private $feedback_obj;

    public function _initialize() {
        parent::_initialize();
        $this->feedback_obj = D('Feedback');
    }

    public function index() {
        import('@.Page');
        $where = array();
        $count = $this->feedback_obj->where($where)->count();
        $p = new \Page($count, C('ADMIN_PAGE_SIZE'));
        
        $list = $this->feedback_obj
            ->where($where)
            ->limit($p->firstRow . ',' . $p->listRows)
            ->order('create_time DESC')->select();

        $this->assign('pagebar', $p->show());
        $this->assign('list', $list);

        $this->display();
    }

    // 详情
    public function detail() {
        $id = I('get.id', 0, 'intval');
        $info = $this->feedback_obj
            ->where(array('id'=>$id))->find();
        if (empty($info)) {
            exit('数据不存在');
        }
        $info['content'] = str_replace("\n", '<br />', $info['content']);
        $this->assign('info', $info);
        
        $this->display('ajax_detail');
    }

    // 删除
    public function doDel() {
        if (IS_POST) {
            $id = I('post.id', 0, 'intval');

            $return = array();
            if ($this->feedback_obj->where(array('id'=>$id))->delete()) {
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
}

?>