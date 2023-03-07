<?php

namespace enrol_collegedatabase\task;

class sync_meta extends \core\task\scheduled_task {      
    public function get_name() {
        // Shown in admin screens
        return get_string('sync_meta', 'enrol_collegedatabase');
    }
                                                                     
    public function execute() {       
		$enrol = enrol_get_plugin('collegedatabase');
		$trace = new \text_progress_trace();
		
		$result = $enrol->sync_meta($trace);
		// 1 failure - trigger exception to force task retry and reporting
		if ($result == 1) {
			throw new \dml_read_exception();
		}
    }                                                                                                                               
} 