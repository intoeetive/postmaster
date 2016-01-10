<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once PATH_THIRD . 'postmaster/libraries/Postmaster_time.php';

if(!class_exists('Base_notification'))
{
	require_once PATH_THIRD . 'postmaster/libraries/Base_notification.php';
}

class Charge_subscription_expiration_reminders_postmaster_notification extends Base_notification {
	
	
	/**
	 * Title
	 * 
	 * @var string
	 */
	 	
	public $title = 'Charge subscription expiration reminders';
	
	/**
	 * Description
	 * 
	 * @var string
	 */
	 	
	public $description = 'Send notification to customers whos subscriptions will expire soon.';
	
	
	/**
	 * Default Settings Field Schema
	 * 
	 * @var string
	 */
	 		 	 
	protected $fields = array(
		'threshold' => array(
			'label' 	  => 'Relative Send Date',
			'description' => 'Enter amount of relative time before the expiration date. If the current time is past this relative time and before the expiration, and email will send. Be sure to use a negative number.<br>Example: "-24 hours", "-3 days", "-1 week"',
		),
        'marker' => array(
			'label' 	  => 'Marker (1,2 or 3)',
			'description' => '1 for notification 2 weeks before expiration, 2 for 1 week before and 3 when it is expired.',
		)
	);
	
	
	/**
	 * Default Settings
	 * 
	 * @var string
	 */
	 	
	protected $default_settings = array();
	
	
	/**
	 * Data Tables
	 * 
	 * @var string
	 */
	 
	protected $tables = array(
		'postmaster_subscription_expires_emails' 	=> array(
			'member_id'	=> array(
				'type'				=> 'int',
				'constraint'		=> 100,
				'primary_key'		=> TRUE,
				'auto_increment'	=> TRUE
			),
            'year'	=> array(
				'type'				=> 'int',
				'constraint'		=> 4,
				'default'		=> 0
			),
            'sent_1'	=> array(
				'type'				=> 'int',
				'constraint'		=> 1,
				'default'		=> 0
			),
            'sent_2'	=> array(
				'type'				=> 'int',
				'constraint'		=> 1,
				'default'		=> 0
			),
            'sent_3'	=> array(
				'type'				=> 'int',
				'constraint'		=> 1,
				'default'		=> 0
			)
		)
	);
	
	 	
	public function __construct($params = array())
	{
		parent::__construct($params);
		
		$this->EE->load->library('encrypt');
	}
	
	public function send()
	{
		$settings = $this->get_settings();
        
        $marker = (in_array($settings->marker, array('1','2','3')))?'sent_'.$settings->marker:'sent_1';
		
		$threshold = strtotime($settings->threshold, $this->EE->localize->now);
		
		$diff = $this->EE->localize->now - $threshold;
        
        $sql = "SELECT member_id FROM exp_members AS m
                WHERE m.group_id = 7
                AND m.join_date < $date_to_check
                AND m.join_date > $site_launched
                AND NOT EXISTS 
                (SELECT member_id FROM exp_charge_subscription_member AS s WHERE s.member_id=m.member_id AND status='active')
                ";

		$this->EE->db->select('product.entry_id, product.title, product.url_title, cartthrob_order_items.order_id, email, screen_name, orders.entry_date') 
            ->from('channel_titles as orders')
            ->join('cartthrob_order_items', 'orders.entry_id=cartthrob_order_items.order_id', 'left')
            ->join('channel_titles as product', 'product.entry_id=cartthrob_order_items.entry_id', 'left')
            ->join('category_posts', 'category_posts.entry_id=cartthrob_order_items.entry_id', 'left')
            ->join('postmaster_expired_entries_emails', 'cartthrob_order_items.order_id = postmaster_expired_entries_emails.entry_id', 'left')
            ->join('members', 'members.member_id=orders.author_id', 'left')
            ->where('orders.channel_id', 10)
            ->where('cat_id', 5)
            ->where('orders.status', 'open')
            ->where('orders.entry_date < '.($this->EE->localize->now - 365*24*60*60 + $diff))
            ->where('('.$marker.'=0 OR NOT EXISTS (SELECT exp_postmaster_expired_entries_emails.entry_id FROM exp_postmaster_expired_entries_emails WHERE exp_postmaster_expired_entries_emails.entry_id=exp_cartthrob_order_items.order_id))');

        $entries = $this->EE->db->get();

        if ($entries->num_rows()==0) return;

		foreach($entries->result() as $entry)
		{	
			$entry->expiration_date = $entry->entry_date +  365*24*60*60;
            
            $this->notification = $this->EE->postmaster_lib->append($this->notification, 'entry', $entry);
			
			$parse_vars = array();
			$response 	= parent::send($parse_vars, FALSE, $entry);
				
			$data = array(
				'entry_id' => $entry->entry_id,
                $marker     => 1	
			);
			
			if(!$this->_existing_entry($entry->entry_id))
			{	
				$this->_insert_entry($entry->entry_id, $data);
			}
			else
			{
				$this->_update_entry($entry->entry_id, $data);
			}
			
		}
	}
		
	/**
	 * Install
	 *
	 * @access	public
	 * @return	void
	 */
	
	public function install()
	{		
		$this->EE->data_forge->update_tables($this->tables);
	}
	
	/**
	 * Update
	 *
	 * @access	public
	 * @param	string 	Current version
	 * @return	void
	 */
	
	public function update($current)
	{		
		$this->EE->data_forge->update_tables($this->tables);
	}
	
	/**
	 * Update an db entry
	 *
	 * @access	private
	 * @param	int 	A row id
	 * @param	array 	Data array to update
	 * @return	void
	 */
	
	private function _update_entry($id, $data = array())
	{
		$this->EE->db->where('entry_id', $id);
		$this->EE->db->update('postmaster_subscription_expires_emails', $data);
	}
	
		
	/**
	 * Insert an db entry
	 *
	 * @access	private
	 * @param	int 	A row id
	 * @param	array 	Data array to update
	 * @return	void
	 */
	
	private function _insert_entry($id, $data = array())
	{
		$data['entry_id'] = $id;
		
		$this->EE->db->insert('postmaster_subscription_expires_emails', $data);
	}
	
	
	/**
	 * Get existing db entry
	 *
	 * @access	private
	 * @param	int 	A row id
	 * @param	array 	Data array to update
	 * @return	void
	 */
	
	private function _existing_entry($id)
	{
		$this->EE->db->where('entry_id', $id);
		
		$data = $this->EE->db->get('postmaster_subscription_expires_emails');
		
		if($data->num_rows() == 0)
		{
			return FALSE;
		}
		
		return $data;
	}
}