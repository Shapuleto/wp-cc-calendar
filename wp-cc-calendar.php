<?php
/*
 * Plugin Name:	WP CC Calendar
 * Plugin URI:
 * Description:	WP CC Calendar was engineeried to display custom posts events.
 * Version:			0.1.0
 * Author:			Conceptual Code - Oscar Chavez
 * License:			GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
?>
<?php
//add_action('template_redirect', 'pbd_alp_init');
function pbd_alp_init(){
	// Queue JS
	// wp_enqueue_script(
	// 	'pbd-alp-load-posts',
	// 	plugin_dir_url( __FILE__ ) . 'js/wp-cc-calendar-load-more.js',
	// 	array('jquery'),
	// 	'1.0',
	// 	true
	// );
	// What page are we on? And what is the pages limit?
	// $max = $wp_query->max_num_pages;
	// $paged = ( get_query_var('paged') > 1 ) ? get_query_var('paged') : 1;
	// // Add some parameters for the JS.
	// wp_localize_script(
	// 	'pbd-alp-load-posts',
	// 	'pbd_alp',
	// 	array(
	// 		'startPage' => $paged,
	// 		'maxPages' => $max,
	// 		'nextLink' => next_posts($max, false)
	// 		)
	// );
}

add_shortcode('wp-cc-calendar', 'wp_cc_calendar_init');
// Initialization of the calendar vars
function wp_cc_calendar_init($atts) {
	$all_events			= [];
	$post_events		= [];
	$month_events		= [];
	$html_events		= "";
	// 3 main vriables below
	$post_type			= "event";
	$post_field			= 'start_date';
	$curr_year			= date('Y');
	$curr_month_num	= date('m') - 0;
	// 3 main vriables above
	if(post_type_exists($post_type)){
		// 4 main functions below
		$post_events		= get_events_posts($post_type);
		if($post_events == null){return "There is no posts availables!";}
		$all_events			= events_month_split($post_events, $curr_year);
		// $month_info			= get_month_info($curr_year, $curr_month_num, $all_events);
		$extra_events		= get_extra_events($all_events);
		$extra_events		= merge_month_days($extra_events);
		// 4 main functions above
		foreach ($extra_events as $i => $years) {
			foreach ($years as $j => $months) {
				$html_events['year'][$i]['month'][$j]['formatted'] = format_calendar($i, $j, $months['events']);
			}
		}
		// keep in session var
		$_SESSION['html_events'] = json_encode($html_events);
		if(isset($_SESSION['html_events'])){
			$html_events		= calendar_display($curr_year, $curr_month_num, $html_events['year'][$curr_year]['month'][$curr_month_num]['formatted']);
		}
		else{$html_events = 'Need to enable sessions';}
	}
	else{
		$html_events = "Your Custom Post Type: '" . $post_type . "' does NOT exists.";
	}

	return $html_events;
}

// Get post types
function get_posts_types(){
	$html_posts_types = '';
	$post_types = get_post_types();

	$html_posts_types		.= '<ul>';
	foreach($post_types as $post_type){
		$html_posts_types	.= '<li>' . $post_type . '</li>';
	}
	$html_posts_types	.= '</ul>';

	return $html_posts_types;
}

// Get all events
function get_events_posts($post_type){
	$post_list	= [];
	$post_data	= [];
	$i = 1;
	$year = $month = $day = null;

	$args = array(
		'post_type'				=> $post_type,
		'posts_per_page'	=> -1,
		'post_status'			=> 'any',
		'post_parent'			=> null,
		'orderby'					=> 'meta_value',
	  'meta_key'				=> 'start_date',
	);
	$post_data = new WP_Query($args);
	if ( $post_data->have_posts() ) {
		while($post_data->have_posts()) : $post_data->the_post();
			// Date format: 27 Nov, 2016 -> d(d) m(m) yyyy
			list($day, $month, $year)						= explode(' ', get_field('start_date'));
			$month = preg_replace("(\,)", "", $month);
			$month = date_parse($month);
			$post_list[$i]['event_title']				= get_the_title();
			$post_list[$i]['event_start_date']	= get_field('start_date');
			$post_list[$i]['event_end_date']		= get_field('end_date');
			$post_list[$i]['event_year']				= $year;
			$post_list[$i]['event_month']				= $month['month'];
			$post_list[$i]['event_day']					= preg_replace("/^0/", '', $day);
			// extra fields...
			$post_list[$i]['event_id']					= get_the_id();
			$post_list[$i]['event_url']					= get_post_permalink();

			$i++;
		endwhile;
	}else{$post_list = null;}
	wp_reset_query();

	return $post_list;
}

// Split events by month
function events_month_split($events, $year){
	$events_by_month	= [];
	$year = $month = $day = null;

	foreach($events as $event){
		// Date format: 27 Nov, 2016 -> d(d) m(m) yyyy
		list($day, $month, $year) = explode(' ', $event['event_start_date']);
		$month = preg_replace("(\,)", "", $month);
		$month = date_parse($month);
  	$month = $month['month'];
		for($i = $year - 1; $i <= $year + 1; $i++){
			if($i == $year){
				for($j = 1; $j <= 12; $j++){
					if($j == $month){
						// echo $day . ' - ' . $month . ' - ' . $year . '<br>';
						$events_by_month['year'][$i]['month'][$j]['event'][] = $event;
						continue;
					}
				}
				continue;
			}
		}

	}

	return $events_by_month;
}

// Get the and put all the month info
function get_month_info($year, $month, $events){
	$month_info = [];
	$prev_year = $prev_month = $next_year = $next_month = null;

	$month_info['name']				= date('F', mktime(0, 0, 0, $month, 10));
	$month_info['num']				= $month;
	$month_info['year']				= $year;
	$month_info['total_days']	= cal_days_in_month(CAL_GREGORIAN, $month, $year);
	$month_info['first_day']	= first_day_month($year, $month); // 0 -> Sun, 6 -> Sat
	$month_info['last_day']		= date('n');

	// Verifies if last or first month of the year
	if($month == 1){	$prev_month	= 12;	$prev_year	= $year - 1;	}
	else{	$prev_month = $month - 1;		$prev_year	= $year;	}
	$month_info['cal_prev'] = prev_month_days($prev_year, $prev_month,	$month_info['first_day']);
	$month_info['cal_prev'] = merge_event_cal($prev_year, $prev_month, $month_info['cal_prev'], $events);

	$month_info['cal_curr'] = curr_month_days($year, $month);
	$month_info['cal_curr'] = merge_event_cal($year, $month, $month_info['cal_curr'], $events);

	if($month == 12){	$next_month	= 1;	$next_year	= $year		+ 1;	}
	else{	$next_month = $month + 1;			$next_year	= $year;	}
	$month_info['cal_next'] = next_month_days($next_year, $next_month, first_day_month($next_year, $next_month));
	$month_info['cal_next'] = merge_event_cal($next_year, $next_month, $month_info['cal_next'], $events);

	return $month_info;
}

function get_extra_events($all_events){
	$start	= date('Y') - 1;
	$end		= $start + 2;

	for($i = $start; $i <= $end; $i++){
		for($j = 1; $j <= 12; $j++){
			$full_month[$i][$j] = get_month_info($i, $j, $all_events);
		}
	}

	return $full_month;
}

// Merge event days
function merge_month_days($events){
	$tmp = [];

	foreach($events as $i => $years){
		foreach ($years as $j => $months) {
			$events[$i][$j]['events'] = array_merge($months['cal_prev'], $months['cal_curr']);
			$events[$i][$j]['events'] = array_merge($events[$i][$j]['events'], $months['cal_next']);
		}
	}
	return $events;
}

// Returns the number of the day of the week of the
// first day of the current month (Sun -> 0, Sat -> 6);
function first_day_month($year = null, $month = null){
	$d = 1;
	$m = $y = $time = $day = null;

	if($year == null){$y = date('Y');}
	else{$y = $year;}
	if($month == null){$m = date('n');}
	else{$m = $month;}

  $time	= strtotime($y . '-' . $m . '-' . $d);
  $day	= date('w', $time);

  return $day;
}

// Getting the remaining days of the previous month for the first week of the ß month
function prev_month_days($year, $month, $week_day){
	$prev_month_days = [];

	$days = cal_days_in_month(CAL_GREGORIAN, $month, $year);
	for($i = $days - $week_day + 1; $i <= $days; $i++){
		$prev_month_days[] = $i;
	}

	return $prev_month_days;
}

// Adding the days of the current month into an array $weeks
function curr_month_days($year, $month){
	$weeks = [];
	$class = null;

	$total_days = cal_days_in_month(CAL_GREGORIAN, $month, $year);;
	for($i = 1; $i <= $total_days; $i++){
		$weeks[] = $i;
	}

	return $weeks;
}

// Getting the remaining days of the next month for the last week of the current month
function next_month_days($year, $month, $day){
	$next_month_days = [];

	if(!$day == 0){
		for($i = 1; $i <= (7 - $day); $i++){
			$next_month_days[] = $i;
		}
	}

	return $next_month_days;
}

// Merging the events into the calendar
function merge_event_cal($year, $month, $calendar, $events){
	$cal_events = $events_list = [];

	// To verify When the year (or month) don't have any event -> @
	$events_list = @$events['year'][$year]['month'][$month]['event'];
	if(!empty($events_list)){
		foreach ($calendar as $i => $day) {
			foreach ($events_list as $j => $event) {
				if($day == $event['event_day']){
					$calendar[$i] = $events_list[$j];
				}
			}
		}
	}
	$cal_events = $calendar;

	return $cal_events;
}

// Formatting the days to be display correctly in html
function format_calendar($year, $month, $days){
	$tmp		= [];
	$class	= '';

	foreach ($days as $day) {
		if($year == date('Y') && $month == date('n') && $day == date('j')){$class = 'today';}else{$class = '';}
		if(is_array($day)){
			// Is an Event
			$tmp[] =
				'<div class="day event ' . $class . '">' .
					'<a class="link" href="' . $day["event_url"] . '">' . $day['event_day'] . '</a>' .
				'</div>';
		}else{
			$tmp[] = '<div class="day no-event ' . $class . '">' . $day . '</div>';
		}
	}
	$tmp = format_weeks($tmp);

	return $tmp;
}

// Formating the weeks array into a string
function format_weeks($weeks_arr){
	$weeks	= '';
	$tags		= '';

	for($i = 0; $i < count($weeks_arr); $i++){
		if($i % 7 == 0){
			if($i == 0){$tags = '<div class="week-row">';}
			else{$tags = '</div><div class="week-row">';}
		}
		$weeks .= $tags;
		$weeks .= $weeks_arr[$i];
		if($i == count($weeks_arr) - 1){$weeks .= '</div>';}
		$tags = '';
	}

	return $weeks;
}

// General display of the calendar
function calendar_display($year, $month, $month_cal) {
	$calendar			= "";
	$month_name		= date('F', mktime(0, 0, 0, $month, 10));
	// $month_cal	= format_weeks($month_cal);

	$calendar .= '<br><br>' .
	'<div id="calendar-' . $year . '-' . $month . '" class="box-calendar" style="color: #000;" >';
	$calendar .= '<input id="year" type="hidden" name="year" value="' . $year . '">';
	$calendar .= '<input id="month" type="hidden" name="month" value="' . $month . '">';
		$calendar .=
		'<div class="box-month">';

			// Start	- Dynamic part
			$calendar .=
			'<div class="row_nav">';

				$calendar .= '<span id="prev-month" class="month-prev-arrow"><a href="#"><</a></span>';
				$calendar .=
				'<div
					id="year-month"
					class="box-month-name"
					data-year-number=' . $year . '
					data-month-number=' . $month . '>';
					$calendar .= $month_name . ' ' . $year;
					$calendar .=
				'</div>';
				$calendar .= '<span id="next-month" class="month-next-arrow"><a href="#">></a></span>';

				$calendar .=
			'</div>';

			$calendar .=
			'<div class="row_events">';

				$calendar .= $month_cal;

				$calendar .=
			'</div>';
			// End		- Dynamic part

			$calendar .=
		'</div>';
		$calendar .=
	'</div>';

  return 	$calendar;
}

// dev function to display
function test_display($to_display){
	if(empty($to_display)){$to_display = 'Hello World!';}
	echo '<pre>';
	print_r($to_display);
	echo '</pre>';
}
?>
