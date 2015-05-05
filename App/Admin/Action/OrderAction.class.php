<?php
/**
* 订单管理
*/
namespace Admin\Action;
use Common\Action\ACommonAction;
class OrderAction extends ACommonAction {
    
    private $order_obj;
    private $status_arr = array(
        '0' => '未支付',
        '1' => '已支付'
    );

    public function _initialize() {
        parent::_initialize();
        $this->order_obj = D('order');

        $this->assign('status_arr', $this->status_arr);

        $data = require_once WEB_ROOT.APP_PATH.'Conf/data_config.php';
        $this->typearr = $data;
        $this->assign('typearr', $this->typearr['CODE_USETYPE']);
    }

    // 查询
    private function _search() {
        $where = array();

        // 搜索
        $search = array(
            's_status' => I('get.s_status', -1, 'intval'),
            's_type' => I('get.s_type', 0, 'intval')
        );
        if ($search['s_status'] !== -1) {
            $where['o.status'] = array('eq', $search['s_status']);
        }
        if (!empty($search['s_type'])) {
            $where['o.type'] = array('eq', $search['s_type']);
        }
        $this->assign('search', $search);
        return $where;
    }

    public function index() {
        import('@.Page');
        $where = $this->_search();

        $count = $this->order_obj
            ->alias('o')
            ->where($where)->count();
        $p = new \Page($count, C('ADMIN_PAGE_SIZE'));
        
        $list = $this->order_obj
            ->alias('o')
            ->field('o.*, u.userName')
            ->join($this->pre.'user u on o.uid = u.id')
            ->where($where)
            ->limit($p->firstRow . ',' . $p->listRows)
            ->order('o.createTime DESC')->select();

        $this->assign('pagebar', $p->show());
        $this->assign('list', $list);

        $this->display();
    }

    // 详情
    public function detail() {
        $id = I('get.id', 0, 'intval');
        $info = $this->order_obj
            ->alias('o')
            ->field('o.*, u.userName')
            ->join($this->pre.'user u on o.uid = u.id')
            ->where(array('o.id'=>$id))->find();
        if (empty($info)) {
            exit('数据不存在');
        }
        $this->assign('info', $info);

        // 产品列表
        $productList = M('orderdetail')
            ->alias('o')
            ->field('o.*, c.code, c.status')
            ->join($this->pre.'code c on o.id = c.did')
            ->where(array('o.orderId'=>$info['orderId']))->select();
        $this->assign('productList', $productList);

        $this->display('ajax_detail');
    }

    // 导出Excel
    public function export() {
        $where = $this->_search();
        $xlsData = $this->order_obj
            ->alias('o')
            ->field('o.orderId, u.userName, o.createTime, o.price, o.status, o.type')
            ->join($this->pre.'user u on o.uid = u.id')
            ->where($where)
            ->order('o.createTime DESC')->select();
        foreach ($xlsData as $k => $v) {
            $v['status']        = $v['status'] == 1 ? '已支付' : '未支付';
            $v['orderId']       = $v['orderId'];
            $v['userName']      = $v['userName'];
            $v['type']          = $this->typearr[$v['type']];
            $v['type']          = $v['type']?$v['type']:'';
            $v['price']         = $v['price'];
            $v['createTime']    = date('Y-m-d H:i:s', $v['createTime']);
            $xlsData[$k] = $v;
        }

        $xlsCell = array(
            array('orderId', '订单号'),
            array('userName', '用户'),
            array('createTime', '下单时间'),
            array('price', '总价'),
            array('type', '类型'),
            array('status', '状态')
        );
        $xlsTitle = '订单';
        $cellNum = count($xlsCell);
        $dataNum = count($xlsData);
        vendor('PHPExcel.PHPExcel');

        $objPHPExcel = new \PHPExcel();
        $cellName = array('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z','AA','AB','AC','AD','AE','AF','AG','AH','AI','AJ','AK','AL','AM','AN','AO','AP','AQ','AR','AS','AT','AU','AV','AW','AX','AY','AZ');

        $objPHPExcel->getActiveSheet(0)->mergeCells('A1:'.$cellName[$cellNum-1].'1');//合并单元格
        for ($i = 0; $i < $cellNum; $i++) {
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cellName[$i].'2', $xlsCell[$i][1]);
        }
        for ($i = 0; $i < $dataNum; $i++) {
            for ($j = 0; $j < $cellNum; $j++) {
                $objPHPExcel->getActiveSheet(0)->setCellValueExplicit($cellName[$j].($i+3), $xlsData[$i][$xlsCell[$j][0]]);
            }
        }

        ob_end_clean(); // 清空缓存
        header('Pragma: public');
        header('Expires: 0');
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Content-Type:application/force-download');
        header('Content-Type:application/vnd.ms-execl');
        header('Content-Type:application/octet-stream');
        header('Content-Type:application/download');
        header('Content-Disposition:attachment;filename="'.$xlsTitle.date('_Ymd').'.xlsx"');
        header('Content-Transfer-Encoding:binary');
        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $objWriter->save('php://output');
        exit;
    }
}

?>