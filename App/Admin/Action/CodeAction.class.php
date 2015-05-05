<?php
/**
* 柬码管理
*/
namespace Admin\Action;
use Common\Action\ACommonAction;
class CodeAction extends ACommonAction {
    
    private $code_obj;
    private $usetypearr;

    public function _initialize() {
        parent::_initialize();
        $this->code_obj = D('code');

        $data = require_once WEB_ROOT.APP_PATH.'Conf/data_config.php';
        $this->usetypearr = $data;
        $this->assign('usetypearr', $this->usetypearr['CODE_USETYPE']);
    }

    private function _search() {
        $where = array();

        // 搜索
        $search = array(
            's_keyword'     => I('get.s_keyword'),
            's_type'        => I('get.s_type', 0, 'intval'),
            's_status'      => I('get.s_status', -1, 'intval'),
            's_startdate'   => I('get.s_startdate'),
            's_enddate'     => I('get.s_enddate'),
            's_usetype'     => I('get.s_usetype', 0, 'intval')
        );
        if (!empty($search['s_keyword'])) {
            switch ($search['s_type']) {
                case '1':// 柬码
                    $where['o.code'] = array('like', '%'.$search['s_keyword'].'%');
                    break;
                case '2':// 订单编号
                    $where['o.orderId'] = array('like', '%'.$search['s_keyword'].'%');
                    break;
                case '3':
                    $where['p.productName'] = array('like', '%'.$search['s_keyword'].'%');
                    break;
            }
        }
        if ($search['s_status'] !== -1) {
            $where['o.status'] = array('eq', $search['s_status']);
        }
        // 时间范围
        if ($search['s_startdate'] && $search['s_enddate']) {
            $where['o.createTime'] = array(array('egt', $search['s_startdate']), array('lt', $search['s_enddate']),'and');
        }
        // 途径
        if (!empty($search['s_usetype'])) {
            $where['o.useType'] = array('eq', $search['s_usetype']);
        }

        $this->assign('search', $search);
        return $where;
    }

    public function index() {
        import("@.Page");
        $where = $this->_search();

        $count = $this->code_obj
            ->alias('o')
            ->field('o.*, p.productName')
            ->join($this->pre.'product p on o.productId = p.id')
            ->where($where)
            ->count();
        $p = new \Page($count, C('ADMIN_PAGE_SIZE'));
        
        $list = $this->code_obj
            ->alias('o')
            ->field('o.*, p.productName')
            ->join($this->pre.'product p on o.productId = p.id')
            ->where($where)
            ->limit($p->firstRow . ',' . $p->listRows)
            ->order('o.createTime DESC')->select();

        $this->assign('pagebar', $p->show());
        $this->assign('list', $list);

        $this->display();
    }

    public function export() {
        $where = $this->_search();
        $xlsData = $this->code_obj
            ->alias('o')
            ->field('o.code, p.productName, o.startTime, o.endTime, o.status, o.orderId, o.useType, o.createTime')
            ->join($this->pre.'product p on o.productId = p.id')
            ->where($where)
            ->order('o.createTime DESC')->select();
        foreach ($xlsData as $k => $v) {
            $v['startTime'] = ($v['startTime']?date('Y-m-d H:i:s', $v['startTime']):'').' - '.($v['endTime']?date('Y-m-d H:i:s', $v['endTime']):'');
            unset($v['endTime']);
            $v['status'] = $v['status'] == 1 ? '已使用' : '未使用';
            $v['orderId'] = $v['orderId'];
            $v['useType'] = $this->usetypearr['CODE_USETYPE'][$v['useType']];
            $v['useType'] = $v['useType']?$v['useType']:'';
            $v['createTime'] = date('Y-m-d H:i:s', $v['createTime']);
            $xlsData[$k] = $v;
        }
        // 导出Excel
        $xlsCell = array(
            array('code', '柬码'),
            array('productName', '模板'),
            array('startTime', '柬码有效时间'),
            array('status', '使用状态'),
            array('orderId', '订单号'),
            array('useType', '途径'),
            array('createTime', '生成时间')
        );
        $xlsTitle = '柬码';
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

    private function _newCode() {
        $code = md5(uniqid('', true));
        return $code;
    }

    // 批量添加
    public function doSave() {
        if (IS_POST) {
            // 判断是否选择对应的请柬
            $productId = I('post.productId', 0, 'intval');
            if (empty($productId)) {
                $this->error('请先选择请柬，而后生成对应的柬码', U('admin/product/index'));
            }
            $productInfo = M('product')->field('id,productPrice')->where('id='.$productId)->find();
            if (empty($productInfo)) {
                $this->error('请柬数据不存在');
            }
            if ($this->code_obj->create()) {
                // 数据校验
                if (empty($this->code_obj->useType)) {
                    $this->error('请选择途径');
                }
                $num = I('post.num', 0, 'intval');
                if (!$num) {
                    $this->error('请填写生成数量');
                }
                // 开始时间
                if (empty($this->code_obj->startTime)) {
                    $this->code_obj->startTime = 0;
                } else {
                    $this->code_obj->startTime = strtotime($this->code_obj->startTime);
                }
                // 结束时间
                if (empty($this->code_obj->endTime)) {
                    $this->code_obj->endTime = 0;
                } else {
                    $this->code_obj->endTime = strtotime($this->code_obj->endTime);
                }
                $this->code_obj->createTime = time();
                // 循环
                $data = array();
                for ($i = 0; $i < $num; $i++) {
                    array_push($data, array(
                        'code'          => $this->_newCode(),
                        'createTime'    => $this->code_obj->createTime,
                        'status'        => 0,
                        'useType'       => $this->code_obj->useType,
                        'startTime'     => $this->code_obj->startTime,
                        'endTime'       => $this->code_obj->endTime,
                        'productId'     => $productInfo['id'],
                        'price'         => $productInfo['productPrice']
                    ));
                }

                if ($this->code_obj->addAll($data)) {
                    $this->success('批量生成成功', U('admin/code/index'));
                } else {
                    $this->error('批量生成失败');
                }
            } else {
                $this->error($this->code_obj->getError());
            }
        }
    }
}

?>