<?php

namespace enrol_collegedatabase\task;

class sync_teachers_and_units extends \core\task\scheduled_task {      
    public function get_name() {
        // Shown in admin screens
        return get_string('sync_teachers_and_units', 'enrol_collegedatabase');
    }
                                                                     
    public function execute() {       
		$enrol = enrol_get_plugin('collegedatabase');
		$trace = new \text_progress_trace();
		
		$result = $enrol->sync_teachers_and_units($trace);
		// 1 db connect failure, 2 db read failure - trigger exception to force task retry and reporting
		if($result == 1) {
			throw new \dml_connection_exception();
		} else if ($result == 2) {
			throw new \dml_read_exception();
		}
    }                                                                                                                               
} 