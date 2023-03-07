<?php

namespace enrol_collegedatabase\task;

class sync_users extends \core\task\scheduled_task {      
    public function get_name() {
        // Shown in admin screens
        return get_string('sync_users', 'enrol_collegedatabase');
    }
                                                                     
    public function execute() {       
		$enrol = enrol_get_plugin('collegedatabase');
		$trace = new \text_progress_trace();
		
		$result = $enrol->sync_users($trace);
		// 1 db connect failure, 2 db read failure - trigger exception to force task retry and reporting
		if($result == 1) {
			throw new \dml_connection_exception();
		} else if ($result == 2) {
			throw new \dml_read_exception();
		}
    }                                                                                                                               
} 