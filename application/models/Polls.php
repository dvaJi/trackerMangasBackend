<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Polls extends MY_Model {
	protected $table  = 'poll';

	protected function setRelations() {
		$this->addRelation(array(
			'primary'   => 'id',
			'table'     => 'poll_answers',
			'foreign'   => 'id_poll',
			'variable'  => 'answers'
		));
	}

  public function getAnswers($answers) {
    if ($answers == NULL) {
			return null;
		}

		$answersArray = array();
		foreach ($answers as $key => $value) {

			$value->votes = (int)$this->pollUserAnswers->where('id_answer', $value->id)->count('id');

			unset($value->id_poll);
			array_push($answersArray, $value);
		}

		return $answersArray;
	}

  public function getUser($idPoll, $userId) {
    $this->db->select('poll_user_answer.id_user');
    $this->db->from('poll');
    $this->db->join('poll_answers', 'poll_answers.id_poll = poll.id');
    $this->db->join('poll_user_answer', 'poll_user_answer.id_answer = poll_answers.id');
    $this->db->where('poll_user_answer.id_user', $userId);
    $this->db->where('poll.id', $idPoll);
    $query = $this->db->get();

    if (!empty($query->result())) {
      return true;
    } else {
      return false;
    }
  }

}
