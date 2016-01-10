<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Golfthis_downgrade_trial_members_postmaster_hook extends Base_hook { 

	protected $title = 'Golfthis downgrade trial members';
		
	public function __construct($params = array())
	{
		parent::__construct(array());
	}

	public function trigger($member_id) 
	{	
		$parse_vars = $this->channel_data->get_member($member_id)->row_array(); 

		return $this->send($parse_vars, $member); 
	} 
}