<?php

include_once('modules/Scheduling/includes/calcSeats0.fnc.php');

if($_REQUEST['modname']=='Scheduling/UnfilledRequests.php')
{
	DrawHeader(ProgramTitle());
	if($_REQUEST['search_modfunc']=='list')
	{
		echo '<FORM action="Modules.php?modname='.$_REQUEST['modname'].'&modfunc=modify" METHOD="POST">';
		DrawHeader('<label>'.CheckBoxOnclick('include_seats').' '._('Show Available Seats').'</label>');
		echo '</FORM>';
	}
}
else
	$extra['suppress_save'] = $extra['NoSearchTerms'] = true;

//modif Francois: multiple school periods for a course period
/*$extra['SELECT'] = ',s.CUSTOM_200000000,c.TITLE AS COURSE,sr.SUBJECT_ID,sr.COURSE_ID,sr.WITH_TEACHER_ID,sr.NOT_TEACHER_ID,sr.WITH_PERIOD_ID,sr.NOT_PERIOD_ID,\'0\' AS AVAILABLE_SEATS,(SELECT count(*) AS SECTIONS FROM COURSE_PERIODS cp WHERE cp.COURSE_ID=sr.COURSE_ID AND (cp.GENDER_RESTRICTION=\'N\' OR cp.GENDER_RESTRICTION=substring(s.CUSTOM_200000000,1,1)) AND (sr.WITH_TEACHER_ID IS NULL OR sr.WITH_TEACHER_ID=cp.TEACHER_ID) AND (sr.NOT_TEACHER_ID IS NULL OR sr.NOT_TEACHER_ID!=cp.TEACHER_ID) AND (sr.WITH_PERIOD_ID IS NULL OR sr.WITH_PERIOD_ID=cp.PERIOD_ID) AND (sr.NOT_PERIOD_ID IS NULL OR sr.NOT_PERIOD_ID!=cp.PERIOD_ID)) AS SECTIONS ';*/
$custom_fields_RET = DBGet(DBQuery("SELECT ID,TITLE,TYPE FROM CUSTOM_FIELDS WHERE ID=200000000"),array(),array('ID'));
if ($custom_fields_RET['200000000'] && $custom_fields_RET['200000000'][1]['TYPE'] == 'select')
	$extra['SELECT'] = ',s.CUSTOM_200000000,c.TITLE AS COURSE,sr.SUBJECT_ID,sr.COURSE_ID,sr.WITH_TEACHER_ID,sr.NOT_TEACHER_ID,sr.WITH_PERIOD_ID,sr.NOT_PERIOD_ID,\'0\' AS AVAILABLE_SEATS,(SELECT count(*) AS SECTIONS FROM COURSE_PERIODS cp WHERE cp.COURSE_ID=sr.COURSE_ID AND (cp.GENDER_RESTRICTION=\'N\' OR cp.GENDER_RESTRICTION=substring(s.CUSTOM_200000000,1,1)) AND (sr.WITH_TEACHER_ID IS NULL OR sr.WITH_TEACHER_ID=cp.TEACHER_ID) AND (sr.NOT_TEACHER_ID IS NULL OR sr.NOT_TEACHER_ID!=cp.TEACHER_ID)) AS SECTIONS ';
else //'None' as GENDER
	$extra['SELECT'] = ',\'None\' AS CUSTOM_200000000,c.TITLE AS COURSE,sr.SUBJECT_ID,sr.COURSE_ID,sr.WITH_TEACHER_ID,sr.NOT_TEACHER_ID,sr.WITH_PERIOD_ID,sr.NOT_PERIOD_ID,\'0\' AS AVAILABLE_SEATS,(SELECT count(*) AS SECTIONS FROM COURSE_PERIODS cp WHERE cp.COURSE_ID=sr.COURSE_ID AND (cp.GENDER_RESTRICTION=\'N\' OR cp.GENDER_RESTRICTION=substring(\'None\',1,1)) AND (sr.WITH_TEACHER_ID IS NULL OR sr.WITH_TEACHER_ID=cp.TEACHER_ID) AND (sr.NOT_TEACHER_ID IS NULL OR sr.NOT_TEACHER_ID!=cp.TEACHER_ID)) AS SECTIONS ';

//$extra['FROM'] = ',SCHEDULE_REQUESTS sr,COURSES c';
$extra['FROM'] = ',SCHEDULE_REQUESTS sr,COURSES c';
//$extra['WHERE'] = ' AND sr.STUDENT_ID=ssm.STUDENT_ID AND sr.SYEAR=ssm.SYEAR AND sr.SCHOOL_ID=ssm.SCHOOL_ID AND sr.COURSE_ID=c.COURSE_ID AND NOT EXISTS (SELECT \'\' FROM SCHEDULE s WHERE s.STUDENT_ID=sr.STUDENT_ID AND s.COURSE_ID=sr.COURSE_ID)';
$extra['WHERE'] = ' AND sr.STUDENT_ID=ssm.STUDENT_ID AND sr.SYEAR=ssm.SYEAR AND sr.SCHOOL_ID=ssm.SCHOOL_ID AND sr.COURSE_ID=c.COURSE_ID AND NOT EXISTS (SELECT \'\' FROM SCHEDULE s WHERE s.STUDENT_ID=sr.STUDENT_ID AND s.COURSE_ID=sr.COURSE_ID)';
$extra['functions'] = array('WITH_TEACHER_ID'=>'_makeTeacher','WITH_PERIOD_ID'=>'_makePeriod');
if($_REQUEST['include_seats'])
	$extra['functions'] += array('AVAILABLE_SEATS'=>'CalcSeats');
$extra['columns_after'] = array('COURSE'=>_('Request'));
if($_REQUEST['include_seats'])
	$extra['columns_after'] += array('AVAILABLE_SEATS'=>_('Available Seats'));
$extra['columns_after'] += array('SECTIONS'=>_('Sections'),'WITH_TEACHER_ID'=>_('Teacher'),'WITH_PERIOD_ID'=>_('Period'));
$extra['singular'] = _('Unfilled Request');
$extra['plural'] = _('Unfilled Requests');
if(!$extra['link']['FULL_NAME'])
{
	$extra['link']['FULL_NAME']['link'] = 'Modules.php?modname=Scheduling/Requests.php';
	$extra['link']['FULL_NAME']['variables']['student_id'] = 'STUDENT_ID';
}
$extra['new'] = true;
$extra['Redirect'] = false;

Search('student_id',$extra);

function calcSeats()
{	global $THIS_RET;

	$periods_RET = DBGet(DBQuery("SELECT COURSE_PERIOD_ID,MARKING_PERIOD_ID,CALENDAR_ID,TOTAL_SEATS 
	FROM COURSE_PERIODS 
	WHERE COURSE_ID='".$THIS_RET['COURSE_ID']."' 
	AND (GENDER_RESTRICTION='N' OR GENDER_RESTRICTION='".mb_substr($THIS_RET['CUSTOM_200000000'],0,1)."')".
	($THIS_RET['WITH_TEACHER_ID']?" AND TEACHER_ID='".$THIS_RET['WITH_TEACHER_ID']."'":'').
	($THIS_RET['NOT_TEACHER_ID']?" AND TEACHER_ID!='".$THIS_RET['NOT_TEACHER_ID']."'":'').
	($THIS_RET['WITH_PERIOD_ID']?" AND PERIOD_ID='".$THIS_RET['WITH_PERIOD_ID']."'":'').
	($THIS_RET['NOT_PERIOD_ID']?" AND PERIOD_ID!='".$THIS_RET['NOT_PERIOD_ID']."'":'')));
	//echo '<pre>'; var_dump($periods_RET); echo '</pre>';
	
	foreach($periods_RET as $period)
	{
		$seats = calcSeats0($period);
		if($total_seats!==false)
			if($period['TOTAL_SEATS'])
				$total_seats += $period['TOTAL_SEATS'];
			else
				$total_seats = false;
		if($filled_seats!==false)
			if($seats!='')
				$filled_seats += $seats;
			else
				$filled_seats = false;
	}
	return ($total_seats!==false?($filled_seats!==false?$total_seats-$filled_seats:''):'n/a');
}

function _makeTeacher($value,$column)
{	global $THIS_RET;

	return ($value?_('With').': '.GetTeacher($value):'').($THIS_RET['NOT_TEACHER_ID']?($value?'<BR />':'')._('Without').': '.GetTeacher($THIS_RET['NOT_TEACHER_ID']):'');
}

function _makePeriod($value,$column)
{	global $THIS_RET;

	return ($value?_('On').': '.GetPeriod($value):'').($THIS_RET['NOT_PERIOD_ID']?($value?'<BR />':'')._('Not on').': '.GetPeriod($THIS_RET['NOT_PERIOD_ID']):'');
}
?>
