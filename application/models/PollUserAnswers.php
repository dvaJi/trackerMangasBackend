<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class PollUserAnswers extends MY_Model {
  protected $table  = 'poll_user_answer';

  protected function setRelations() {
		$this->addRelation(array(
			'primary'   => 'id_user',
			'table'     => 'users',
			'foreign'   => 'id',
			'variable'  => 'user'
		));
	}

}
