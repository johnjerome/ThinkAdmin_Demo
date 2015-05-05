<?php
/**
* 商户操作
*/
namespace Admin\Action;
use Common\Action\ACommonAction;
Class TradeAction extends ACommonAction {

    public function _initialize() {
        parent::_initialize();
    }

    public function appoint() {
        if (IS_POST) {
            $uid = I('post.uid', 0, 'intval');
            $data = array();
            $productIds = I('post.productIds');
            $productIds = explode(',', $productIds);
            foreach ($productIds as $k => $v) {
                array_push($data, array('uid'=>$uid, 'productId'=>$v));
            }

            $Model = M('custom_product');
            $Model->where(array('uid'=>$uid))->delete();
            if ($Model->addAll($data)) {
                $this->success('指派成功!', U('admin/user/index'));
            } else {
                $this->error('指派失败!');
            }
        } else {
            $uid = I('get.id', 0, 'intval');
            $userinfo = M('user')->field('id, userName')->where(array('id'=>$uid))->find();
            $this->assign('userinfo', $userinfo);

            // 拥有的模板
            $haslist = D('custom_product')
                ->alias('o')
                ->field('p.id, p.productName')
                ->join($this->pre.'product p on o.productId = p.id')
                ->where(array('o.uid' => $uid))
                ->select();
            $this->assign('haslist', $haslist);

            $ids = array();
            foreach ($haslist as $k => $v) {
                array_push($ids, $v['id']);
            }

            // 所有模板
            $data = array();
            $data['status'] = 1;
            if (!empty($ids)) $data['id'] = array('not in', $ids);
            $alllist = M('product')->where($data)->field('id, productName')->select();
            $this->assign('alllist', $alllist);

            $this->display();
        }
    }
}
?>