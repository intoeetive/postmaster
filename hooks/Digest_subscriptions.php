<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Digest_subscriptions_postmaster_hook extends Base_hook {
	
	protected $title = 'Digest_subscriptions';
	
	protected $hook = 'entry_submission_end';
	
	protected $min_entries_in_digest = 5; //minimum number of entries in digest
	
	protected $frequency_seconds = 86400; //max frequency of emails (in seconds). 60*60*24 = 86400 - each email send not earlier than 24 hours after previous one
	
	public function __construct()
	{
		parent::__construct();
	}
	
	public function trigger($entry_id, $meta, $data)
	{
		//grab every member that has subscribe_to_notifications field set to 'y'
		$members_q = $this->EE->db->select('members.member_id, screen_name, email, last_visit')
					->from('members')
					->join('member_data', 'members.member_id=member_data.member_id', 'left')
					->where('m_field_id_4', 'y')
					->get();
		if ($members_q->num_rows()==0) return;
		foreach ($members_q->result_array() as $members_row)
		{
			if ($members_row['email']=='') return;
			$data = array(
				'screen_name'	=> $members_row['screen_name'],
				'email'			=> $members_row['email'],
				'entry_id'		=> ''
			);
			//get subscribed categories
			$cat_q = $this->EE->db->select('data_id')
					->from('bookmarks_reloaded')
					->where('type', 'category')
					->where('member_id', $members_row['member_id'])
					->get();
			if ($cat_q->num_rows()==0) return;
			$categories = array();
			foreach ($cat_q->result_array() as $cat_row)
			{
				$categories[] = $cat_row['data_id'];
			}
			if (empty($categories)) return;
			//when the last email to user was sent?
			$date_q = $this->EE->db->select('gmt_date, date')
						->from('postmaster_mailbox')
						->where('to_email', $members_row['email'])
						->order_by('id', 'desc')
						->limit(1)
						->get();
			if ($date_q->num_rows()==0)
			{
				$last_date = 0;
			}
			else
			{
				$last_date = $this->EE->localize->string_to_timestamp($date_q->row('date'));
			}
			
			if (($this->EE->localize->now - $last_date) < $this->frequency_seconds) return;
			
			//do we have enough entries for digest
			$entry_ids_q = $this->EE->db->select('channel_titles.entry_id')
							->from('channel_titles')
							->join('category_posts', 'channel_titles.entry_id=category_posts.entry_id', 'left')
							->where_in('cat_id', $categories)
							->where('entry_date > ', $last_date)
							->where('entry_date > ', $members_row['last_visit'])
							->where('author_id != ', $members_row['member_id'])
							->get();
			if ($entry_ids_q->num_rows() < $this->min_entries_in_digest) return;
			
			//prepare entry_id variable
			foreach ($entry_ids_q->result_array() as $row)
			{
				$data['entry_id'] .= $row['entry_id'].'|';
			}
			$data['entry_id'] = trim($data['entry_id'], '|');
			
			parent::send($data);
			return;

		}

	}
	
}