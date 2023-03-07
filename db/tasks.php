<?php

$tasks = array(                                                                                                                     
    array(                                                                                                                          
        'classname' => 'enrol_collegedatabase\task\sync_enrolments',                                                                            
        'blocking' => 0,                                                                                                            
        'minute' => '30',                                                                                                            
        'hour' => '*',                                                                                                              
        'day' => '*',                                                                                                               
        'dayofweek' => '*',                                                                                                         
        'month' => '*'                                                                                                              
    ),
	array(                                                                                                                          
        'classname' => 'enrol_collegedatabase\task\sync_users',                                                                            
        'blocking' => 0,                                                                                                            
        'minute' => '15',                                                                                                            
        'hour' => '*',                                                                                                              
        'day' => '*',                                                                                                               
        'dayofweek' => '*',                                                                                                         
        'month' => '*'                                                                                                              
    ),
	array(                                                                                                                          
        'classname' => 'enrol_collegedatabase\task\sync_courses',                                                                            
        'blocking' => 0,                                                                                                            
        'minute' => '5',                                                                                                            
        'hour' => '*',                                                                                                              
        'day' => '*',                                                                                                               
        'dayofweek' => '*',                                                                                                         
        'month' => '*'                                                                                                              
    ),
	array(                                                                                                                          
        'classname' => 'enrol_collegedatabase\task\sync_teachers_and_units',                                                                            
        'blocking' => 0,                                                                                                            
        'minute' => '0',                                                                                                            
        'hour' => '*',                                                                                                              
        'day' => '*',                                                                                                               
        'dayofweek' => '*',                                                                                                         
        'month' => '*'                                                                                                              
    ),
	array(                                                                                                                          
        'classname' => 'enrol_collegedatabase\task\sync_meta',                                                                            
        'blocking' => 0,                                                                                                            
        'minute' => '20',                                                                                                            
        'hour' => '*',                                                                                                              
        'day' => '*',                                                                                                               
        'dayofweek' => '*',                                                                                                         
        'month' => '*'                                                                                                              
    )
);