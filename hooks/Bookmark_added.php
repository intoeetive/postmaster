<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Bookmark_added_postmaster_hook extends Base_hook {
	
	protected $title = 'Bookmark added';
	
	protected $hook = 'bookmarks_bookmark_add_end';
    
	public $description = 'Triggers notification to entry author or member when entry/member is bookmarked';
    
    protected $fields = array(
		'entry_notification_preference_field' => array(
			'label' 	  => 'Profile field ID for "entry bookmarked" notification preference',
			'description' => 'Notification is sent only if field is set to "y" (or empty)',
		),
        'member_notification_preference_field' => array(
			'label' 	  => 'Profile field ID for "member bookmarked" notification preference',
			'description' => 'Notification is sent only if field is set to "y" (or empty)',
		),
	);
	
	public function __construct()
	{
		parent::__construct();
	}
	
	public function trigger($mod_data, $bookmark_id)
	{
		$settings = $this->get_settings();
        
        if ($mod_data['type']=='member')
        {
    		
    		//grab every member that has subscribe_to_notifications field set to 'y'
    		$members_q = $this->EE->db->select('members.member_id, username, screen_name, email, m_field_id_'.$settings->member_notification_preference_field)
    					->from('members')
    					->join('member_data', 'members.member_id=member_data.member_id', 'left')
    					->where('( m_field_id_'.$settings->member_notification_preference_field.' ="y" OR  m_field_id_'.$settings->member_notification_preference_field.' iS NULL)')
    					->where('members.member_id', $mod_data['data_id'])
    					->get();
    		if ($members_q->num_rows()==0) return;
    		
    		$members_row = $members_q->row_array();
    
    		if ($members_row['email']=='') return;
    		
    		$data = array(
    			'type'                      => 'member',
                'member_id'					=> $members_row['member_id'],
    			'username'					=> $members_row['username'],
    			'screen_name'				=> $members_row['screen_name'],
    			'email'						=> $members_row['email'],
    			'follower_member_id'		=> $this->EE->session->userdata('member_id'),
    			'follower_username'			=> $this->EE->session->userdata('username'),
    			'follower_screen_name'		=> $this->EE->session->userdata('screen_name')
    		);
    		
    		parent::send($data);
        }
        else if ($mod_data['type']=='entry')
        {
    		
    		//grab every member that has subscribe_to_notifications field set to 'y'
    		$members_q = $this->EE->db->select('entry_id, title, url_title, members.member_id, username, screen_name, email, m_field_id_'.$settings->entry_notification_preference_field)
    					->from('channel_titles')
                        ->join('members', 'members.member_id=channel_titles.author_id', 'left')
    					->join('member_data', 'members.member_id=member_data.member_id', 'left')
                        ->where('( m_field_id_'.$settings->entry_notification_preference_field.' ="y" OR  m_field_id_'.$settings->entry_notification_preference_field.' iS NULL )')
    					->where('entry_id', $mod_data['data_id'])
    					->get();
    		if ($members_q->num_rows()==0) return;
    		
    		$members_row = $members_q->row_array();
    
    		if ($members_row['email']=='') return;
    		
    		$data = array(
    			'type'                      => 'entry',
                'member_id'					=> $members_row['member_id'],
    			'username'					=> $members_row['username'],
    			'screen_name'				=> $members_row['screen_name'],
    			'email'						=> $members_row['email'],
                'entry_id'                  => $members_row['entry_id'],
                'title'                     => $members_row['title'],
                'url_title'                 => $members_row['url_title'],
    			'follower_member_id'		=> $this->EE->session->userdata('member_id'),
    			'follower_username'			=> $this->EE->session->userdata('username'),
    			'follower_screen_name'		=> $this->EE->session->userdata('screen_name')
    		);
    		
    		parent::send($data);
        }
        
        
		return;

	}
	
}