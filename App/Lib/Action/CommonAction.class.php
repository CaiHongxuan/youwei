<?php

/**
 * 普通控制基类
 * @author 郑永茂
 * @method display()
 * A函数可以起到拼接作用，返回一个相对应的service例如：UserService
 */
class CommonAction extends Action {

    private $_services = array('User'); //以后多service,每个要增加修改

    protected function initService($services = array()) {

        if (is_string($services)) {
            if (in_array($services, $this->_services) === false) {
                throw_exception('不存在' . $services . ' Service', 'ThinkException');
            }
            $this->$services = A($services, 'Service', true);
            return $this->$services;
        }

        if (count($services) == 0) {
            $services = $this->_services;
        }
        if (count($services) == 1) {
            $service = array_pop($services);
            if (in_array($service, $this->_services) === false) {
                throw_exception('不存在' . $service . ' Service', 'ThinkException');
            }
            $this->$service = A($service, 'Service', true);
            return $this->$service;
        } else {
            foreach ($services as $service) {
                if (in_array($service, $this->_services) === false) {
                    throw_exception('不存在' . $service . ' Service', 'ThinkException');
                }
                $this->$service = A($service, 'Service', true);
            }
        }
    }

     
    /**
     * 空操作 404
     */
    
    public function _empty() {
        $this->display('Public/404');
    }
    
}
