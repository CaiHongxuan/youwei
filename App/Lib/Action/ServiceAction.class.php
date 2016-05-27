<?php
/**
 * Service基类
 * @author 郑永茂
 */
define('CODE_SUCCESS', 1);
define('CODE_ERROR', -1);

class ServiceAction extends Action {

    private $status;
    private $message = '';
    public function setMessage($data) {
        $this->message = $data;
    }

    public function getMessage() {
        return $this->message;
    }

    public function setStatus($data) {
        $this->status = $data;
    }

    public function getStatus() {
        return $this->status;
    }
	
	public function setError($data) {
		 $this->message = $data;
		 $this->status = CODE_ERROR;
	}

	public function setSuccess($data) {
		 $this->message = $data;
		 $this->status = CODE_SUCCESS;
	}
}

?>
