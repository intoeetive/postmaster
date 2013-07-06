<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Sites_notifications_postmaster_hook extends Base_hook {
	
	protected $title = 'Sites notifications';
	
	protected $hook = 'entry_submission_end';
	
	public function __construct()
	{
		parent::__construct();
	}
	
	public function trigger($entry_id, $meta, $data)
	{
		$data = array_merge($data, $meta);
		
		if (!in_array($data['channel_id'], array(24,11,2))) //reviews, features, blogs
		{
			return;
		}

    	//better workflow compatibility
		foreach($_POST as $k => $v) 
		{
			if (preg_match('/^epBwfEntry/',$k))
			{
				$data['status'] = array_pop(explode('|',$v));
				break;
			}
		}

		
		if($data['status']=='open')
		{
			//check it hasn't beed out yet
			$query = $this->EE->db->select('entry_id')
						->from('postmaster_sites_notifications')
						->where('entry_id', $entry_id)
						->get();
			if ($query->num_rows()>0)
			{
				return;
			}
				
			//recipients
			$recipients = array(
"email1@domain1.com",
"email2@domain2.com"
			);
			

			$q = $this->EE->db->select('channel_url, comment_url')
					->from('channels')
					->where('channel_id', $data['channel_id'])
					->get();
			$channel_data = $q->row_array();
			$basepath = ($channel_data['comment_url']!='') ? $channel_data['comment_url'] : $channel_data['channel_url'];
					

			$data['entry_id'] = $entry_id;
			$data['tag_id'] = $tag_id;
			$data['tag'] = $tag_name;
			$data['entry_id_path'] = $this->EE->functions->create_page_url($basepath, $entry_id);
			$data['url_title_path'] = $data['path'] = $this->EE->functions->create_page_url($basepath, $data['url_title']);
			
			
			foreach ($recipients as $email)
			{
				$data['email'] = $email;
				//var_dump($data);
				parent::send($data);
			}
			
			$ins = array('entry_id' => $entry_id);
			$this->EE->db->insert('postmaster_sites_notifications', $ins);
			

		}	
		//exit();
	}
	
}